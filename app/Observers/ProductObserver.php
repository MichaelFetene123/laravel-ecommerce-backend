<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\CatalogCacheService;

class ProductObserver
{
    public function __construct(
        protected CatalogCacheService $catalogCache
    ) {}

    public function created(Product $product): void
    {
        $this->catalogCache->flushProduct($product);
    }

    public function updated(Product $product): void
    {
        $this->catalogCache->flushProduct($product);
    }

    public function deleted(Product $product): void
    {
        $this->catalogCache->flushProduct($product);
    }

    public function restored(Product $product): void
    {
        $this->catalogCache->flushProduct($product);
    }
}
