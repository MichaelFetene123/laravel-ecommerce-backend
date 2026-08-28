<?php

namespace App\Observers;

use App\Models\ProductVariant;
use App\Services\CatalogCacheService;

class ProductVariantObserver
{
    public function __construct(
        protected CatalogCacheService $catalogCache
    ) {}

    public function created(ProductVariant $variant): void
    {
        $this->catalogCache->flushVariant($variant);
    }

    public function updated(ProductVariant $variant): void
    {
        $this->catalogCache->flushVariant($variant);
    }

    public function deleted(ProductVariant $variant): void
    {
        $this->catalogCache->flushVariant($variant);
    }
}
