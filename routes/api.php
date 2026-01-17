<?php

use App\Http\Controllers\Api\Admin\BrandController;
use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\Admin\ProductImageController;
use App\Http\Controllers\Api\Admin\ProductSeriesController;
use App\Http\Controllers\Api\Admin\ProductSpecController;
use App\Http\Controllers\Api\Admin\ProductStockMovementController;
use App\Http\Controllers\Api\Admin\SlideshowController;
use App\Http\Controllers\Api\Admin\SupplierController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerProfileController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\WarrantyController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Authentication routes, public
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// public (customer/guest)
Route::get('/products', [ProductController::class, 'index']);
//Route::get('/products', [ProductController::class, 'show']);
Route::get('/products/{slug}', [ProductController::class, 'showBySlug']);

// Authentication routes, protected
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Customer-only route
    Route::middleware('role:customer')->group(function () {
        Route::get('/customer/profile', [CustomerProfileController::class, 'show']);
        Route::put('/customer/profile', [CustomerProfileController::class, 'update']);
        Route::post('/customer/profile/image', [CustomerProfileController::class, 'uploadImage']);
    });

    // Admin-only route
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/users', [UserManagementController::class, 'index']);
        Route::get('/users/{id}', [UserManagementController::class, 'show']);
        Route::patch('/users/{id}/status', [UserManagementController::class, 'updateStatus']);
        Route::patch('/users/{id}/role', [UserManagementController::class, 'updateRole']);
        Route::delete('/users/{id}', [UserManagementController::class, 'destroy']);

        // Category routes
        Route::apiResource('categories', CategoryController::class);

        // Brand routes
        Route::get('/brands', [BrandController::class, 'index']);
        Route::post('/brands', [BrandController::class, 'store']);
        Route::get('/brands/{id}', [BrandController::class, 'show']);
        Route::put('/brands/{id}', [BrandController::class, 'update']);
        Route::delete('/brands/{id}', [BrandController::class, 'destroy']);

        // Product routes
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);

        // Product Image routes
        Route::prefix('products')->group(function () {
            Route::get('{product}/images', [ProductImageController::class, 'index']);
            Route::post('{product}/images', [ProductImageController::class, 'store']);
        });
        Route::patch('product-images/{id}/thumbnail', [ProductImageController::class, 'setThumbnail']);
        Route::delete('product-images/{id}', [ProductImageController::class, 'destroy']);

        // Product Spec routes
        Route::prefix('products')->group(function () {
            Route::get('{product}/specs', [ProductSpecController::class, 'index']);
            Route::post('{product}/specs', [ProductSpecController::class, 'store']);
        });
        Route::patch('product-specs/{spec}', [ProductSpecController::class, 'update']);
        Route::delete('product-specs/{spec}', [ProductSpecController::class, 'destroy']);

        // Warrenty Route
        Route::get('warranties', [WarrantyController::class, 'index']);
        Route::post('warranties', [WarrantyController::class, 'store']);
        Route::get('warranties/{warranty}', [WarrantyController::class, 'show']);
        Route::patch('warranties/{warranty}', [WarrantyController::class, 'update']);
        Route::delete('warranties/{warranty}', [WarrantyController::class, 'destroy']);

        // Supplier routes
        Route::apiResource('suppliers', SupplierController::class);

        // Product Stock Movement routes
        Route::get('stock-movements', [ProductStockMovementController::class, 'index']);
        Route::post('stock-movements', [ProductStockMovementController::class, 'store']);
        Route::get('stock-movements/{productStockMovement}', [ProductStockMovementController::class, 'show']);
        Route::delete('stock-movements/{productStockMovement}', [ProductStockMovementController::class, 'destroy']);
        // 📦 Get current stock for product
        Route::get('products/{product}/stock', [ProductStockMovementController::class, 'productStock']);

        // Product Series routes
        Route::prefix('product-series')->group(function () {
            Route::get('/', [ProductSeriesController::class, 'index']);
            Route::post('/', [ProductSeriesController::class, 'store']);
            Route::get('slug/{slug}', [ProductSeriesController::class, 'showBySlug']);
            Route::patch('{productSeries}', [ProductSeriesController::class, 'update']);
            Route::delete('{productSeries}', [ProductSeriesController::class, 'destroy']);
        });

        // Slideshow routes
        Route::prefix('slideshows')->group(function () {
            Route::get('/', [SlideshowController::class, 'index']);
            Route::get('/active', [SlideshowController::class, 'active']);
            Route::get('{slideshow}', [SlideshowController::class, 'show']);

            Route::post('/', [SlideshowController::class, 'store']);
            Route::patch('{slideshow}', [SlideshowController::class, 'update']);
            Route::patch('reorder/all', [SlideshowController::class, 'reorder']);
            Route::patch('{slideshow}/toggle', [SlideshowController::class, 'toggle']);

            Route::delete('{slideshow}', [SlideshowController::class, 'destroy']);
        });
    });
});





// Route::prefix('otp')->group(function () {
//     Route::post('/send', [OtpController::class, 'send']);
//     Route::post('/verify', [OtpController::class, 'verify']);
// });
