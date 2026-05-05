<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('api')
    ->name('auth')
    ->group(function () {
        Route::middleware('throttle:5,1')
            ->name('login')
            ->post('login', [AuthController::class, 'login']);
        Route::middleware('auth:sanctum')->group(function () {
            Route::name('logout')->post('logout', [AuthController::class, 'logout']);
            Route::name('me')->get('me', [AuthController::class, 'me']);
        });
    });
