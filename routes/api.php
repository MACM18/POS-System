<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\TenantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Central tenant management routes (admin only)
Route::prefix('tenants')->group(function () {
    Route::get('/', [TenantController::class, 'index']);
    Route::post('/', [TenantController::class, 'store']);
    Route::get('/{id}', [TenantController::class, 'show']);
    Route::put('/{id}', [TenantController::class, 'update']);
    Route::delete('/{id}', [TenantController::class, 'destroy']);
    Route::post('/{id}/activate', [TenantController::class, 'activate']);
    Route::post('/{id}/suspend', [TenantController::class, 'suspend']);
});

// Tenant-scoped routes (require tenant context)
Route::middleware(['tenant'])->group(function () {

    // Authentication routes
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        // Protected auth routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/user', [AuthController::class, 'user']);
            Route::post('/refresh', [AuthController::class, 'refresh']);
            Route::post('/password', [AuthController::class, 'updatePassword']);
        });
    });

    // Protected API routes
    Route::middleware('auth:sanctum')->group(function () {

        // Categories
        Route::apiResource('categories', CategoryController::class);
        Route::get('/categories-tree', [CategoryController::class, 'tree']);

        // Products
        Route::apiResource('products', ProductController::class);
        Route::get('/products/barcode/{barcode}', [ProductController::class, 'findByBarcode']);
        Route::get('/products/sku/{sku}', [ProductController::class, 'findBySku']);
        Route::post('/products/{id}/stock', [ProductController::class, 'updateStock']);
        Route::get('/products-low-stock', [ProductController::class, 'lowStock']);

        // Customers
        Route::apiResource('customers', CustomerController::class);
        Route::get('/customers/{id}/purchases', [CustomerController::class, 'purchases']);

        // Sales
        Route::apiResource('sales', SaleController::class)->only(['index', 'store', 'show']);
        Route::post('/sales/{id}/cancel', [SaleController::class, 'cancel']);
        Route::get('/sales-statistics', [SaleController::class, 'statistics']);
        Route::get('/sales-daily-report', [SaleController::class, 'dailyReport']);
    });
});
