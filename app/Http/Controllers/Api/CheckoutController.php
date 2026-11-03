<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaidOrder;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ChapaService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct(protected ChapaService $chapaService) {}

    /**
     * Handle checkout, create order, order items with snapshot, payment and initialize Chapa.
     *
     * @throws ValidationException
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'billing_address_id' => ['nullable', 'integer', 'exists:addresses,id'],
            'return_url' => ['nullable', 'url'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $currency = strtoupper($validated['currency'] ?? 'ETB');

        // Extract and load all requested variants
        $requestedItems = collect($validated['items']);
        $variantIds = $requestedItems->pluck('product_variant_id')->unique()->all();

        $variants = ProductVariant::with('product')
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        // Validate stock availability
        $subtotal = 0.0;
        foreach ($requestedItems as $item) {
            /** @var ProductVariant|null $variant */
            $variant = $variants->get($item['product_variant_id']);

            if (! $variant || $variant->stock_quantity < $item['quantity']) {
                $variantName = $variant ? "{$variant->product->title} ({$variant->sku})" : "Variant #{$item['product_variant_id']}";
                throw ValidationException::withMessages([
                    'items' => ["The requested quantity for '{$variantName}' exceeds available stock."],
                ]);
            }

            $subtotal += round(((float) $variant->price) * $item['quantity'], 2);
        }

        $total = $subtotal;
        $orderNumber = 'ORD-'.strtoupper(Str::random(10));
        $txRef = 'TX-'.strtoupper(Str::random(16));

        $nameParts = explode(' ', (string) $user->name, 2);
        $firstName = $validated['first_name'] ?? ($nameParts[0] ?? 'Customer');
        $lastName = $validated['last_name'] ?? ($nameParts[1] ?? 'User');
        $email = $validated['email'] ?? $user->email;
        $returnUrl = $validated['return_url'] ?? (config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')).'/checkout/callback');

        try {
            return DB::transaction(function () use (
                $user,
                $validated,
                $requestedItems,
                $variants,
                $subtotal,
                $total,
                $currency,
                $orderNumber,
                $txRef,
                $firstName,
                $lastName,
                $email,
                $returnUrl
            ) {
                // 1. Create Order
                $order = Order::create([
                    'user_id' => $user->id,
                    'order_number' => $orderNumber,
                    'tx_ref' => $txRef,
                    'status' => 'pending',
                    'billing_address_id' => $validated['billing_address_id'] ?? null,
                    'subtotal' => $subtotal,
                    'total' => $total,
                    'currency' => $currency,
                ]);

                // 2. Create Order Items with historical snapshot
                foreach ($requestedItems as $item) {
                    /** @var ProductVariant $variant */
                    $variant = $variants->get($item['product_variant_id']);
                    $lineTotal = round(((float) $variant->price) * $item['quantity'], 2);

                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_variant_id' => $variant->id,
                        'product_title_snapshot' => $variant->product?->title ?? 'Product',
                        'variant_sku_snapshot' => $variant->sku,
                        'unit_price_snapshot' => $variant->price,
                        'quantity' => $item['quantity'],
                        'line_total' => $lineTotal,
                    ]);
                }

                // 3. Create Payment record
                Payment::create([
                    'order_id' => $order->id,
                    'tx_ref' => $txRef,
                    'amount' => $total,
                    'currency' => $currency,
                    'status' => 'initiated',
                ]);

                // 4. Initialize Chapa Payment Gateway
                $returnUrlWithRef = $returnUrl;
                if (! str_contains($returnUrl, 'tx_ref=') && ! str_contains($returnUrl, 'trx_ref=')) {
                    $separator = str_contains($returnUrl, '?') ? '&' : '?';
                    $returnUrlWithRef .= $separator.http_build_query([
                        'tx_ref' => $txRef,
                        'order_number' => $order->order_number,
                    ]);
                }

                $chapaResponse = $this->chapaService->initializePayment([
                    'amount' => $total,
                    'currency' => $currency,
                    'email' => $email,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'tx_ref' => $txRef,
                    'return_url' => $returnUrlWithRef,
                    'order_number' => $order->order_number,
                ]);

                $checkoutUrl = $chapaResponse['data']['checkout_url'] ?? null;

                return response()->json([
                    'message' => 'Checkout initialized successfully.',
                    'order' => $order->load('items'),
                    'tx_ref' => $txRef,
                    'checkout_url' => $checkoutUrl,
                ], 201);
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
     * Verify payment transaction status and synchronize order state.
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tx_ref' => ['nullable', 'string'],
            'order_number' => ['nullable', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $txRef = $validated['tx_ref'] ?? null;
        $orderNumber = $validated['order_number'] ?? null;

        $order = null;
        $payment = null;

        // 1. Resolve by explicit transaction reference
        if ($txRef) {
            $payment = Payment::where('tx_ref', $txRef)->first();
            $order = $payment?->order ?? Order::where('tx_ref', $txRef)->first();
        }

        // 2. Resolve by order number
        if (! $order && $orderNumber) {
            $order = $user->orders()->where('order_number', $orderNumber)->first();
            if ($order) {
                $payment = $order->payments()->latest()->first();
                $txRef = $order->tx_ref ?? $payment?->tx_ref;
            }
        }

        // 3. Fallback: Resolve authenticated user's latest pending order
        if (! $order) {
            $order = $user->orders()->where('status', 'pending')->latest()->first();
            if ($order) {
                $payment = $order->payments()->latest()->first();
                $txRef = $order->tx_ref ?? $payment?->tx_ref;
            }
        }

        if (! $order || $order->user_id !== $user->id || ! $txRef) {
            return response()->json([
                'message' => 'No transaction reference or associated order found to verify.',
            ], 404);
        }

        if (! $payment) {
            $payment = Payment::where('order_id', $order->id)->latest()->first();
        }

        // If order is already confirmed as paid/completed, return immediately
        if ($order->isPaid() || ($payment && $payment->status === 'success')) {
            return response()->json([
                'status' => 'success',
                'message' => 'Payment has already been successfully verified.',
                'order' => $order->fresh()->load('items'),
                'payment' => $payment?->fresh(),
            ]);
        }

        // Synchronize state via server-to-server inquiry to Chapa
        try {
            $chapaData = $this->chapaService->verifyPayment($txRef);
            $chapaStatus = strtolower((string) ($chapaData['data']['status'] ?? ($chapaData['status'] ?? '')));

            return DB::transaction(function () use ($payment, $order, $chapaData, $chapaStatus) {
                $lockedPayment = $payment ? Payment::where('id', $payment->id)->lockForUpdate()->first() : null;
                $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

                if ($lockedOrder->isPaid() || ($lockedPayment && $lockedPayment->status === 'success')) {
                    return response()->json([
                        'status' => 'success',
                        'message' => 'Payment has already been successfully verified.',
                        'order' => $lockedOrder->fresh()->load('items'),
                        'payment' => $lockedPayment?->fresh(),
                    ]);
                }

                $isSuccess = $chapaStatus === 'success';

                if ($lockedPayment) {
                    $lockedPayment->update([
                        'status' => $isSuccess ? 'success' : 'failed',
                        'chapa_reference' => $chapaData['data']['reference'] ?? null,
                        'channel' => $chapaData['data']['method'] ?? ($chapaData['data']['payment_method'] ?? null),
                        'webhook_payload' => $chapaData,
                        'verified_at' => now(),
                    ]);
                }

                if ($isSuccess) {
                    $lockedOrder->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                    ]);

                    ProcessPaidOrder::dispatch($lockedOrder->id);
                } else {
                    $lockedOrder->update([
                        'status' => 'failed',
                    ]);
                }

                return response()->json([
                    'status' => $isSuccess ? 'success' : 'failed',
                    'message' => $isSuccess ? 'Payment verified successfully.' : 'Payment verification failed.',
                    'order' => $lockedOrder->fresh()->load('items'),
                    'payment' => $lockedPayment?->fresh(),
                ]);
            });
        } catch (Exception $e) {
            return response()->json([
                'status' => 'pending',
                'message' => 'Could not verify payment status with gateway: '.$e->getMessage(),
                'order' => $order->fresh()->load('items'),
                'payment' => $payment?->fresh(),
            ], 200);
        }
    }
}
