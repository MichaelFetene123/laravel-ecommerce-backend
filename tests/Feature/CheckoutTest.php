<?php

use App\Jobs\ProcessPaidOrder;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withHeaders;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('services.chapa.secret_key', 'CHASECK_TEST-1234567890');
    Config::set('services.chapa.base_url', 'https://api.chapa.co/v1');
    Config::set('services.chapa.webhook_secret', 'CHASECK_TEST-1234567890');
});

test('unauthenticated checkout request is rejected', function () {
    postJson('/api/checkout', [
        'items' => [['product_variant_id' => 1, 'quantity' => 1]],
        'return_url' => 'http://localhost:3000/orders/success',
    ])->assertUnauthorized();
});

test('authenticated user can checkout and initialize chapa payment', function () {
    /** @var User $user */
    $user = User::factory()->create([
        'name' => 'Abebe Bikila',
        'email' => 'abebe@example.com',
    ]);

    $category = Category::factory()->create(['name' => 'Shoes']);
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'title' => 'Running Sneakers',
    ]);

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'sku' => 'RUN-SNK-42',
        'price' => 2500.00,
        'stock_quantity' => 10,
    ]);

    Http::fake([
        'https://api.chapa.co/v1/transaction/initialize' => Http::response([
            'status' => 'success',
            'message' => 'Hosted Link',
            'data' => [
                'checkout_url' => 'https://checkout.chapa.co/checkout/payment/CHAPA-12345',
            ],
        ], 200),
    ]);

    actingAs($user, 'sanctum');

    $response = postJson('/api/checkout', [
        'items' => [
            [
                'product_variant_id' => $variant->id,
                'quantity' => 2,
            ],
        ],
        'return_url' => 'http://localhost:3000/orders/success',
    ]);

    $response->assertCreated()
        ->assertJson([
            'message' => 'Checkout initialized successfully.',
            'checkout_url' => 'https://checkout.chapa.co/checkout/payment/CHAPA-12345',
        ])
        ->assertJsonStructure([
            'order' => [
                'id',
                'order_number',
                'tx_ref',
                'subtotal',
                'total',
                'items',
            ],
            'tx_ref',
            'checkout_url',
        ]);

    $txRef = $response->json('tx_ref');

    // Verify database records
    assertDatabaseHas('orders', [
        'user_id' => $user->id,
        'tx_ref' => $txRef,
        'status' => 'pending',
        'subtotal' => 5000.00,
        'total' => 5000.00,
        'currency' => 'ETB',
    ]);

    assertDatabaseHas('order_items', [
        'product_variant_id' => $variant->id,
        'product_title_snapshot' => 'Running Sneakers',
        'variant_sku_snapshot' => 'RUN-SNK-42',
        'unit_price_snapshot' => 2500.00,
        'quantity' => 2,
        'line_total' => 5000.00,
    ]);

    assertDatabaseHas('payments', [
        'tx_ref' => $txRef,
        'amount' => 5000.00,
        'currency' => 'ETB',
        'status' => 'initiated',
    ]);
});

test('checkout fails when requested variant quantity exceeds available stock', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $product = Product::factory()->create(['title' => 'Limited Edition Watch']);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'sku' => 'WATCH-LTD',
        'price' => 15000.00,
        'stock_quantity' => 1,
    ]);

    actingAs($user, 'sanctum');

    $response = postJson('/api/checkout', [
        'items' => [
            [
                'product_variant_id' => $variant->id,
                'quantity' => 5, // Exceeds stock of 1
            ],
        ],
        'return_url' => 'http://localhost:3000/orders/success',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['items']);
});

