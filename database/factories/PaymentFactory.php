<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'tx_ref' => 'TX-'.strtoupper(Str::random(14)),
            'chapa_reference' => 'CHP-'.strtoupper(Str::random(12)),
            'amount' => fake()->randomFloat(2, 50, 2000),
            'currency' => 'ETB',
            'status' => fake()->randomElement(['initiated', 'success', 'failed']),
            'channel' => fake()->randomElement(['telebirr', 'cbebirr', 'card']),
            'webhook_payload' => [
                'status' => 'success',
                'message' => 'Payment completed successfully',
            ],
            'verified_at' => now(),
        ];
    }
}
