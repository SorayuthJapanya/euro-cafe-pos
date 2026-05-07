<?php

use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')
    ->name('products')
    ->group(function () {
        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{product}', [ProductController::class, 'show']);

        Route::middleware('role:admin')
            ->group(function () {
                Route::post('products', [ProductController::class, 'store']);
                Route::put('products/{product}', [ProductController::class, 'update']);
                Route::delete('products/{product}', [ProductController::class, 'destroy']);
            });
    });
