<?php
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\B2CAdminController;
use App\Http\Controllers\B2CAuthController;
use App\Http\Controllers\B2COrderController;
use App\Http\Controllers\B2CPolicyController;
use App\Http\Controllers\B2CProductController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'app');
Route::view('/b2c', 'app');
Route::view('/b2c-admin', 'app');
Route::view('/my-orders', 'app');
Route::view('/profile', 'app');
Route::view('/printing-policy', 'app');
Route::view('/about-us', 'app');

Route::prefix('api/b2c')->group(function () {
    Route::post('/register', [B2CAuthController::class, 'register']);
    Route::post('/login', [B2CAuthController::class, 'login']);
    Route::post('/forgot-password', [B2CAuthController::class, 'forgotPassword']);
    Route::get('/me', [B2CAuthController::class, 'me']);
    Route::get('/admin-session', [B2CAuthController::class, 'adminSession'])->middleware('web');
    Route::post('/logout', [B2CAuthController::class, 'logout']);
    Route::get('/products', [B2CProductController::class, 'index']);
    Route::get('/categories', [B2CProductController::class, 'categories']);
    Route::get('/policy', [B2CPolicyController::class, 'show']);
    Route::middleware('auth:customer')->group(function () {
        Route::post('/profile/update', [B2CAuthController::class, 'updateProfile']);
        Route::post('/profile/reset-password', [B2CAuthController::class, 'resetPassword']);
        Route::get('/my-orders', [B2COrderController::class, 'myOrders']);
        Route::post('/orders', [B2COrderController::class, 'store']);
        Route::get('/notifications', [NotificationController::class, 'customerIndex']);
        Route::post('/notifications/read-all', [NotificationController::class, 'customerMarkAllRead']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'customerMarkRead']);
    });
});

Route::prefix('portal/api')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::middleware('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/profile/reset-password', [AuthController::class, 'resetPassword']);
        Route::post('/profile/update', [AuthController::class, 'updateProfile']);
        Route::get('/categories', [ProductController::class, 'categories']);
        Route::get('/download/{item}', [OrderController::class, 'download']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::middleware('approved.dealer')->group(function () {
            Route::get('/products', [ProductController::class, 'index']);
            Route::get('/my-orders', [OrderController::class, 'myOrders']);
            Route::post('/checkout', [OrderController::class, 'checkout']);
            Route::get('/my-wallet-transactions', [OrderController::class, 'myWalletTransactions']);
        });
        Route::middleware('role:admin')->prefix('/admin')->group(function () {
            Route::get('/dashboard', [AdminController::class, 'dashboard']);
            Route::post('/categories', [ProductController::class, 'storeCategory']);
            Route::delete('/categories/{category}', [ProductController::class, 'destroyCategory']);
            Route::get('/dealers', [AdminController::class, 'dealers']);
            Route::get('/hold-dealers', [AdminController::class, 'holdDealers']);
            Route::put('/dealers/{dealer}/approval', [AdminController::class, 'setApproval']);
            Route::post('/dealers/{dealer}/wallet', [AdminController::class, 'adjustWallet']);
            Route::get('/products', [ProductController::class, 'adminIndex']);
            Route::post('/products', [ProductController::class, 'store']);
            Route::put('/products/{product}', [ProductController::class, 'update']);
            Route::delete('/products/{product}', [ProductController::class, 'destroy']);
            Route::get('/orders', [AdminController::class, 'orders']);
            Route::get('/staff-users', [AdminController::class, 'staffUsers']);
            Route::post('/staff', [AdminController::class, 'createStaff']);
            Route::delete('/staff/{staff}', [AdminController::class, 'destroyStaff']);
            Route::put('/staff/{staff}', [AdminController::class, 'updateStaff']);
            Route::put('/orders/{order}/assign', [AdminController::class, 'assignStaff']);
            Route::put('/orders/{order}/status', [AdminController::class, 'updateStatus']);
            Route::post('/orders/{order}/share-receipt', [AdminController::class, 'shareReceipt']);
            Route::post('/orders/{order}/extra-charge', [AdminController::class, 'addCharge']);
            Route::get('/b2c/dashboard', [B2CAdminController::class, 'dashboard']);
            Route::get('/b2c/customers', [B2CAdminController::class, 'customers']);
            Route::delete('/b2c/customers/{customer}', [B2CAdminController::class, 'destroyCustomer']);
            Route::get('/b2c/orders', [B2CAdminController::class, 'orders']);
            Route::put('/b2c/orders/{b2cOrder}/status', [B2CAdminController::class, 'updateOrderStatus']);
            Route::put('/b2c/orders/{b2cOrder}/assign', [B2CAdminController::class, 'assignStaff']);
            Route::get('/b2c/policy', [B2CPolicyController::class, 'adminShow']);
            Route::put('/b2c/policy', [B2CPolicyController::class, 'update']);
            Route::get('/b2c/categories', [B2CProductController::class, 'adminCategories']);
            Route::post('/b2c/categories', [B2CProductController::class, 'storeCategory']);
            Route::delete('/b2c/categories/{b2cCategory}', [B2CProductController::class, 'destroyCategory']);
            Route::get('/b2c/products', [B2CProductController::class, 'adminIndex']);
            Route::post('/b2c/products', [B2CProductController::class, 'store']);
            Route::post('/b2c/products/{b2cProduct}', [B2CProductController::class, 'update']);
            Route::delete('/b2c/products/{b2cProduct}', [B2CProductController::class, 'destroy']);
        });
        Route::middleware('role:admin,staff')->prefix('/staff')->group(function () {
            Route::get('/queue', [StaffController::class, 'queue']);
            Route::put('/orders/{order}/status', [StaffController::class, 'updateStatus']);
            Route::get('/b2c/queue', [B2COrderController::class, 'queue']);
            Route::put('/b2c/orders/{b2cOrder}/status', [B2COrderController::class, 'updateStaffStatus']);
        });
    });
});

Route::get('/portal/orders/{order}/receipt', [OrderController::class, 'receipt'])->middleware('auth');

// React page refresh fallback (keep after portal API routes).
Route::view('/portal/{any?}', 'app')->where('any', '.*');
