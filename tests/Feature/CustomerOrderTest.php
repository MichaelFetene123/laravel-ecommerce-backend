<?php

use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

test('unauthenticated users cannot access customer orders endpoints', function () {
    getJson('/api/orders')->assertUnauthorized();
    getJson('/api/orders/ORD-NONEXISTENT')->assertUnauthorized();
});

test('authenticated customer can list their own orders paginated with relations', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $variant = ProductVariant::factory()->create();

    // Create 15 orders for user 1 with items and payments
    for ($i = 1; $i <= 15; $i++) {
        $order = Order::factory()->create([
            'user_id' => $user1->id,
            'order_number' => "ORD-USER1-{$i}",
            'created_at' => now()->subMinutes(16 - $i), // Latest has larger index
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_variant_id' => $variant->id,
        ]);

        Payment::factory()->create([
            'order_id' => $order->id,
        ]);
    }

    // Create 3 orders for user 2
    Order::factory()->count(3)->create([
        'user_id' => $user2->id,
        'order_number' => fn () => 'ORD-USER2-'.fake()->unique()->randomNumber(5),
    ]);

    Sanctum::actingAs($user1);

    $response = getJson('/api/orders');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'order_number',
                    'status',
                    'total',
                    'items' => [
                        '*' => ['id', 'product_title_snapshot', 'line_total'],
                    ],
                    'payments' => [
                        '*' => ['id', 'amount', 'status'],
                    ],
                ],
            ],
            'current_page',
            'per_page',
            'total',
        ]);

    expect($response->json('total'))->toBe(15)
        ->and($response->json('per_page'))->toBe(10)
        ->and($response->json('data'))->toHaveCount(10)
        ->and($response->json('data.0.order_number'))->toBe('ORD-USER1-15'); // Latest first
});

test('authenticated customer can view their specific order by order_number with relations', function () {
    $user = User::factory()->create();
    $address = Address::factory()->create(['user_id' => $user->id]);
    $variant = ProductVariant::factory()->create();

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'order_number' => 'ORD-TARGET-12345',
        'billing_address_id' => $address->id,
        'status' => 'pending',
    ]);

    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'product_title_snapshot' => 'Awesome Product',
    ]);

    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'amount' => $order->total,
        'status' => 'initiated',
    ]);

    Sanctum::actingAs($user);

    $response = getJson('/api/orders/ORD-TARGET-12345');

    $response->assertOk()
        ->assertJson([
            'id' => $order->id,
            'order_number' => 'ORD-TARGET-12345',
            'billing_address' => [
                'id' => $address->id,
                'full_name' => $address->full_name,
            ],
            'items' => [
                [
                    'id' => $item->id,
                    'product_title_snapshot' => 'Awesome Product',
                ],
            ],
            'payments' => [
                [
                    'id' => $payment->id,
                    'status' => 'initiated',
                ],
            ],
        ]);
});

test('customer cannot view another customer order and receives 404', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    Order::factory()->create([
        'user_id' => $user2->id,
        'order_number' => 'ORD-USER2-SECRET',
    ]);

    Sanctum::actingAs($user1);

    getJson('/api/orders/ORD-USER2-SECRET')->assertNotFound();
});

test('viewing non-existent order returns 404', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    getJson('/api/orders/ORD-DOES-NOT-EXIST')->assertNotFound();
});
