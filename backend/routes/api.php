<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\ProductController;
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

    Route::apiResource('customers', CustomerController::class);
});
