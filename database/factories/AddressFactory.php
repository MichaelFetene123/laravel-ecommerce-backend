<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'full_name' => fake()->name(),
            'line1' => fake()->streetAddress(),
            'line2' => fake()->secondaryAddress(),
            'city' => 'Addis Ababa',
            'region' => 'Addis Ababa',
            'country' => 'Ethiopia',
            'postal_code' => '1000',
            'phone' => '+2519'.fake()->numerify('########'),
        ];
    }
}
