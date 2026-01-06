<?php

use App\Http\Controllers\Api\Admin\BrandController;
use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerProfileController;
use App\Http\Controllers\Api\Admin\UserManagementController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Authentication routes, public
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// public (customer/guest)
Route::get('/products', [ProductController::class, 'index']);
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
    });
});



// Route::prefix('otp')->group(function () {
//     Route::post('/send', [OtpController::class, 'send']);
//     Route::post('/verify', [OtpController::class, 'verify']);
// });