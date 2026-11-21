<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EducationNewsController;
use App\Http\Controllers\Api\FavoriteSentenceController;
use App\Http\Controllers\Api\LetterController;
use App\Http\Controllers\Api\UserController;
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

// Landing page translator (public). Registered before the resource so "translate" isn't read as a {letter} id.
Route::get('letters/translate', [LetterController::class, 'translate']);

// Letters and education news: anyone can read (public pages need them), only admins can change.
Route::apiResource('letters', LetterController::class)->only(['index', 'show']);
Route::apiResource('education-news', EducationNewsController::class)
    ->only(['index', 'show'])
    ->parameters(['education-news' => 'news']);

// Favorite sentences: every logged-in user manages their own.
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('favorite-sentences', FavoriteSentenceController::class)
        ->only(['index', 'store', 'destroy'])
        ->parameters(['favorite-sentences' => 'favorite']);
});

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::apiResource('letters', LetterController::class)->only(['store', 'update', 'destroy']);
    Route::apiResource('education-news', EducationNewsController::class)
        ->only(['store', 'update', 'destroy'])
        ->parameters(['education-news' => 'news']);

    Route::get('dashboard/stats', [DashboardController::class, 'stats']);

    // Users: admins can view, change the role, and delete. Creating happens via /auth/register.
    Route::apiResource('users', UserController::class)->except(['store']);
});
