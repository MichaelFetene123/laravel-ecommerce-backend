<?php

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\getJson;

uses(RefreshDatabase::class);

test('public categories endpoint returns active root categories with active children ordered by sort order', function () {
    // Root categories with sort_order
    $rootCat2 = Category::factory()->create([
        'parent_id' => null,
        'name' => 'Root Category 2',
        'sort_order' => 20,
        'is_active' => true,
    ]);

    $rootCat1 = Category::factory()->create([
        'parent_id' => null,
        'name' => 'Root Category 1',
        'sort_order' => 10,
        'is_active' => true,
    ]);

    // Inactive root category
    Category::factory()->create([
        'parent_id' => null,
        'name' => 'Inactive Root Category',
        'sort_order' => 5,
        'is_active' => false,
    ]);

    // Active and inactive children under rootCat1
    $activeChild = Category::factory()->create([
        'parent_id' => $rootCat1->id,
        'name' => 'Active Child Category',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    Category::factory()->create([
        'parent_id' => $rootCat1->id,
        'name' => 'Inactive Child Category',
        'sort_order' => 2,
        'is_active' => false,
    ]);

    $response = getJson('/api/categories');

    $response->assertSuccessful()
        ->assertJsonCount(2);

    $data = $response->json();
    expect($data[0]['id'])->toBe($rootCat1->id)
        ->and($data[1]['id'])->toBe($rootCat2->id)
        ->and($data[0]['children'])->toHaveCount(1)
        ->and($data[0]['children'][0]['id'])->toBe($activeChild->id);
});

test('public products endpoint returns active products with default variant and images paginated', function () {
    // Create 25 active products
    $activeProducts = Product::factory()->count(25)->create([
        'is_active' => true,
    ]);

    // Create 3 inactive products
    Product::factory()->count(3)->create([
        'is_active' => false,
    ]);

    foreach ($activeProducts as $product) {
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_default' => true,
            'price' => 99.99,
        ]);

        ProductImage::factory()->create([
            'product_id' => $product->id,
            'path' => 'products/sample.png',
        ]);
    }

    $response = getJson('/api/products');

    $response->assertSuccessful()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'slug',
                    'is_active',
                    'default_variant' => ['id', 'price', 'is_default'],
                    'images' => [
                        '*' => ['id', 'path'],
                    ],
                ],
            ],
            'current_page',
            'per_page',
            'total',
        ]);

    expect($response->json('per_page'))->toBe(20)
        ->and($response->json('total'))->toBe(25)
        ->and($response->json('data'))->toHaveCount(20);
});

test('public product show endpoint returns product details with relations', function () {
    $category = Category::factory()->create(['name' => 'Smartphones']);

    /** @var Product $product */
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'title' => 'Flagship Phone 15',
        'slug' => 'flagship-phone-15',
        'is_active' => true,
    ]);

    $image = ProductImage::factory()->create([
        'product_id' => $product->id,
        'path' => 'products/phone.png',
    ]);

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'sku' => 'PHONE-15-BLK',
        'price' => 999.00,
        'is_default' => true,
    ]);

    $colorAttribute = Attribute::factory()->create(['name' => 'Color']);
    $colorValue = AttributeValue::factory()->create([
        'attribute_id' => $colorAttribute->id,
        'value' => 'Midnight Black',
        'slug' => 'midnight-black',
    ]);

    $variant->attributeValues()->attach($colorValue->id);

    $response = getJson('/api/products/flagship-phone-15');

    $response->assertSuccessful()
        ->assertJson([
            'id' => $product->id,
            'title' => 'Flagship Phone 15',
            'slug' => 'flagship-phone-15',
            'category' => [
                'id' => $category->id,
                'name' => 'Smartphones',
            ],
            'images' => [
                ['id' => $image->id, 'path' => 'products/phone.png'],
            ],
            'variants' => [
                [
                    'id' => $variant->id,
                    'sku' => 'PHONE-15-BLK',
                    'attribute_values' => [
                        [
                            'id' => $colorValue->id,
                            'value' => 'Midnight Black',
                            'attribute' => [
                                'id' => $colorAttribute->id,
                                'name' => 'Color',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
});

test('public product show endpoint returns 404 for non-existent product', function () {
    getJson('/api/products/non-existent-product')
        ->assertNotFound();
});

test('public product show endpoint returns 404 for inactive product', function () {
    Product::factory()->create([
        'title' => 'Inactive Product',
        'slug' => 'inactive-product',
        'is_active' => false,
    ]);

    getJson('/api/products/inactive-product')
        ->assertNotFound();
});
