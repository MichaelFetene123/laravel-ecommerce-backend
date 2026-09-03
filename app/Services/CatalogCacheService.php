<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class CatalogCacheService
{
    public const int DEFAULT_CATEGORY_TTL = 86400; // 24 hours

    public const int DEFAULT_PRODUCT_TTL = 3600;   // 1 hour

    public const int DEFAULT_ATTRIBUTE_TTL = 86400; // 24 hours

    public const int DEFAULT_VARIANT_TTL = 3600;   // 1 hour

    public const string TAG_CATALOG = 'catalog';

    public const string TAG_CATEGORIES = 'categories';

    public const string TAG_PRODUCTS = 'products';

    public const string TAG_ATTRIBUTES = 'attributes';

    public const string TAG_VARIANTS = 'variants';

    /**
     * Get root category tree with nested children.
     */
    public function getCategoryTree(): Collection
    {
        $key = 'catalog:categories:tree';

        return $this->remember(
            key: $key,
            tags: [self::TAG_CATALOG, self::TAG_CATEGORIES],
            ttl: self::DEFAULT_CATEGORY_TTL,
            callback: fn () => Category::query()
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->with(['children' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
                ->orderBy('sort_order')
                ->get()
        );
    }

    /**
     * Get all active categories.
     */
    public function getAllCategories(): Collection
    {
        $key = 'catalog:categories:all';

        return $this->remember(
            key: $key,
            tags: [self::TAG_CATALOG, self::TAG_CATEGORIES],
            ttl: self::DEFAULT_CATEGORY_TTL,
            callback: fn () => Category::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
        );
    }

    /**
     * Get category by ID.
     */
    public function getCategoryById(int $id): ?Category
    {
        $key = "catalog:categories:id:{$id}";

        return $this->remember(
            key: $key,
            tags: [self::TAG_CATALOG, self::TAG_CATEGORIES],
            ttl: self::DEFAULT_CATEGORY_TTL,
            callback: fn () => Category::query()
                ->with(['parent', 'children' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
                ->find($id)
        );
    }

    /**
     * Get category by Slug.
     */
    public function getCategoryBySlug(string $slug): ?Category
    {
        $key = "catalog:categories:slug:{$slug}";

        return $this->remember(
            key: $key,
            tags: [self::TAG_CATALOG, self::TAG_CATEGORIES],
            ttl: self::DEFAULT_CATEGORY_TTL,
            callback: fn () => Category::query()
                ->with(['parent', 'children' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')])
                ->where('slug', $slug)
                ->first()
        );
    }

    /**
     * Get paginated products with optional filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getProducts(array $filters = [], int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        $filterHash = md5(serialize($filters));
        $key = "catalog:products:page:{$page}:limit:{$perPage}:filter:{$filterHash}";

        return $this->remember(
            key: $key,
            tags: [self::TAG_CATALOG, self::TAG_PRODUCTS],
            ttl: self::DEFAULT_PRODUCT_TTL,
            callback: function () use ($filters, $page, $perPage) {
                $query = Product::query()
                    ->where('is_active', true)
                    ->with(['category', 'defaultVariant', 'variants', 'images']);

                if (! empty($filters['category_id'])) {
                    $query->where('category_id', $filters['category_id']);
                }

                if (! empty($filters['search'])) {
                    $search = (string) $filters['search'];
                    $query->where('title', 'like', "%{$search}%");
                }

                return $query->paginate(perPage: $perPage, page: $page);
            }
        );
    }

    /**
     * Get featured active products.
     */
    public function getFeaturedProducts(int $limit = 10): Collection
    {
        $key = "catalog:products:featured:limit:{$limit}";

        return $this->remember(
            key: $key,
            tags: [self::TAG_CATALOG, self::TAG_PRODUCTS],
            ttl: self::DEFAULT_PRODUCT_TTL,
            callback: fn () => Product::query()
                ->where('is_active', true)
                ->with(['category', 'defaultVariant', 'variants', 'images'])
                ->limit($limit)
                ->get()
        );
    }

    /**
     * Get product by ID with full relationships.
     */
    public function getProductById(int $id): ?Product
    {
        $key = "catalog:products:id:{$id}";

        return $this->remember(
            key: $key,
            tags: [self::TAG_CATALOG, self::TAG_PRODUCTS],
            ttl: self::DEFAULT_PRODUCT_TTL,
            callback: fn () => Product::query()
                ->with([
                    'category',
                    'images',
                    'defaultVariant.attributeValues.attribute',
                    'variants.attributeValues.attribute',
                    'variants.images',
                ])
                ->find($id)
        );
    }

    /**
     * Get product by slug with full relationships.
     */
    public function getProductBySlug(string $slug): ?Product
    {
        $key = "catalog:products:slug:{$slug}";

        return $this->remember(
            key: $key,
            tags: [self::TAG_CATALOG, self::TAG_PRODUCTS],
            ttl: self::DEFAULT_PRODUCT_TTL,
            callback: fn () => Product::query()
                ->with([
                    'category',
                    'images',
                    'defaultVariant.attributeValues.attribute',
                    'variants.attributeValues.attribute',
                    'variants.images',
                ])
                ->where('slug', $slug)
                ->first()
        );
    }

    /**
     * Get all attributes with their values.
     */
    public function getAllAttributesWithValues(): Collection
    {
        $key = 'catalog:attributes:all';

        return $this->remember(
            key: $key,
            tags: [self::TAG_CATALOG, self::TAG_ATTRIBUTES],
            ttl: self::DEFAULT_ATTRIBUTE_TTL,
            callback: fn () => Attribute::query()->with('values')->get()
        );
    }

    /**
     * Get attribute by ID with values.
     */
    public function getAttributeById(int $id): ?Attribute
    {
        $key = "catalog:attributes:id:{$id}";

        return $this->remember(
            key: $key,
            tags: [self::TAG_CATALOG, self::TAG_ATTRIBUTES],
            ttl: self::DEFAULT_ATTRIBUTE_TTL,
            callback: fn () => Attribute::query()->with('values')->find($id)
        );
    }

    /**
     * Get attribute by slug with values.
     */
    public function getAttributeBySlug(string $slug): ?Attribute
    {
        $key = "catalog:attributes:slug:{$slug}";

        return $this->remember(
            key: $key,
            tags: [self::TAG_CATALOG, self::TAG_ATTRIBUTES],
            ttl: self::DEFAULT_ATTRIBUTE_TTL,
            callback: fn () => Attribute::query()->with('values')->where('slug', $slug)->first()
        );
    }

    /**
     * Get variants for a specific product.
     */
    public function getVariantsByProductId(int $productId): Collection
    {
        $key = "catalog:variants:product:{$productId}";

        return $this->remember(
            key: $key,
            tags: [self::TAG_CATALOG, self::TAG_VARIANTS, self::TAG_PRODUCTS],
            ttl: self::DEFAULT_VARIANT_TTL,
            callback: fn () => ProductVariant::query()
                ->where('product_id', $productId)
                ->with(['attributeValues.attribute', 'images'])
                ->get()
        );
    }

    /**
     * Get single variant by ID.
     */
    public function getVariantById(int $id): ?ProductVariant
    {
        $key = "catalog:variants:id:{$id}";

        return $this->remember(
            key: $key,
            tags: [self::TAG_CATALOG, self::TAG_VARIANTS],
            ttl: self::DEFAULT_VARIANT_TTL,
            callback: fn () => ProductVariant::query()
                ->with(['product', 'attributeValues.attribute', 'images'])
                ->find($id)
        );
    }

    /**
     * Flush all catalog-related caches.
     */
    public function flushAll(): void
    {
        if ($this->supportsTags()) {
            Cache::tags([self::TAG_CATALOG])->flush();
        } else {
            Cache::flush();
        }
    }

    /**
     * Flush category-related caches.
     */
    public function flushCategories(): void
    {
        if ($this->supportsTags()) {
            Cache::tags([self::TAG_CATEGORIES])->flush();
        } else {
            Cache::forget('catalog:categories:tree');
            Cache::forget('catalog:categories:all');
        }
    }

    /**
     * Flush specific category cache.
     */
    public function flushCategory(Category|int $category): void
    {
        $this->flushCategories();

        if ($category instanceof Category) {
            Cache::forget("catalog:categories:id:{$category->id}");
            Cache::forget("catalog:categories:slug:{$category->slug}");
        } elseif (is_int($category)) {
            Cache::forget("catalog:categories:id:{$category}");
        }
    }

    /**
     * Flush product-related caches.
     */
    public function flushProducts(): void
    {
        if ($this->supportsTags()) {
            Cache::tags([self::TAG_PRODUCTS])->flush();
        }
    }

    /**
     * Flush specific product cache.
     */
    public function flushProduct(Product|int $product): void
    {
        $this->flushProducts();

        if ($product instanceof Product) {
            Cache::forget("catalog:products:id:{$product->id}");
            Cache::forget("catalog:products:slug:{$product->slug}");
            Cache::forget("catalog:variants:product:{$product->id}");
        } elseif (is_int($product)) {
            Cache::forget("catalog:products:id:{$product}");
            Cache::forget("catalog:variants:product:{$product}");
        }
    }

    /**
     * Flush attribute-related caches.
     */
    public function flushAttributes(): void
    {
        if ($this->supportsTags()) {
            Cache::tags([self::TAG_ATTRIBUTES])->flush();
        } else {
            Cache::forget('catalog:attributes:all');
        }
    }

    /**
     * Flush specific attribute cache.
     */
    public function flushAttribute(Attribute|int $attribute): void
    {
        $this->flushAttributes();

        if ($attribute instanceof Attribute) {
            Cache::forget("catalog:attributes:id:{$attribute->id}");
            Cache::forget("catalog:attributes:slug:{$attribute->slug}");
        } elseif (is_int($attribute)) {
            Cache::forget("catalog:attributes:id:{$attribute}");
        }
    }

    /**
     * Flush variant-related caches.
     */
    public function flushVariants(): void
    {
        if ($this->supportsTags()) {
            Cache::tags([self::TAG_VARIANTS])->flush();
        }
    }

    /**
     * Flush specific variant cache.
     */
    public function flushVariant(ProductVariant|int $variant): void
    {
        $this->flushVariants();
        $this->flushProducts();

        if ($variant instanceof ProductVariant) {
            Cache::forget("catalog:variants:id:{$variant->id}");
            Cache::forget("catalog:variants:product:{$variant->product_id}");
        } elseif (is_int($variant)) {
            Cache::forget("catalog:variants:id:{$variant}");
        }
    }

    /**
     * Helper to remember values using tags if supported.
     *
     * @template T
     *
     * @param  array<int, string>  $tags
     * @param  \Closure(): T  $callback
     * @return T
     */
    protected function remember(string $key, array $tags, int $ttl, \Closure $callback)
    {
        if ($this->supportsTags()) {
            return Cache::tags($tags)->remember($key, $ttl, $callback);
        }

        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Check if the current cache driver supports tagging.
     */
    public function supportsTags(): bool
    {
        return Cache::supportsTags();
    }
}
