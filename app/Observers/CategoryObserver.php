<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\CatalogCacheService;

class CategoryObserver
{
    public function __construct(
        protected CatalogCacheService $catalogCache
    ) {}

    public function created(Category $category): void
    {
        $this->catalogCache->flushCategory($category);
    }

    public function updated(Category $category): void
    {
        $this->catalogCache->flushCategory($category);
    }

    public function deleted(Category $category): void
    {
        $this->catalogCache->flushCategory($category);
    }

    public function restored(Category $category): void
    {
        $this->catalogCache->flushCategory($category);
    }
}
