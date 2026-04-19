<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PublicWarrantyController;
use App\Http\Controllers\Api\Admin\{
    UserManagementController,
    CategoryController,
    BrandController,
    ProductController,
    ProductImageController,
    ProductSpecController,
    ProductStockMovementController,
    SlideshowController,
    SupplierController,
    WarrantyController,
    ProductSerialController,
};

// =============================================================
// 1. PUBLIC ROUTES (Guests & Customers)
// =============================================================
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
    Route::post('/verify-admin-login', [AuthController::class, 'verifyAdminLogin']);
});

Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/{id}', [ProductController::class, 'show']);
    Route::get('/{slug}', [ProductController::class, 'showBySlug']);
    Route::get('/{product:slug}/images', [ProductImageController::class, 'index']);
});

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/brands', [BrandController::class, 'index']);
Route::get('/slideshows', [SlideshowController::class, 'index']);
// Public Route (អតិថិជនប្រើ)
Route::get('check-warranty', [PublicWarrantyController::class, 'check']);
// =============================================================
// 2. PROTECTED ROUTES (Logged-in Users)
// =============================================================
Route::middleware(['auth:sanctum', 'active_user'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // User profile
    Route::prefix('me')->group(function () {
        Route::get('/profile', [UserProfileController::class, 'show']);
        Route::put('/profile', [UserProfileController::class, 'update']);
        Route::post('/profile/image', [UserProfileController::class, 'uploadImage']);
    });

    // =============================================================
    // 3. ADMIN ONLY ROUTES (Super Admin & Admins)
    // =============================================================
    Route::middleware('role:admin')->prefix('admin')->group(function () {

        // User Management
        Route::prefix('users')->group(function () {
            Route::get('/', [UserManagementController::class, 'index']);
            Route::get('/{id}', [UserManagementController::class, 'show']);
            Route::patch('/{id}/status', [UserManagementController::class, 'updateStatus']);
            Route::patch('/{id}/role', [UserManagementController::class, 'updateRole']);
            Route::delete('/{id}', [UserManagementController::class, 'destroy']);
        });

        // Simplified Resources
        // Categories
        Route::apiResource('categories', CategoryController::class);
        Route::post('categories/{category}/upload-image', [CategoryController::class, 'uploadImage']);
        // Brands
        Route::apiResource('brands', BrandController::class);
        Route::post('brands/{brand}/upload-logo', [BrandController::class, 'uploadLogo']);

        // Suppliers
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy']);
        Route::get('suppliers/trash', [SupplierController::class, 'trash']);
        Route::post('suppliers/{id}/restore', [SupplierController::class, 'restore']);
        Route::delete('suppliers/{id}/force', [SupplierController::class, 'forceDelete']);
        Route::apiResource('suppliers', SupplierController::class)->except(['destroy']);

        // Warranties
        Route::apiResource('warranties', WarrantyController::class);

        // Products & Sub-resources
        Route::prefix('products')->group(function () {
            Route::post('/', [ProductController::class, 'store']);
            Route::get('/', [ProductController::class, 'index']);
            Route::get('/stats', [ProductController::class, 'getStats']);
            Route::get('/{id}', [ProductController::class, 'show']);
            Route::put('/{id}', [ProductController::class, 'update']);
            Route::delete('/{id}', [ProductController::class, 'destroy']); // Soft Delete

            // មុខងារបន្ថែមសម្រាប់ Soft Deletes (Trash Management)
            Route::get('/trash/all', [ProductController::class, 'trash']); // មើលផលិតផលក្នុង Trash
            Route::patch('/{id}/restore', [ProductController::class, 'restore']); // យកចេញពី Trash
            Route::delete('/{id}/force', [ProductController::class, 'forceDelete']); // លុបដាច់ពី System

            // Stock for specific product
            Route::get('/{product}/stock', [ProductStockMovementController::class, 'productStock']);

            // Images
            Route::post('/{product}/images', [ProductImageController::class, 'store']);

            // Specs
            Route::post('/{product}/specs/sync', [ProductSpecController::class, 'sync']);
            // Route::get('/{product}/specs', [ProductSpecController::class, 'index']);
        });

        // Standalone Image/Spec Actions
        Route::patch('product-images/{id}/thumbnail', [ProductImageController::class, 'setThumbnail']);
        Route::delete('product-images/{id}', [ProductImageController::class, 'destroy']);
        Route::patch('product-specs/{spec}', [ProductSpecController::class, 'update']);
        Route::delete('product-specs/{spec}', [ProductSpecController::class, 'destroy']);
    

        // Stock Movement Routes
        // Stock Movement Routes
        Route::prefix('stock-movements')->group(function () {
            Route::get('/', [ProductStockMovementController::class, 'index']);      // មើលប្រវត្តិស្តុកទាំងអស់
            Route::post('/', [ProductStockMovementController::class, 'store']);     // បញ្ចូលស្តុក (IN/OUT/ADJUST)

            // 🌟 ត្រូវដាក់ Route ពិសេស (Static Route) នៅពីលើ Route ដែលមាន {id} ជានិច្ច
            Route::get('/report', [ProductStockMovementController::class, 'stockReport']);
            Route::get('/pending-serials', [ProductStockMovementController::class, 'pendingSerials']);
            Route::post('/resolve-pending-serials', [ProductStockMovementController::class, 'resolvePendingSerials']);

            Route::get('/{id}', [ProductStockMovementController::class, 'show']);   // មើលព័ត៌មានលម្អិត ១ record

            // លុបបានតែ record ចុងក្រោយ (សម្រាប់តែ Super Admin)
            Route::delete('/{productStockMovement}', [ProductStockMovementController::class, 'destroy']);
        });
        // មុខងារបន្ថែមសម្រាប់ឆែកស្តុកតាមផលិតផលនីមួយៗ
        Route::get('products/{product}/stock', [ProductStockMovementController::class, 'productStock']);

        // Product Serials
        Route::prefix('product-serials')->group(function () {
            Route::get('/', [ProductSerialController::class, 'index']);
            Route::get('/check-warranty/{serial_number}', [ProductSerialController::class, 'checkWarranty']);
            Route::patch('/{id}/status', [ProductSerialController::class, 'updateStatus']);
            Route::put('/{id}/serial-number', [ProductSerialController::class, 'updateSerialNumber']);
        });

        // Slideshows
        Route::prefix('slideshows')->group(function () {
            Route::post('/reorder', [SlideshowController::class, 'reorder']);
            Route::get('/', [SlideshowController::class, 'index']);      // មើលបញ្ជី Slide ទាំងអស់
            Route::post('/', [SlideshowController::class, 'store']);     // បង្កើត Slide ថ្មី (Upload រូបភាព)

            // 🌟 កែប្រែពី put មក post ដើម្បីគាំទ្រការ Upload រូបភាព (Multipart Form Data)
            Route::post('/{id}', [SlideshowController::class, 'update']);

            Route::delete('/{id}', [SlideshowController::class, 'destroy']); // លុប Slide
            Route::patch('/{id}/toggle-status', [SlideshowController::class, 'toggleStatus']);
        });
    });
});





// Route::prefix('otp')->group(function () {
//     Route::post('/send', [OtpController::class, 'send']);
//     Route::post('/verify', [OtpController::class, 'verify']);
// });
