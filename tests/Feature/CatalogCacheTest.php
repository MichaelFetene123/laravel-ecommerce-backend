<?php

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CatalogCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function catalogService(): CatalogCacheService
{
    return app(CatalogCacheService::class);
}

beforeEach(function () {
    catalogService()->flushAll();
});

test('categories are cached and retrieved on subsequent requests without querying the database', function () {
    Category::factory()->count(3)->create(['is_active' => true]);

    // Initial fetch - populates cache
    DB::enableQueryLog();
    DB::flushQueryLog();

    $categories = catalogService()->getAllCategories();
    expect($categories)->toHaveCount(3);
    $firstQueryCount = count(DB::getQueryLog());
    expect($firstQueryCount)->toBeGreaterThan(0);

    // Second fetch - should hit cache with 0 database queries
    DB::flushQueryLog();
    $cachedCategories = catalogService()->getAllCategories();
    expect($cachedCategories)->toHaveCount(3);
    expect(DB::getQueryLog())->toHaveCount(0);
});

test('category cache is invalidated when a new category is created', function () {
    Category::factory()->count(2)->create(['is_active' => true]);

    $initial = catalogService()->getAllCategories();
    expect($initial)->toHaveCount(2);

    // Create a new category
    Category::factory()->create(['is_active' => true]);

    // Fetch again - should reflect updated count
    $updated = catalogService()->getAllCategories();
    expect($updated)->toHaveCount(3);
});

test('category cache is invalidated when a category is updated', function () {
    $category = Category::factory()->create([
        'name' => 'Old Category Name',
        'slug' => 'old-category-name',
        'is_active' => true,
    ]);

    $cached = catalogService()->getCategoryById($category->id);
    expect($cached->name)->toBe('Old Category Name');

    // Update category
    $category->update(['name' => 'New Category Name']);

    // Fetch again
    $refreshed = catalogService()->getCategoryById($category->id);
    expect($refreshed->name)->toBe('New Category Name');
});

test('category cache is invalidated when a category is deleted', function () {
    $category = Category::factory()->create(['is_active' => true]);

    $cached = catalogService()->getAllCategories();
    expect($cached)->toHaveCount(1);

    $category->delete();

    $afterDelete = catalogService()->getAllCategories();
    expect($afterDelete)->toHaveCount(0);
});

test('products are cached and retrieved on subsequent requests without querying the database', function () {
    $category = Category::factory()->create(['is_active' => true]);
    Product::factory()->count(4)->create([
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    DB::enableQueryLog();
    DB::flushQueryLog();

    $products = catalogService()->getProducts();
    expect($products->total())->toBe(4);
    expect(count(DB::getQueryLog()))->toBeGreaterThan(0);

    // Second fetch - should hit cache with 0 DB queries
    DB::flushQueryLog();
    $cachedProducts = catalogService()->getProducts();
    expect($cachedProducts->total())->toBe(4);
    expect(DB::getQueryLog())->toHaveCount(0);
});

test('product cache is invalidated when a product is updated or deleted', function () {
    $product = Product::factory()->create([
        'title' => 'Original Laptop',
        'slug' => 'original-laptop',
        'is_active' => true,
    ]);

    $cached = catalogService()->getProductById($product->id);
    expect($cached->title)->toBe('Original Laptop');

    // Update
    $product->update(['title' => 'Updated Laptop']);
    $refreshed = catalogService()->getProductById($product->id);
    expect($refreshed->title)->toBe('Updated Laptop');

    // Delete
    $product->delete();
    $afterDelete = catalogService()->getProductById($product->id);
    expect($afterDelete)->toBeNull();
});

test('attributes and values are cached and invalidated on change', function () {
    $attribute = Attribute::factory()->create(['name' => 'Color', 'slug' => 'color']);
    AttributeValue::factory()->create([
        'attribute_id' => $attribute->id,
        'value' => 'Red',
    ]);

    DB::enableQueryLog();
    DB::flushQueryLog();

    $attributes = catalogService()->getAllAttributesWithValues();
    expect($attributes)->toHaveCount(1);
    expect($attributes->first()->values)->toHaveCount(1);
    expect(count(DB::getQueryLog()))->toBeGreaterThan(0);

    // Cache hit
    DB::flushQueryLog();
    $cached = catalogService()->getAllAttributesWithValues();
    expect($cached)->toHaveCount(1);
    expect(DB::getQueryLog())->toHaveCount(0);

    // Adding attribute value invalidates cache
    AttributeValue::factory()->create([
        'attribute_id' => $attribute->id,
        'value' => 'Blue',
    ]);

    $refreshed = catalogService()->getAllAttributesWithValues();
    expect($refreshed->first()->values)->toHaveCount(2);
});

test('product variants are cached and invalidated on change', function () {
    $product = Product::factory()->create(['is_active' => true]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'sku' => 'SKU-001',
        'price' => 99.99,
    ]);

    $variants = catalogService()->getVariantsByProductId($product->id);
    expect($variants)->toHaveCount(1);
    expect($variants->first()->sku)->toBe('SKU-001');

    // Update variant price
    $variant->update(['price' => 79.99]);

    $refreshed = catalogService()->getVariantsByProductId($product->id);
    expect((float) $refreshed->first()->price)->toBe(79.99);
});
