<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\Admin\{UserManagementController, CategoryController, BrandController, ProductController, ProductImageController, ProductSpecController, ProductStockMovementController, SlideshowController, SupplierController, WarrantyController, ProductSerialController, SettingController};
use App\Http\Controllers\Api\Admin\AIGeneratorController;
use App\Http\Controllers\Api\Admin\ContactController as AdminContactController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\OrderController;
use App\Http\Controllers\Api\Admin\PosController;
use App\Http\Controllers\Api\Admin\ReportController;
use App\Http\Controllers\Api\Admin\RoleController;
use App\Http\Controllers\Api\Admin\ShippingZoneController;
use App\Http\Controllers\Api\Admin\SystemController;
use App\Http\Controllers\Api\Admin\WarrantyCheckController;
use App\Http\Controllers\Api\Auth\GoogleAuthController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\ContactController as CustomerContactController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController as PublicOrderController;
use App\Http\Controllers\Api\PublicWarrantyController;
use App\Http\Controllers\Api\UserProfileController;
use Illuminate\Support\Facades\Route;

// =============================================================
// 1. PUBLIC ROUTES (Guests & Customers - View & Search Products)
// =============================================================
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
    Route::post('/verify-admin-login', [AuthController::class, 'verifyAdminLogin']);
});

Route::prefix('auth/google')->group(function () {
    Route::get('/redirect', [GoogleAuthController::class, 'redirect']);
    Route::get('/callback', [GoogleAuthController::class, 'callback']);
});

Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/force-change-password', [AuthController::class, 'forceChangePassword']);

