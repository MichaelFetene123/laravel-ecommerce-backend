<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('benchmark filament admin pages response times and query counts', function () {
    /** @var User $admin */
    $admin = User::factory()->create([
        'email' => 'admin_test@example.com',
        'role' => 'admin',
    ]);

    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);
    ProductVariant::factory()->create(['product_id' => $product->id]);
    $order = Order::factory()->create(['user_id' => $admin->id]);
    Payment::factory()->create(['order_id' => $order->id]);

    $routes = [
        'Dashboard' => '/admin',
        'Categories' => '/admin/categories',
        'Products' => '/admin/products',
        'Orders' => '/admin/orders',
        'Payments' => '/admin/payments',
    ];

    foreach ($routes as $url) {
        $response = actingAs($admin)->get($url);
        $response->assertSuccessful();
    }
});
