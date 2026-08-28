<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 50, 2000);
        $total = $subtotal;

        return [
            'user_id' => User::factory(),
            'order_number' => 'ORD-'.strtoupper(Str::random(10)),
            'tx_ref' => 'TX-'.strtoupper(Str::random(14)),
            'status' => fake()->randomElement(['pending', 'paid', 'completed']),
            'billing_address_id' => Address::factory(),
            'subtotal' => $subtotal,
            'total' => $total,
            'currency' => 'ETB',
            'paid_at' => fake()->optional(0.7)->dateTimeThisMonth(),
        ];
    }
}
