
<?php

use App\Http\Controllers\Api\CategoryContrller;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->name('categories')
    ->group(function () {
        Route::get('categories', [CategoryContrller::class, 'index']);

        Route::middleware('role:admin')->group(function () {
            Route::post('categories', [CategoryContrller::class, 'store']);
            Route::put('categories/{category}', [CategoryContrller::class, 'update']);
            Route::delete('categories/{category}', [CategoryContrller::class, 'destroy']);
        });
    });
