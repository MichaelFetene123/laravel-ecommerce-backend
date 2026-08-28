<?php

namespace App\Providers;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Observers\AttributeObserver;
use App\Observers\AttributeValueObserver;
use App\Observers\CategoryObserver;
use App\Observers\ProductImageObserver;
use App\Observers\ProductObserver;
use App\Observers\ProductVariantObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Category::observe(CategoryObserver::class);
        Product::observe(ProductObserver::class);
        Attribute::observe(AttributeObserver::class);
        AttributeValue::observe(AttributeValueObserver::class);
        ProductVariant::observe(ProductVariantObserver::class);
        ProductImage::observe(ProductImageObserver::class);
    }
}
