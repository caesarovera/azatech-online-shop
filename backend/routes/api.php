<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderInvoiceController;
use App\Http\Controllers\Api\OrderTransitionController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductStockController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Route;

/*
| Semua route di file ini otomatis berprefix /api (lihat bootstrap/app.php).
| Otorisasi per role ditegakkan di Policy/Gate di dalam controller, bukan di sini,
| supaya aturan role ada di satu tempat (app/Policies).
*/

Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('auth.login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');

    Route::apiResource('products', ProductController::class);
    Route::post('/products/{product}/stock-adjustments', [ProductStockController::class, 'adjust'])->name('products.stock-adjustments');
    Route::get('/products/{product}/stock-movements', [ProductStockController::class, 'movements'])->name('products.stock-movements');

    Route::apiResource('customers', CustomerController::class);

    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::post('/orders', [OrderController::class, 'store'])
        ->middleware(['throttle:orders', EnsureIdempotency::class])
        ->name('orders.store');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/pay', [OrderTransitionController::class, 'pay'])->name('orders.pay');
    Route::post('/orders/{order}/ship', [OrderTransitionController::class, 'ship'])->name('orders.ship');
    Route::post('/orders/{order}/complete', [OrderTransitionController::class, 'complete'])->name('orders.complete');
    Route::post('/orders/{order}/cancel', [OrderTransitionController::class, 'cancel'])->name('orders.cancel');
    Route::get('/orders/{order}/invoice', [OrderInvoiceController::class, 'show'])->name('orders.invoice');

    Route::get('/dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');

    Route::get('/reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
    Route::get('/reports/top-products', [ReportController::class, 'topProducts'])->name('reports.top-products');
});
