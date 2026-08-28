<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $qty = fake()->numberBetween(1, 3);
        $price = fake()->randomFloat(2, 20, 500);

        return [
            'order_id' => Order::factory(),
            'product_variant_id' => ProductVariant::factory(),
            'product_title_snapshot' => fake()->words(3, true),
            'variant_sku_snapshot' => 'SKU-'.fake()->bothify('####-????'),
            'unit_price_snapshot' => $price,
            'quantity' => $qty,
            'line_total' => $price * $qty,
        ];
    }
}
