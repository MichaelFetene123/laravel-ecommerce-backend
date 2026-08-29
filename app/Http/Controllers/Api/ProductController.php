<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    /**
     * Display a paginated listing of active products with default variant and images.
     */
    public function index(): JsonResponse
    {
        $products = Product::where('is_active', true)
            ->with(['defaultVariant', 'images'])
            ->paginate(20);

        return response()->json($products);
    }

    /**
     * Display the specified active product by slug.
     */
    public function show(string $slug): JsonResponse
    {
        $product = Product::where('slug', $slug)
            ->where('is_active', true)
            ->with([
                'variants.attributeValues.attribute',
                'images',
                'category',
            ])
            ->firstOrFail();

        return response()->json($product);
    }
}
