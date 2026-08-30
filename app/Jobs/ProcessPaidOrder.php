<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPaidOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $orderId) {}

    /**
     * Execute the job to decrement stock and process post-payment tasks.
     */
    public function handle(): void
    {
        $order = Order::with('items')->find($this->orderId);

        if (! $order) {
            Log::warning("ProcessPaidOrder: Order #{$this->orderId} not found.");

            return;
        }

        foreach ($order->items as $item) {
            if ($item->product_variant_id) {
                ProductVariant::where('id', $item->product_variant_id)
                    ->decrement('stock_quantity', $item->quantity);
            }
        }

        Log::info("ProcessPaidOrder: Successfully processed stock deduction and confirmation for Order #{$order->order_number} (ID: {$order->id}).");
    }
}
