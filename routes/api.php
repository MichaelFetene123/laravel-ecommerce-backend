<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\Admin\AdminProductController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CustomerOrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\WebhookController;
use App\Http\Middleware\EnsureAdmin;
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
    Route::get('/user/addresses', [AddressController::class, 'index'])->name('api.user.addresses.index');
    Route::post('/user/addresses', [AddressController::class, 'store'])->name('api.user.addresses.store');
    Route::get('/orders', [CustomerOrderController::class, 'index'])->name('api.orders.index');
    Route::get('/orders/{orderNumber}', [CustomerOrderController::class, 'show'])->name('api.orders.show');
    Route::post('/orders/{orderNumber}/pay', [CustomerOrderController::class, 'pay'])->name('api.orders.pay');
    Route::post('/orders/{orderNumber}/verify', [CustomerOrderController::class, 'verify'])->name('api.orders.verify');
    Route::post('/checkout', [CheckoutController::class, 'checkout'])->name('api.checkout');
    Route::post('/checkout/verify', [CheckoutController::class, 'verify'])->name('api.checkout.verify');

    // Admin routes
    Route::middleware([EnsureAdmin::class])->prefix('admin')->group(function () {
        Route::get('/attributes', [AdminProductController::class, 'attributes'])->name('api.admin.attributes.index');
        Route::get('/products', [AdminProductController::class, 'index'])->name('api.admin.products.index');
        Route::post('/products', [AdminProductController::class, 'store'])->name('api.admin.products.store');
        Route::get('/products/{id}', [AdminProductController::class, 'show'])->name('api.admin.products.show');
        Route::put('/products/{id}', [AdminProductController::class, 'update'])->name('api.admin.products.update');
        Route::patch('/products/{id}', [AdminProductController::class, 'update'])->name('api.admin.products.patch');
        Route::delete('/products/{id}', [AdminProductController::class, 'destroy'])->name('api.admin.products.destroy');
        Route::patch('/products/{id}/status', [AdminProductController::class, 'toggleStatus'])->name('api.admin.products.status');
        Route::patch('/products/{id}/featured', [AdminProductController::class, 'toggleFeatured'])->name('api.admin.products.featured');
        Route::patch('/products/{id}/stock', [AdminProductController::class, 'updateStock'])->name('api.admin.products.stock');
        Route::post('/products/{id}/images', [AdminProductController::class, 'uploadImages'])->name('api.admin.products.images.upload');
        Route::delete('/products/{id}/images/{imageId}', [AdminProductController::class, 'deleteImage'])->name('api.admin.products.images.destroy');
        Route::patch('/products/{id}/images/reorder', [AdminProductController::class, 'reorderImages'])->name('api.admin.products.images.reorder');
    });
});
