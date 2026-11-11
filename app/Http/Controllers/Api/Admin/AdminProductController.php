<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\CatalogCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminProductController extends Controller
{
    public function __construct(
        protected CatalogCacheService $catalogCache
    ) {}

    /**
     * Get all available attributes with their values.
     */
    public function attributes(): JsonResponse
    {
        return response()->json([
            'data' => $this->catalogCache->getAllAttributesWithValues(),
        ]);
    }

    /**
     * Display a listing of products with admin filters, search, sorting, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->query('page', 1);
        $perPage = min((int) $request->query('per_page', 15), 100);

        $query = Product::query()
            ->with(['category', 'defaultVariant.attributeValues.attribute', 'variants.attributeValues.attribute', 'images']);

        // Search by title, description, or SKU
        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhereHas('variants', function ($vq) use ($search) {
                        $vq->where('sku', 'like', "%{$search}%");
                    });
            });
        }

        // Category filter
        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', (int) $categoryId);
        }

        // Status filter: 'all', 'active', 'inactive'
        $status = $request->query('status', 'all');
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        // Featured filter: 'all', 'featured', 'standard'
        $featured = $request->query('featured', 'all');
        if ($featured === 'featured') {
            $query->where('is_featured', true);
        } elseif ($featured === 'standard') {
            $query->where('is_featured', false);
        }

        // Stock filter: 'all', 'in_stock', 'low_stock', 'out_of_stock'
        $stockFilter = $request->query('stock', 'all');
        if ($stockFilter === 'out_of_stock') {
            $query->whereHas('variants', function ($vq) {
                $vq->where('is_default', true)->where('stock_quantity', '<=', 0);
            });
        } elseif ($stockFilter === 'low_stock') {
            $query->whereHas('variants', function ($vq) {
                $vq->where('is_default', true)->where('stock_quantity', '>', 0)->where('stock_quantity', '<=', 5);
            });
        } elseif ($stockFilter === 'in_stock') {
            $query->whereHas('variants', function ($vq) {
                $vq->where('is_default', true)->where('stock_quantity', '>', 0);
            });
        }

        // Sorting
        $sortBy = $request->query('sort_by', 'created_at');
        $sortOrder = strtolower((string) $request->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

        if ($sortBy === 'price') {
            $query->select('products.*')
                ->leftJoin('product_variants', function ($join) {
                    $join->on('products.id', '=', 'product_variants.product_id')
                        ->where('product_variants.is_default', true);
                })
                ->orderBy('product_variants.price', $sortOrder);
        } elseif ($sortBy === 'stock') {
            $query->select('products.*')
                ->leftJoin('product_variants', function ($join) {
                    $join->on('products.id', '=', 'product_variants.product_id')
                        ->where('product_variants.is_default', true);
                })
                ->orderBy('product_variants.stock_quantity', $sortOrder);
        } elseif (in_array($sortBy, ['title', 'slug', 'created_at', 'updated_at'])) {
            $query->orderBy("products.{$sortBy}", $sortOrder);
        } else {
            $query->orderBy('products.created_at', 'desc');
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        // Include quick aggregated KPI stats
        $stats = [
            'total' => Product::count(),
            'active' => Product::where('is_active', true)->count(),
            'draft' => Product::where('is_active', false)->count(),
            'featured' => Product::where('is_featured', true)->count(),
            'low_stock' => ProductVariant::where('is_default', true)->where('stock_quantity', '<=', 5)->count(),
        ];

        return response()->json([
            'data' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'stats' => $stats,
        ]);
    }

    /**
     * Display a single product by ID.
     */
    public function show(int $id): JsonResponse
    {
        $product = Product::with([
            'category',
            'defaultVariant',
            'variants.attributeValues.attribute',
            'variants.images',
            'images',
        ])->findOrFail($id);

        return response()->json([
            'data' => $product,
        ]);
    }

    /**
     * Store a newly created product with default variant and optional images.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:products,slug'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],

            // Variant pricing & inventory
            'sku' => ['required', 'string', 'max:100', 'unique:product_variants,sku'],
            'price' => ['required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],

            // Pre-existing image paths or URLs
            'image_paths' => ['nullable', 'array'],
            'image_paths.*' => ['string'],

            // File uploads
            'image_files' => ['nullable', 'array'],
            'image_files.*' => ['file', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:5120'],

            // Variant attributes
            'attribute_value_ids' => ['nullable', 'array'],
            'attribute_value_ids.*' => ['integer', 'exists:attribute_values,id'],
        ]);

        $product = DB::transaction(function () use ($validated, $request) {
            // Generate slug if omitted
            $slug = ! empty($validated['slug'])
                ? Str::slug($validated['slug'])
                : Str::slug($validated['title']);

            // Ensure unique slug
            $originalSlug = $slug;
            $count = 1;
            while (Product::where('slug', $slug)->exists()) {
                $slug = "{$originalSlug}-{$count}";
                $count++;
            }

            $product = Product::create([
                'category_id' => $validated['category_id'],
                'title' => $validated['title'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'is_active' => $request->boolean('is_active', true),
                'is_featured' => $request->boolean('is_featured', false),
                'meta_title' => $validated['meta_title'] ?? null,
                'meta_description' => $validated['meta_description'] ?? null,
            ]);

            // Create default variant
            $variant = ProductVariant::create([
                'product_id' => $product->id,
                'sku' => strtoupper(trim($validated['sku'])),
                'price' => $validated['price'],
                'compare_at_price' => $validated['compare_at_price'] ?? null,
                'stock_quantity' => $validated['stock_quantity'],
                'weight' => $validated['weight'] ?? null,
                'is_default' => true,
            ]);

            if (! empty($validated['attribute_value_ids'])) {
                $variant->attributeValues()->sync($validated['attribute_value_ids']);
            }

            $sortOrder = 0;

            // Handle pre-existing image paths
            if (! empty($validated['image_paths'])) {
                foreach ($validated['image_paths'] as $path) {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'product_variant_id' => null,
                        'path' => $path,
                        'sort_order' => $sortOrder++,
                    ]);
                }
            }

            // Handle uploaded files
            if ($request->hasFile('image_files')) {
                foreach ($request->file('image_files') as $file) {
                    $storedPath = $file->store('products', 'public');
                    ProductImage::create([
                        'product_id' => $product->id,
                        'product_variant_id' => null,
                        'path' => $storedPath,
                        'sort_order' => $sortOrder++,
                    ]);
                }
            }

            return $product;
        });

        $product->load(['category', 'defaultVariant.attributeValues.attribute', 'variants.attributeValues.attribute', 'images']);

        return response()->json([
            'message' => 'Product created successfully.',
            'data' => $product,
        ], 201);
    }

    /**
     * Update an existing product and its default variant.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::with(['defaultVariant', 'images'])->findOrFail($id);
        /** @var ProductVariant|null $defaultVariant */
        $defaultVariant = $product->defaultVariant ?? $product->variants()->first();

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($product->id)],
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],

            // Variant pricing & inventory
            'sku' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('product_variants', 'sku')->ignore($defaultVariant?->id)],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['sometimes', 'required', 'integer', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],

            // Variant attributes
            'attribute_value_ids' => ['nullable', 'array'],
            'attribute_value_ids.*' => ['integer', 'exists:attribute_values,id'],
        ]);

        DB::transaction(function () use ($product, $defaultVariant, $validated, $request) {
            $productData = [];
            foreach (['title', 'category_id', 'description', 'meta_title', 'meta_description'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $productData[$field] = $validated[$field];
                }
            }

            if (isset($validated['slug'])) {
                $productData['slug'] = Str::slug($validated['slug']);
            }

            if ($request->has('is_active')) {
                $productData['is_active'] = $request->boolean('is_active');
            }

            if ($request->has('is_featured')) {
                $productData['is_featured'] = $request->boolean('is_featured');
            }

            if (! empty($productData)) {
                $product->update($productData);
            }

            // Update default variant
            $variantData = [];
            if (isset($validated['sku'])) {
                $variantData['sku'] = strtoupper(trim($validated['sku']));
            }
            if (isset($validated['price'])) {
                $variantData['price'] = $validated['price'];
            }
            if (array_key_exists('compare_at_price', $validated)) {
                $variantData['compare_at_price'] = $validated['compare_at_price'];
            }
            if (isset($validated['stock_quantity'])) {
                $variantData['stock_quantity'] = (int) $validated['stock_quantity'];
            }
            if (array_key_exists('weight', $validated)) {
                $variantData['weight'] = $validated['weight'];
            }

            if (! empty($variantData)) {
                if ($defaultVariant) {
                    $defaultVariant->update($variantData);
                } else {
                    $defaultVariant = $product->variants()->create(array_merge($variantData, ['is_default' => true]));
                }
            }

            if ($defaultVariant && array_key_exists('attribute_value_ids', $validated)) {
                $defaultVariant->attributeValues()->sync($validated['attribute_value_ids'] ?? []);
            }
        });

        $product->load(['category', 'defaultVariant.attributeValues.attribute', 'variants.attributeValues.attribute', 'images']);

        return response()->json([
            'message' => 'Product updated successfully.',
            'data' => $product,
        ]);
    }

    /**
     * Delete the specified product.
     */
    public function destroy(int $id): JsonResponse
    {
        $product = Product::with('images')->findOrFail($id);

        // Remove linked image files from storage disk
        foreach ($product->images as $image) {
            if ($image->path && ! str_starts_with($image->path, 'http')) {
                Storage::disk('public')->delete($image->path);
            }
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }

    /**
     * Toggle active status.
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->is_active = ! $product->is_active;
        $product->save();

        return response()->json([
            'message' => $product->is_active ? 'Product published.' : 'Product hidden/drafted.',
            'data' => [
                'id' => $product->id,
                'is_active' => $product->is_active,
            ],
        ]);
    }

    /**
     * Toggle featured status.
     */
    public function toggleFeatured(int $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->is_featured = ! $product->is_featured;
        $product->save();

        return response()->json([
            'message' => $product->is_featured ? 'Product marked as featured.' : 'Product unfeatured.',
            'data' => [
                'id' => $product->id,
                'is_featured' => $product->is_featured,
            ],
        ]);
    }

    /**
     * Fast update default variant stock.
     */
    public function updateStock(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'stock_quantity' => ['required', 'integer', 'min:0'],
        ]);

        $product = Product::with('defaultVariant')->findOrFail($id);
        /** @var ProductVariant|null $variant */
        $variant = $product->defaultVariant ?? $product->variants()->first();

        if (! $variant) {
            return response()->json(['message' => 'No variant found to update stock.'], 404);
        }

        $variant->update([
            'stock_quantity' => (int) $request->stock_quantity,
        ]);

        return response()->json([
            'message' => 'Stock updated successfully.',
            'data' => [
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'stock_quantity' => $variant->stock_quantity,
            ],
        ]);
    }

    /**
     * Upload one or more images for a product.
     */
    public function uploadImages(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'images' => ['required', 'array'],
            'images.*' => ['file', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:5120'],
        ]);

        $product = Product::findOrFail($id);

        $lastSortOrder = (int) $product->images()->max('sort_order');
        $createdImages = [];

        foreach ($request->file('images') as $file) {
            $lastSortOrder++;
            $path = $file->store('products', 'public');
            $image = ProductImage::create([
                'product_id' => $product->id,
                'product_variant_id' => null,
                'path' => $path,
                'sort_order' => $lastSortOrder,
            ]);
            $createdImages[] = $image;
        }

        return response()->json([
            'message' => 'Images uploaded successfully.',
            'data' => $createdImages,
        ], 201);
    }

    /**
     * Delete a product image.
     */
    public function deleteImage(int $productId, int $imageId): JsonResponse
    {
        $image = ProductImage::where('product_id', $productId)->findOrFail($imageId);

        if ($image->path && ! str_starts_with($image->path, 'http')) {
            Storage::disk('public')->delete($image->path);
        }

        $image->delete();

        return response()->json([
            'message' => 'Image deleted successfully.',
        ]);
    }

    /**
     * Reorder product images.
     */
    public function reorderImages(Request $request, int $productId): JsonResponse
    {
        $request->validate([
            'image_ids' => ['required', 'array'],
            'image_ids.*' => ['integer', 'exists:product_images,id'],
        ]);

        foreach ($request->image_ids as $index => $imageId) {
            ProductImage::where('product_id', $productId)
                ->where('id', $imageId)
                ->update(['sort_order' => $index]);
        }

        return response()->json([
            'message' => 'Images reordered successfully.',
        ]);
    }
}

