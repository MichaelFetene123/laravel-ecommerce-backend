<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use App\Services\ChapaService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerOrderController extends Controller
{
    public function __construct(protected ChapaService $chapaService) {}

    /**
     * Display a paginated listing of the authenticated user's orders.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $orders = $user->orders()
            ->with(['items', 'payments'])
            ->latest()
            ->paginate(10);

        return response()->json($orders);
    }

    /**
     * Display the specified order for the authenticated user by order_number.
     */
    public function show(Request $request, string $orderNumber): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $order = $user->orders()
            ->where('order_number', $orderNumber)
            ->with(['items', 'billingAddress', 'payments'])
            ->firstOrFail();

        return response()->json($order);
    }

    /**
     * Initiate Chapa payment for an existing pending order.
     */
    public function pay(Request $request, string $orderNumber): JsonResponse
    {
        $validated = $request->validate([
            'return_url' => ['nullable', 'url'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $order = $user->orders()
            ->where('order_number', $orderNumber)
            ->with(['items.variant.product'])
            ->firstOrFail();

        if ($order->isPaid()) {
            return response()->json([
                'message' => 'This order has already been paid.',
            ], 422);
        }

        // Validate stock availability using database records
        foreach ($order->items as $item) {
            $variant = $item->variant;
            if ($variant && $variant->stock_quantity < $item->quantity) {
                $variantName = $variant->product?->title ?? "Item #{$item->id}";
                throw ValidationException::withMessages([
                    'items' => ["The requested quantity for '{$variantName}' exceeds available stock."],
                ]);
            }
        }

        $txRef = 'TX-'.strtoupper(Str::random(16));
        $returnUrl = $validated['return_url'] ?? (config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')).'/checkout/callback');

        $nameParts = explode(' ', (string) $user->name, 2);
        $firstName = $nameParts[0] ?? 'Customer';
        $lastName = $nameParts[1] ?? 'User';

        try {
            return DB::transaction(function () use ($order, $user, $txRef, $returnUrl, $firstName, $lastName) {
                $order->update([
                    'tx_ref' => $txRef,
                    'status' => 'pending',
                ]);

                Payment::create([
                    'order_id' => $order->id,
                    'tx_ref' => $txRef,
                    'amount' => $order->total,
                    'currency' => $order->currency,
                    'status' => 'initiated',
                ]);

                $returnUrlWithRef = $returnUrl;
                if (! str_contains($returnUrl, 'tx_ref=') && ! str_contains($returnUrl, 'trx_ref=')) {
                    $separator = str_contains($returnUrl, '?') ? '&' : '?';
                    $returnUrlWithRef .= $separator.http_build_query([
                        'tx_ref' => $txRef,
                        'order_number' => $order->order_number,
                    ]);
                }

                $chapaResponse = $this->chapaService->initializePayment([
                    'amount' => (float) $order->total,
                    'currency' => $order->currency,
                    'email' => $user->email,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'tx_ref' => $txRef,
                    'return_url' => $returnUrlWithRef,
                    'order_number' => $order->order_number,
                ]);

                $checkoutUrl = $chapaResponse['data']['checkout_url'] ?? null;

                return response()->json([
                    'message' => 'Payment initialized successfully.',
                    'order' => $order->fresh()->load('items'),
                    'tx_ref' => $txRef,
                    'checkout_url' => $checkoutUrl,
                ], 200);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Payment initialization failed: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Verify payment status for an existing order by order_number.
     */
    public function verify(Request $request, string $orderNumber): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $order = $user->orders()
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        $txRef = $order->tx_ref;

        if (! $txRef) {
            return response()->json([
                'message' => 'No transaction reference found for this order.',
            ], 404);
        }

        $request->merge(['tx_ref' => $txRef]);
        /** @var CheckoutController $checkoutController */
        $checkoutController = app(CheckoutController::class);

        return $checkoutController->verify($request);
    }
}
