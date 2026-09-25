<?php

use App\Http\Controllers\Api\AppCategoryController;
use App\Http\Controllers\Api\AppController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactMessageController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\ProfileController;
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
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('throttle:register')
            ->name('api.auth.register');
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:login')
            ->name('api.auth.login');
        Route::post('/email/verify', [EmailVerificationController::class, 'verify'])
            ->middleware('throttle:verify-email')
            ->name('api.auth.email.verify');
        Route::post('/email/resend', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:resend-verification')
            ->name('api.auth.email.resend');
        Route::post('/password/forgot', [PasswordResetController::class, 'forgot'])
            ->middleware('throttle:forgot-password')
            ->name('api.auth.password.forgot');

        Route::post('/password/verify-code', [PasswordResetController::class, 'verifyCode'])
            ->middleware('throttle:verify-email')
            ->name('api.auth.password.verify-code');

        Route::post('/password/reset', [PasswordResetController::class, 'reset'])
            ->middleware('throttle:reset-password')
            ->name('api.auth.password.reset');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me'])->name('api.auth.me');
            Route::post('/password/change', [AuthController::class, 'changePassword'])
                ->middleware('throttle:reset-password')
                ->name('api.auth.password.change');
            Route::post('/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
            Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('api.auth.logout-all');
        });
    });

    /* ----------------------------------------------------------
     | Signed-in user's profile
     | -------------------------------------------------------- */
    Route::middleware('auth:sanctum')->prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show'])->name('api.profile.show');
        Route::put('/', [ProfileController::class, 'update'])->name('api.profile.update');
    });
});
