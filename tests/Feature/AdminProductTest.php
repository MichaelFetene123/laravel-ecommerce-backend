<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

uses(RefreshDatabase::class);

test('guest cannot access admin products endpoints', function () {
    getJson('/api/admin/products')->assertUnauthorized();
    postJson('/api/admin/products', [])->assertUnauthorized();
});

test('non-admin customer receives 403 forbidden on admin products endpoints', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    Sanctum::actingAs($customer);

    getJson('/api/admin/products')->assertForbidden();
    postJson('/api/admin/products', [])->assertForbidden();
});

test('admin can list products with filters, sorting, and stats', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Sanctum::actingAs($admin);

    $category = Category::factory()->create(['name' => 'Laptops', 'slug' => 'laptops']);

    $product1 = Product::factory()->create([
        'category_id' => $category->id,
        'title' => 'Alpha Ultra Laptop',
        'slug' => 'alpha-ultra-laptop',
        'is_active' => true,
        'is_featured' => true,
    ]);
    ProductVariant::factory()->create([
        'product_id' => $product1->id,
        'sku' => 'ALPHA-001',
        'price' => 1999.00,
        'stock_quantity' => 10,
        'is_default' => true,
    ]);

    $product2 = Product::factory()->create([
        'category_id' => $category->id,
        'title' => 'Beta Budget Phone',
        'slug' => 'beta-budget-phone',
        'is_active' => false,
        'is_featured' => false,
    ]);
    ProductVariant::factory()->create([
        'product_id' => $product2->id,
        'sku' => 'BETA-002',
        'price' => 499.00,
        'stock_quantity' => 2,
        'is_default' => true,
    ]);

    // Admin listing returns both active and inactive
    $response = getJson('/api/admin/products');
    $response->assertSuccessful();
    expect($response->json('total'))->toBe(2);
    expect($response->json('stats.active'))->toBe(1);
    expect($response->json('stats.draft'))->toBe(1);

    // Search by title or sku
    $searchRes = getJson('/api/admin/products?search=ALPHA');
    $searchRes->assertSuccessful();
    expect($searchRes->json('total'))->toBe(1);
    expect($searchRes->json('data.0.title'))->toBe('Alpha Ultra Laptop');

    // Status filter
    $statusRes = getJson('/api/admin/products?status=inactive');
    $statusRes->assertSuccessful();
    expect($statusRes->json('total'))->toBe(1);
    expect($statusRes->json('data.0.slug'))->toBe('beta-budget-phone');
});

test('admin can create, update, toggle, and delete a product', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    Sanctum::actingAs($admin);

    $category = Category::factory()->create(['name' => 'Monitors', 'slug' => 'monitors']);

    // 1. Create product
    $createPayload = [
        'title' => 'Studio Pro Monitor 27"',
        'slug' => 'studio-pro-monitor-27',
        'category_id' => $category->id,
        'description' => '4K Retina grade external display.',
        'is_active' => true,
        'is_featured' => true,
        'sku' => 'SPM-27-4K',
        'price' => 1299.00,
        'compare_at_price' => 1499.00,
        'stock_quantity' => 25,
        'weight' => 5.2,
    ];

    $createRes = postJson('/api/admin/products', $createPayload);
    $createRes->assertCreated();
    $productId = $createRes->json('data.id');
    expect($productId)->toBeGreaterThan(0);
    expect($createRes->json('data.default_variant.sku'))->toBe('SPM-27-4K');

    // 2. Update product
    $updateRes = putJson("/api/admin/products/{$productId}", [
        'title' => 'Studio Pro Monitor 27" Updated',
        'price' => 1199.00,
        'stock_quantity' => 30,
    ]);
    $updateRes->assertSuccessful();
    expect($updateRes->json('data.title'))->toBe('Studio Pro Monitor 27" Updated');
    expect(floatval($updateRes->json('data.default_variant.price')))->toBe(1199.0);

    // 3. Toggle status
    $toggleRes = patchJson("/api/admin/products/{$productId}/status");
    $toggleRes->assertSuccessful();
    expect($toggleRes->json('data.is_active'))->toBeFalse();

    // 4. Update stock fast
    $stockRes = patchJson("/api/admin/products/{$productId}/stock", ['stock_quantity' => 50]);
    $stockRes->assertSuccessful();
    expect($stockRes->json('data.stock_quantity'))->toBe(50);

    // 5. Delete product
    $deleteRes = deleteJson("/api/admin/products/{$productId}");
    $deleteRes->assertSuccessful();
    expect(Product::find($productId))->toBeNull();
});

