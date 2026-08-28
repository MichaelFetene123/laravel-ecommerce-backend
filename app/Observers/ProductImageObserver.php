<?php

namespace App\Observers;

use App\Models\ProductImage;
use App\Services\CatalogCacheService;

class ProductImageObserver
{
    public function __construct(
        protected CatalogCacheService $catalogCache
    ) {}

    public function created(ProductImage $image): void
    {
        $this->catalogCache->flushProduct($image->product_id);
    }

    public function updated(ProductImage $image): void
    {
        $this->catalogCache->flushProduct($image->product_id);
    }

    public function deleted(ProductImage $image): void
    {
        $this->catalogCache->flushProduct($image->product_id);
    }
}
