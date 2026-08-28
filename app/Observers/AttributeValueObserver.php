<?php

namespace App\Observers;

use App\Models\AttributeValue;
use App\Services\CatalogCacheService;

class AttributeValueObserver
{
    public function __construct(
        protected CatalogCacheService $catalogCache
    ) {}

    public function created(AttributeValue $value): void
    {
        $this->catalogCache->flushAttribute($value->attribute_id);
    }

    public function updated(AttributeValue $value): void
    {
        $this->catalogCache->flushAttribute($value->attribute_id);
    }

    public function deleted(AttributeValue $value): void
    {
        $this->catalogCache->flushAttribute($value->attribute_id);
    }
}
