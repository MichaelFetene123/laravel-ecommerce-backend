<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
            'return_url' => ['required', 'url'],
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
                $email
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
                $chapaResponse = $this->chapaService->initializePayment([
                    'amount' => $total,
                    'currency' => $currency,
                    'email' => $email,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'tx_ref' => $txRef,
                    'return_url' => $validated['return_url'],
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
}