test('checkout rolls back transaction if Chapa initialization fails', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 100.00,
        'stock_quantity' => 10,
    ]);

    Http::fake([
        'https://api.chapa.co/v1/transaction/initialize' => Http::response([
            'message' => 'Invalid key or unauthorized',
            'status' => 'failed',
        ], 401),
    ]);

    actingAs($user, 'sanctum');

    $response = postJson('/api/checkout', [
        'items' => [
            [
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ],
        ],
        'return_url' => 'http://localhost:3000/orders/success',
    ]);

    $response->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'Payment initialization failed: Chapa initialization failed: {"message":"Invalid key or unauthorized","status":"failed"}',
        ]);

    expect(Order::count())->toBe(0);
    expect(OrderItem::count())->toBe(0);
    expect(Payment::count())->toBe(0);
});

test('webhook verifies signature, marks payment success, order paid, and dispatches stock job', function () {
    Queue::fake();

    /** @var User $user */
    $user = User::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'tx_ref' => 'TX-TEST-WEBHOOK-99',
        'status' => 'pending',
        'total' => 1200.00,
    ]);

    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'tx_ref' => 'TX-TEST-WEBHOOK-99',
        'amount' => 1200.00,
        'status' => 'initiated',
    ]);

    $payload = [
        'event' => 'charge.complete',
        'tx_ref' => 'TX-TEST-WEBHOOK-99',
        'reference' => 'CHAPA-REF-998877',
        'status' => 'success',
        'amount' => 1200.00,
        'currency' => 'ETB',
        'payment_method' => 'telebirr',
    ];

    $rawContent = json_encode($payload);
    $signature = hash_hmac('sha256', (string) $rawContent, 'CHASECK_TEST-1234567890');

    $response = withHeaders([
        'x-chapa-signature' => $signature,
    ])->postJson('/api/webhooks/chapa', $payload);

    $response->assertSuccessful()
        ->assertJson(['status' => 'success']);

    $payment->refresh();
    $order->refresh();

    expect($payment->status)->toBe('success')
        ->and($payment->chapa_reference)->toBe('CHAPA-REF-998877')
        ->and($payment->channel)->toBe('telebirr')
        ->and($payment->verified_at)->not->toBeNull()
        ->and($order->status)->toBe('paid')
        ->and($order->paid_at)->not->toBeNull();

    Queue::assertPushed(ProcessPaidOrder::class, function ($job) use ($order) {
        return $job->orderId === $order->id;
    });
});

test('webhook rejects invalid x-chapa-signature with 401', function () {
    $payload = [
        'tx_ref' => 'TX-INVALID-SIG',
        'status' => 'success',
    ];

    $response = withHeaders([
        'x-chapa-signature' => 'invalid-tampered-signature',
    ])->postJson('/api/webhooks/chapa', $payload);

    $response->assertStatus(401)
        ->assertJson(['message' => 'Invalid webhook signature.']);
});

test('webhook handles duplicate idempotent notifications without re-dispatching', function () {
    Queue::fake();

    /** @var User $user */
    $user = User::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'tx_ref' => 'TX-IDEMPOTENT-100',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    Payment::factory()->create([
        'order_id' => $order->id,
        'tx_ref' => 'TX-IDEMPOTENT-100',
        'status' => 'success',
        'verified_at' => now(),
    ]);

    $payload = [
        'tx_ref' => 'TX-IDEMPOTENT-100',
        'status' => 'success',
    ];

    $signature = hash_hmac('sha256', (string) json_encode($payload), 'CHASECK_TEST-1234567890');

    $response = withHeaders([
        'x-chapa-signature' => $signature,
    ])->postJson('/api/webhooks/chapa', $payload);

    $response->assertSuccessful()
        ->assertJson([
            'status' => 'success',
            'message' => 'Payment has already been successfully processed.',
        ]);

    Queue::assertNothingPushed();
});

test('ProcessPaidOrder job decrements variant stock correctly', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'stock_quantity' => 15,
    ]);

    $user = User::factory()->create();
    $order = Order::factory()->create(['user_id' => $user->id]);

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_variant_id' => $variant->id,
        'quantity' => 4,
    ]);

    // Execute the job synchronously
    $job = new ProcessPaidOrder($order->id);
    $job->handle();

    $variant->refresh();
    expect($variant->stock_quantity)->toBe(11);
});
