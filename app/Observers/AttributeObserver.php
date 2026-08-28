<?php

namespace App\Observers;

use App\Models\Attribute;
use App\Services\CatalogCacheService;

class AttributeObserver
{
    public function __construct(
        protected CatalogCacheService $catalogCache
    ) {}

    public function created(Attribute $attribute): void
    {
        $this->catalogCache->flushAttribute($attribute);
    }

    public function updated(Attribute $attribute): void
    {
        $this->catalogCache->flushAttribute($attribute);
    }

    public function deleted(Attribute $attribute): void
    {
        $this->catalogCache->flushAttribute($attribute);
    }
}
