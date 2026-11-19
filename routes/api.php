<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LetterController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->controller(AuthController::class)->group(function () {
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('register', 'register');
        Route::post('login', 'login');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('session', 'session');
        Route::post('logout', 'logout');
    });
});

// Letters: anyone can read (the landing page translator needs them), only admins can change.
Route::apiResource('letters', LetterController::class)->only(['index', 'show']);

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::apiResource('letters', LetterController::class)->only(['store', 'update', 'destroy']);
});
