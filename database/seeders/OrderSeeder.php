<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $john = User::where('email', 'john.doe@example.com')->first();
        $jane = User::where('email', 'jane.smith@example.com')->first();
        $abebe = User::where('email', 'abebe.kebede@example.com')->first();

        if (! $john || ! $jane || ! $abebe) {
            return;
        }

        $mbpVariant = ProductVariant::where('sku', 'MBP16-512-16-SG')->with('product')->first();
        $iphoneVariant = ProductVariant::where('sku', 'IPH15PM-256-BLK')->with('product')->first();
        $sonyVariant = ProductVariant::where('sku', 'SONY-XM5-BLK')->with('product')->first();
        $sweaterVariant = ProductVariant::where('sku', 'M-SWTR-BLK-M')->with('product')->first();

        if (! $mbpVariant || ! $iphoneVariant || ! $sonyVariant || ! $sweaterVariant) {
            return;
        }

        // Order 1: John Doe - Paid MacBook Pro & Sony XM5
        $this->createOrder(
            user: $john,
            orderNumber: 'ORD-20260828-001',
            txRef: 'TX-ET-2026-00101',
            status: 'paid',
            channel: 'telebirr',
            paidAt: now()->subDays(2),
            items: [
                ['variant' => $mbpVariant, 'quantity' => 1],
                ['variant' => $sonyVariant, 'quantity' => 1],
            ]
        );

        // Order 2: Jane Smith - Completed iPhone 15 Pro Max
        $this->createOrder(
            user: $jane,
            orderNumber: 'ORD-20260828-002',
            txRef: 'TX-ET-2026-00102',
            status: 'completed',
            channel: 'cbebirr',
            paidAt: now()->subDays(5),
            items: [
                ['variant' => $iphoneVariant, 'quantity' => 1],
                ['variant' => $sweaterVariant, 'quantity' => 2],
            ]
        );

        // Order 3: Abebe Kebede - Pending order
        $this->createOrder(
            user: $abebe,
            orderNumber: 'ORD-20260828-003',
            txRef: 'TX-ET-2026-00103',
            status: 'pending',
            channel: 'card',
            paidAt: null,
            items: [
                ['variant' => $sonyVariant, 'quantity' => 2],
            ]
        );
    }

    /**
     * Helper to create an order with items and payment.
     *
     * @param  array<int, array{variant: ProductVariant, quantity: int}>  $items
     */
    protected function createOrder(
        User $user,
        string $orderNumber,
        string $txRef,
        string $status,
        string $channel,
        ?\DateTimeInterface $paidAt,
        array $items
    ): void {
        $address = $user->addresses()->first();

        $subtotal = 0;
        $orderItemsData = [];

        foreach ($items as $item) {
            $variant = $item['variant'];
            $qty = $item['quantity'];
            $lineTotal = $variant->price * $qty;
            $subtotal += $lineTotal;

            $orderItemsData[] = [
                'variant' => $variant,
                'qty' => $qty,
                'unit_price' => $variant->price,
                'line_total' => $lineTotal,
            ];
        }

        $total = $subtotal;

        $order = Order::updateOrCreate(
            ['order_number' => $orderNumber],
            [
                'user_id' => $user->id,
                'tx_ref' => $txRef,
                'status' => $status,
                'billing_address_id' => $address?->id,
                'subtotal' => $subtotal,
                'total' => $total,
                'currency' => 'ETB',
                'paid_at' => $paidAt,
            ]
        );

        foreach ($orderItemsData as $itemData) {
            OrderItem::updateOrCreate(
                [
                    'order_id' => $order->id,
                    'product_variant_id' => $itemData['variant']->id,
                ],
                [
                    'product_title_snapshot' => $itemData['variant']->product->title,
                    'variant_sku_snapshot' => $itemData['variant']->sku,
                    'unit_price_snapshot' => $itemData['unit_price'],
                    'quantity' => $itemData['qty'],
                    'line_total' => $itemData['line_total'],
                ]
            );
        }

        Payment::updateOrCreate(
            ['tx_ref' => $txRef],
            [
                'order_id' => $order->id,
                'chapa_reference' => 'CHP-'.substr(md5($txRef), 0, 10),
                'amount' => $total,
                'currency' => 'ETB',
                'status' => $status === 'paid' || $status === 'completed' ? 'success' : 'initiated',
                'channel' => $channel,
                'webhook_payload' => [
                    'status' => $status === 'paid' || $status === 'completed' ? 'success' : 'pending',
                    'tx_ref' => $txRef,
                    'amount' => $total,
                    'currency' => 'ETB',
                ],
                'verified_at' => $paidAt,
            ]
        );
    }
}
