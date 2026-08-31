<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CatalogCacheService;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function __construct(
        protected CatalogCacheService $catalogCache
    ) {}

    /**
     * Display a listing of active root categories with their active children.
     */
    public function index(): JsonResponse
    {
        $categories = $this->catalogCache->getCategoryTree();

        return response()->json($categories);
    }
}
