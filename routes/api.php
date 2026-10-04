<?php

use App\Http\Controllers\Api\AppCategoryController;
use App\Http\Controllers\Api\AppController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessSettingController;
use App\Http\Controllers\Api\ContactMessageController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\LanguageController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\StaffController;
use App\Http\Controllers\Api\WorkspaceController;
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
    Route::get('/currencies', [CurrencyController::class, 'index'])->name('api.currencies.index');
    Route::get('/languages', [LanguageController::class, 'index'])->name('api.languages.index');

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

    /* ----------------------------------------------------------
     | Signed-in user's business settings
     |
     | Saved with POST, not PUT: the form sends the logo as a file, and PHP only
     | parses multipart bodies on POST. It is still create-or-overwrite.
     | -------------------------------------------------------- */
    Route::middleware('auth:sanctum')->prefix('business-settings')->group(function () {
        Route::get('/', [BusinessSettingController::class, 'show'])->name('api.business-settings.show');
        Route::post('/', [BusinessSettingController::class, 'save'])->name('api.business-settings.save');
    });

    /* ----------------------------------------------------------
     | Signed-in user's locations - branches, warehouses, outlets
     |
     | Every id is looked up among the caller's own rows, so someone else's
     | location is a 404, the same as one that does not exist.
     | -------------------------------------------------------- */
    Route::middleware('auth:sanctum')->prefix('locations')->group(function () {
        Route::get('/', [LocationController::class, 'index'])->name('api.locations.index');
        Route::post('/', [LocationController::class, 'store'])->name('api.locations.store');
        Route::put('/{id}', [LocationController::class, 'update'])->whereNumber('id')->name('api.locations.update');
        Route::delete('/{id}', [LocationController::class, 'destroy'])->whereNumber('id')->name('api.locations.destroy');
    });

    /* ----------------------------------------------------------
     | Staff a signed-in user can attach to a workspace - id and name only,
     | and not for anonymous visitors
     | -------------------------------------------------------- */
    Route::get('/staff', [StaffController::class, 'index'])->middleware('auth:sanctum')->name('api.staff.index');

    /* ----------------------------------------------------------
     | Signed-in user's workspaces
     |
     | Same rule as locations: every id is looked up among the caller's own
     | rows, so someone else's workspace is a 404.
     | -------------------------------------------------------- */
    Route::middleware('auth:sanctum')->prefix('workspaces')->group(function () {
        Route::get('/', [WorkspaceController::class, 'index'])->name('api.workspaces.index');
        Route::post('/', [WorkspaceController::class, 'store'])->name('api.workspaces.store');
        Route::put('/{id}', [WorkspaceController::class, 'update'])->whereNumber('id')->name('api.workspaces.update');
        Route::delete('/{id}', [WorkspaceController::class, 'destroy'])->whereNumber('id')->name('api.workspaces.destroy');
    });
});
