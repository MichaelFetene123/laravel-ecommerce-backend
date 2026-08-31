<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CatalogCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        protected CatalogCacheService $catalogCache
    ) {}

    /**
     * Display a paginated listing of active products with default variant and images.
     */
    public function index(Request $request): JsonResponse
    {
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 20);
        $filters = $request->only(['category_id', 'search']);

        $products = $this->catalogCache->getProducts(
            filters: $filters,
            page: $page,
            perPage: $perPage,
        );

        return response()->json($products);
    }

    /**
     * Display the specified active product by slug.
     */
    public function show(string $slug): JsonResponse
    {
        $product = $this->catalogCache->getProductBySlug($slug);

        if (! $product || ! $product->is_active) {
            abort(404);
        }

        return response()->json($product);
    }
}
