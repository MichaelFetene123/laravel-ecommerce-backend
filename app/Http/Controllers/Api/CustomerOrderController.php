<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerOrderController extends Controller
{
    /**
     * Display a paginated listing of the authenticated user's orders.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $orders = $user->orders()
            ->with(['items', 'payments'])
            ->latest()
            ->paginate(10);

        return response()->json($orders);
    }

    /**
     * Display the specified order for the authenticated user by order_number.
     */
    public function show(Request $request, string $orderNumber): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $order = $user->orders()
            ->where('order_number', $orderNumber)
            ->with(['items', 'billingAddress', 'payments'])
            ->firstOrFail();

        return response()->json($order);
    }
}
