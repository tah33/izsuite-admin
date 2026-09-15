<?php

use App\Http\Controllers\Api\AppCategoryController;
use App\Http\Controllers\Api\AppController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactMessageController;
use App\Http\Controllers\Api\PlanController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    /* ----------------------------------------------------------
     | Public routes
     | -------------------------------------------------------- */
    Route::post('/contact-messages', [ContactMessageController::class, 'store']);
    Route::post('/contact/messages', [ContactMessageController::class, 'store']); // frontend alias

    Route::get('/app-categories', [AppCategoryController::class, 'index'])->name('api.app-categories.index');
    Route::get('/apps', [AppController::class, 'index'])->name('api.apps.index');
    Route::get('/plans', [PlanController::class, 'index'])->name('api.plans.index');

    /* ----------------------------------------------------------
     | Frontend user authentication
     | -------------------------------------------------------- */
    Route::prefix('auth')->group(function () {
        // Same ceiling as login. A tighter per-IP cap punishes the wrong
        // people - an office or campus behind one NAT address shares it, so a
        // handful of colleagues signing up together would lock each other out
        // while a bot just rotates IPs and walks past it. Real sign-up abuse is
        // stopped by email verification and a captcha, not by this number.
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('throttle:register')
            ->name('api.auth.register');

        // Throttled per IP: credential stuffing is the obvious attack on an
        // unauthenticated write endpoint.
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:login')
            ->name('api.auth.login');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me'])->name('api.auth.me');
            Route::post('/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
            Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('api.auth.logout-all');
        });
    });
});