Route::prefix('admin')->group(function () {
    Route::post('/forgot-password', [AuthController::class, 'adminForgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'adminResetPassword']);
});

// Storefront & Public Data
Route::prefix('products')->group(function () {
    Route::get('/suggestions', [ProductController::class, 'suggestions']);
    Route::get('/', [ProductController::class, 'storefrontIndex']);
    Route::get('/{slug}/related', [ProductController::class, 'getRelatedProducts']);
    Route::get('/{slug}', [ProductController::class, 'showBySlug']);
    Route::get('/{product:slug}/images', [ProductImageController::class, 'index']);
});

Route::get('/categories', [CategoryController::class, 'storefrontIndex']);
Route::get('/brands', [BrandController::class, 'storefrontIndex']);
Route::get('/home', [HomeController::class, 'index']);
Route::post('/contacts', [CustomerContactController::class, 'store']);
Route::get('/warranty-check', [PublicWarrantyController::class, 'check']);


// =============================================================
// 2. PROTECTED ROUTES (All Logged-in Users)
// =============================================================
Route::middleware(['auth:sanctum', 'active_user'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Profile & Notifications
    Route::prefix('me')->group(function () {
        Route::get('/profile', [UserProfileController::class, 'show']);
        Route::put('/profile', [UserProfileController::class, 'update']);
        Route::post('/profile/image', [UserProfileController::class, 'uploadImage']);
    });
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::patch('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
    });

    // Customer Actions (Add to Cart, Checkout, Add Favorite)
    Route::prefix('cart')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/add', [CartController::class, 'addItem']);
        Route::put('/item/{itemId}', [CartController::class, 'updateItem']);
        Route::delete('/item/{itemId}', [CartController::class, 'removeItem']);
        Route::delete('/clear', [CartController::class, 'clearCart']);
    });
    Route::prefix('favorites')->group(function () {
        Route::get('/', [FavoriteController::class, 'index']);
        Route::post('/toggle', [FavoriteController::class, 'toggle']);
    });
    Route::prefix('orders')->group(function () {
        Route::get('/', [PublicOrderController::class, 'index']);
        Route::post('/checkout', [PublicOrderController::class, 'store']);
        Route::put('/{id}/address', [PublicOrderController::class, 'updateOrderAddress']);
        Route::get('/{id}', [PublicOrderController::class, 'show']);
        Route::post('/{id}/upload-receipt', [PublicOrderController::class, 'uploadReceipt']);
    });
    Route::get( '/internal/orders/{id}/khqr-payment', [OrderController::class, 'paymentForKHQR'] );
    
    Route::prefix('addresses')->group(function () {
        Route::get('/', [AddressController::class, 'index']);
        Route::post('/', [AddressController::class, 'store']);
        Route::put('/{id}', [AddressController::class, 'update']);
        
        Route::patch('/{id}/set-default', [AddressController::class, 'setAsDefault']);
        Route::delete('/{id}', [AddressController::class, 'destroy']);
    });
    Route::get('/shipping-zones', [ShippingZoneController::class, 'index']);


    // =============================================================
    // 3. STAFF ROUTES (Manage Orders & Make Invoices)
    // =============================================================
    // 🌟 អនុញ្ញាតឱ្យ admin, super_admin ព្រមទាំង sale_staff ប្រើប្រាស់
    Route::middleware('role:admin|super_admin|sale_staff')->prefix('admin')->group(function () {
        Route::post('/toggle-2fa', [AuthController::class, 'toggle2FA']);
        Route::get('/dashboard', [DashboardController::class, 'index']); // អាចឱ្យ Sale មើលទិន្នន័យទូទៅ

        // Manage Order & Make Invoice
        Route::prefix('orders')->group(function () {
            Route::get('/', [OrderController::class, 'index']);
            Route::get('/kpis', [OrderController::class, 'getKpis']);
            Route::get('/{id}', [OrderController::class, 'show']);
            Route::patch('/{id}/status', [OrderController::class, 'updateStatus']);
            Route::put('/{id}/payment-status', [OrderController::class, 'updatePaymentStatus']);
            Route::post('/{id}/fulfill-serials', [OrderController::class, 'fulfillOrderSerials']);
            Route::post('/{id}/reject-receipt', [OrderController::class, 'rejectPaymentReceipt']);
        });

        // POS (Make Invoice directly)
        Route::prefix('pos')->group(function () {
            Route::get('/categories', [PosController::class, 'getCategories']);
            Route::get('/brands', [PosController::class, 'getBrands']);
            Route::get('/products/search', [PosController::class, 'searchProducts']);
            Route::get('/users/search', [PosController::class, 'searchUsers']);
            Route::post('/orders', [PosController::class, 'storeOrder']);
            Route::post('/check-serial', [PosController::class, 'checkSerial']);
        });

        // View Report
        Route::get('/reports', [ReportController::class, 'index']);
        Route::get('/reports/export/excel', [ReportController::class, 'exportExcel']);
        Route::get('/reports/export/pdf', [ReportController::class, 'exportPdf']);

        // Sale Staff ត្រូវការមើលផលិតផល និងឆែក Warranty
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/stats', [ProductController::class, 'getStats']);
        Route::get('/products/{id}', [ProductController::class, 'show']);

        Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
        Route::apiResource('brands', BrandController::class)->only(['index', 'show']);

        Route::prefix('product-serials')->group(function () {
            Route::get('/', [ProductSerialController::class, 'index']);
            Route::get('/check-warranty/{serial_number}', [ProductSerialController::class, 'checkWarranty']);
        });

        // Contacts សម្រាប់ឆ្លើយតបអតិថិជន
        Route::prefix('contacts')->group(function () {
            Route::get('/', [AdminContactController::class, 'index']);
            Route::get('/{id}', [AdminContactController::class, 'show']);
            Route::patch('/{id}/status', [AdminContactController::class, 'updateStatus']);
        });
    });


    // =============================================================
    // 4. ADMIN & SUPER ADMIN ONLY ROUTES (Manage System)
    // =============================================================
    // 🌟 អនុញ្ញាតតែ admin និង super_admin ប៉ុណ្ណោះ
    Route::middleware('role:admin|super_admin')->prefix('admin')->group(function () {

        // Manage User
        Route::prefix('users')->group(function () {
            Route::post('/', [UserManagementController::class, 'store']);
            Route::get('/', [UserManagementController::class, 'index']);
            Route::get('/kpis', [UserManagementController::class, 'getKpis']);
            Route::get('/{id}', [UserManagementController::class, 'show']);
            Route::patch('/{id}/status', [UserManagementController::class, 'updateStatus']);
            Route::patch('/{id}/role', [UserManagementController::class, 'updateRole']);
            Route::delete('/{id}', [UserManagementController::class, 'destroy']);
        });

        Route::get('/permissions', [RoleController::class, 'getPermissions']);
        Route::apiResource('roles', RoleController::class);


        // Manage Product
        Route::put('/categories/reorder', [CategoryController::class, 'reorder']);
        Route::apiResource('categories', CategoryController::class)->except(['index', 'show']);
        Route::post('categories/{category}/upload-image', [CategoryController::class, 'uploadImage']);

        Route::put('/brands/reorder', [BrandController::class, 'reorder']);
        Route::apiResource('brands', BrandController::class)->except(['index', 'show']);
        Route::post('brands/{brand}/upload-logo', [BrandController::class, 'uploadLogo']);

        Route::prefix('products')->group(function () {
            Route::post('/', [ProductController::class, 'store']);
            Route::put('/reorder', [ProductController::class, 'reorder']);
            Route::put('/{id}', [ProductController::class, 'update']);
            Route::delete('/{id}', [ProductController::class, 'destroy']);

            Route::get('/trash/all', [ProductController::class, 'trash']);
            Route::patch('/{id}/restore', [ProductController::class, 'restore']);
            Route::delete('/{id}/force', [ProductController::class, 'forceDelete']);

            Route::post('/{product}/images', [ProductImageController::class, 'store']);
            Route::post('/{product}/specs/sync', [ProductSpecController::class, 'sync']);
        });
        Route::post('/ai/generate-description', [AIGeneratorController::class, 'generateDescription']);
        Route::patch('product-images/{id}/thumbnail', [ProductImageController::class, 'setThumbnail']);
        Route::delete('product-images/{id}', [ProductImageController::class, 'destroy']);
        Route::patch('product-specs/{spec}', [ProductSpecController::class, 'update']);
        Route::delete('product-specs/{spec}', [ProductSpecController::class, 'destroy']);

        // Manage Inventory
        Route::prefix('stock-movements')->group(function () {
            Route::get('/', [ProductStockMovementController::class, 'index']);
            Route::post('/', [ProductStockMovementController::class, 'store']);
            Route::get('/report', [ProductStockMovementController::class, 'stockReport']);
            Route::get('/pending-serials', [ProductStockMovementController::class, 'pendingSerials']);
            Route::post('/resolve-pending-serials', [ProductStockMovementController::class, 'resolvePendingSerials']);
            Route::get('/{id}', [ProductStockMovementController::class, 'show']);
            Route::delete('/{productStockMovement}', [ProductStockMovementController::class, 'destroy']);
        });
        Route::get('products/{product}/stock', [ProductStockMovementController::class, 'productStock']);
        Route::patch('product-serials/{id}/status', [ProductSerialController::class, 'updateStatus']);
        Route::put('product-serials/{id}/serial-number', [ProductSerialController::class, 'updateSerialNumber']);



        // Other Configurations (Settings, Warranties, Slideshows, Suppliers)
        Route::apiResource('suppliers', SupplierController::class)->except(['destroy']);
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy']);
        Route::get('suppliers/trash', [SupplierController::class, 'trash']);
        Route::post('suppliers/{id}/restore', [SupplierController::class, 'restore']);
        Route::delete('suppliers/{id}/force', [SupplierController::class, 'forceDelete']);

        Route::apiResource('warranties', WarrantyController::class);
        Route::get('/warranty-check', [WarrantyCheckController::class, 'check']);

        Route::prefix('slideshows')->group(function () {
            Route::post('/reorder', [SlideshowController::class, 'reorder']);
            Route::get('/', [SlideshowController::class, 'index']);
            Route::post('/', [SlideshowController::class, 'store']);
            Route::post('/{id}', [SlideshowController::class, 'update']);
            Route::delete('/{id}', [SlideshowController::class, 'destroy']);
            Route::patch('/{id}/toggle-status', [SlideshowController::class, 'toggleStatus']);
        });

        Route::prefix('settings')->group(function () {
            Route::get('/', [SettingController::class, 'index']);
            Route::post('/', [SettingController::class, 'update']);
            Route::get('/discount-sort', [SettingController::class, 'getDiscountSort']);
            Route::post('/discount-sort', [SettingController::class, 'updateDiscountSort']);
        });

        // 🚚 Shipping Zones Management
        Route::get('/shipping-zones', [ShippingZoneController::class, 'index']);
        Route::post('/shipping-zones', [ShippingZoneController::class, 'store']);
        Route::get('/shipping-zones/{id}', [ShippingZoneController::class, 'show']);
        Route::put('/shipping-zones/{id}', [ShippingZoneController::class, 'update']);
        Route::patch('/shipping-zones/{id}/status', [ShippingZoneController::class, 'updateStatus']);
        Route::delete('/shipping-zones/{id}', [ShippingZoneController::class, 'destroy']);

        // 🌟 Admin អាចលុប Order បាន ចំណែក Sale ត្រឹមតែអាច Manage
        Route::delete('orders/{id}', [OrderController::class, 'destroy']);
        Route::post('/system/clear-cache', [SystemController::class, 'clearCache']);
    });
});
