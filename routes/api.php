<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CustomerOrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register'])->name('api.register');
Route::post('/login', [AuthController::class, 'login'])->name('api.login');
Route::get('/categories', [CategoryController::class, 'index'])->name('api.categories.index');
Route::get('/products', [ProductController::class, 'index'])->name('api.products.index');
Route::get('/products/{slug}', [ProductController::class, 'show'])->name('api.products.show');
Route::post('/webhooks/chapa', [WebhookController::class, 'handleChapa'])->name('api.webhooks.chapa');

// Protected routes (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
    Route::get('/me', [AuthController::class, 'me'])->name('api.me');
    Route::get('/user', [AuthController::class, 'me'])->name('api.user');
    Route::get('/addresses', [AddressController::class, 'index'])->name('api.addresses.index');
    Route::post('/addresses', [AddressController::class, 'store'])->name('api.addresses.store');
    Route::get('/orders', [CustomerOrderController::class, 'index'])->name('api.orders.index');
    Route::get('/orders/{orderNumber}', [CustomerOrderController::class, 'show'])->name('api.orders.show');
    Route::post('/checkout', [CheckoutController::class, 'checkout'])->name('api.checkout');
});
