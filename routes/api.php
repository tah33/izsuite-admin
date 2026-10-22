<?php

use App\Http\Controllers\Api\AppActivationController;
use App\Http\Controllers\Api\AppCategoryController;
use App\Http\Controllers\Api\AppController;
use App\Http\Controllers\Api\AppsCenterController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessSettingController;
use App\Http\Controllers\Api\ContactMessageController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\LanguageController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\NavigationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\UserStaffController;
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
     | The business's settings - one set per business
     |
     | This group, locations and user-staff answer for the business picked in
     | the top bar (X-Workspace-Id; without it, the account's oldest). Each
     | business's data is its own: another business's ids are a 404, even the
     | same owner's. An account with no workspace gets a 404 asking for one.
     |
     | Saved with POST, not PUT: the form sends the logo as a file, and PHP only
     | parses multipart bodies on POST. It is still create-or-overwrite.
     | -------------------------------------------------------- */
    Route::middleware(['auth:sanctum', 'workspace:required'])->prefix('business-settings')->group(function () {
        Route::get('/', [BusinessSettingController::class, 'show'])->name('api.business-settings.show');
        Route::post('/', [BusinessSettingController::class, 'save'])->name('api.business-settings.save');
    });

    /* ----------------------------------------------------------
     | The business's locations - branches, warehouses, outlets
     |
     | Every id is looked up among the business's own rows, so another
     | business's location is a 404, the same as one that does not exist.
     | -------------------------------------------------------- */
    Route::middleware(['auth:sanctum', 'workspace:required'])->prefix('locations')->group(function () {
        Route::get('/', [LocationController::class, 'index'])->name('api.locations.index');
        Route::post('/', [LocationController::class, 'store'])->name('api.locations.store');
        Route::put('/{id}', [LocationController::class, 'update'])->whereNumber('id')->name('api.locations.update');
        Route::delete('/{id}', [LocationController::class, 'destroy'])->whereNumber('id')->name('api.locations.destroy');
    });

    /* ----------------------------------------------------------
     | Signed-in user's workspaces
     |
     | Same rule as locations: every id is looked up among the caller's own
     | rows, so someone else's workspace is a 404. A workspace has no staff
     | member of its own - which staff work in it is set from the staff side,
     | as their assigned workspaces (see user-staff below).
     | -------------------------------------------------------- */
    Route::middleware('auth:sanctum')->prefix('workspaces')->group(function () {
        Route::get('/', [WorkspaceController::class, 'index'])->name('api.workspaces.index');
        Route::post('/', [WorkspaceController::class, 'store'])->name('api.workspaces.store');
        Route::put('/{id}', [WorkspaceController::class, 'update'])->whereNumber('id')->name('api.workspaces.update');
        Route::delete('/{id}', [WorkspaceController::class, 'destroy'])->whereNumber('id')->name('api.workspaces.destroy');
    });

    /* ----------------------------------------------------------
     | The business's staff - the people who work for it, with their contact
     | details, whether they are active, and the apps and workspaces they are
     | assigned to
     |
     | The same rule as locations applies - every id is looked up among the
     | business's own rows, so another business's is a 404. An assigned
     | workspace must be one of the owner's own, and an assigned app one that
     | is active.
     | -------------------------------------------------------- */
    Route::middleware(['auth:sanctum', 'workspace:required'])->prefix('user-staff')->group(function () {
        Route::get('/', [UserStaffController::class, 'index'])->name('api.user-staff.index');
        Route::post('/', [UserStaffController::class, 'store'])->name('api.user-staff.store');
        Route::put('/{id}', [UserStaffController::class, 'update'])->whereNumber('id')->name('api.user-staff.update');
        Route::delete('/{id}', [UserStaffController::class, 'destroy'])->whereNumber('id')->name('api.user-staff.destroy');
    });

    /* ----------------------------------------------------------
     | The signed-in user's business: its sidebar, its Apps Center, and
     | turning apps on and off ("izSuite Apps & Menus")
     |
     | `workspace` picks the business from the X-Workspace-Id header (the
     | caller's oldest workspace without one); someone else's is a 404.
     | Activating and deactivating answer with the new navigation.
     |
     | An app's own routes go in groups behind `app:{family}` - for example
     | Route::middleware(['auth:sanctum', 'workspace', 'app:sales'])
     | - or `app:{family},full` for what only the Full edition has.
     | -------------------------------------------------------- */
    Route::middleware(['auth:sanctum', 'workspace'])->group(function () {
        Route::get('/navigation', NavigationController::class)->name('api.navigation');
        Route::get('/apps-center', AppsCenterController::class)->name('api.apps-center');
        Route::post('/apps/{appKey}/activate', [AppActivationController::class, 'activate'])->name('api.apps.activate');
        Route::post('/apps/{appKey}/deactivate', [AppActivationController::class, 'deactivate'])->name('api.apps.deactivate');
    });
});
