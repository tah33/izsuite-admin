<?php

use App\Http\Controllers\Api\AppActivationController;
use App\Http\Controllers\Api\AppCategoryController;
use App\Http\Controllers\Api\AppController;
use App\Http\Controllers\Api\AppsCenterController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessCurrencyController;
use App\Http\Controllers\Api\BusinessSettingController;
use App\Http\Controllers\Api\BusinessTaxController;
use App\Http\Controllers\Api\BusinessUsageController;
use App\Http\Controllers\Api\ContactAttachmentController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ContactMessageController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\ItemFileController;
use App\Http\Controllers\Api\ItemImageController;
use App\Http\Controllers\Api\ItemLabelController;
use App\Http\Controllers\Api\ItemSettingController;
use App\Http\Controllers\Api\LanguageController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\NavigationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PaymentAccountController;
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
    Route::get('/countries', [CountryController::class, 'index'])->name('api.countries.index');

    // A business's private file on this server, through a link GET
    // /files/{id}/link signed a few minutes ago: no token, so a plain browser
    // download can follow it. A link that is changed or out of date is a 403.
    Route::get('/files/{file}/download', [FileController::class, 'download'])->middleware('signed')->whereNumber('file')->name('api.files.download');

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

        // Settings > Currencies and Settings > Taxes (Doc 01 sections 11, 12):
        // the base currency, the other currencies, and whether taxes are used.
        Route::get('/currencies', [BusinessCurrencyController::class, 'show'])->name('api.business-settings.currencies.show');
        Route::put('/currencies', [BusinessCurrencyController::class, 'update'])->name('api.business-settings.currencies.update');
        Route::get('/taxes', [BusinessTaxController::class, 'show'])->name('api.business-settings.taxes.show');
        Route::put('/taxes', [BusinessTaxController::class, 'update'])->name('api.business-settings.taxes.update');

        // Settings > Items (Doc 04 section 7): whether the business uses brands.
        Route::get('/items', [ItemSettingController::class, 'show'])->name('api.business-settings.items.show');
        Route::put('/items', [ItemSettingController::class, 'update'])->name('api.business-settings.items.update');

        // Usage and Limits (Doc 13 section 13.14): the package and the storage used.
        Route::get('/usage', BusinessUsageController::class)->name('api.business-settings.usage');
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
     | The business's shared records (Doc 01 section 10): contacts, items and
     | payment accounts - the one source every app points at
     |
     | The same rule as locations: every id is looked up among the business's
     | own rows, so another business's is a 404. Contacts and items come a
     | page at a time; payment accounts all at once.
     | -------------------------------------------------------- */
    Route::middleware(['auth:sanctum', 'workspace:required'])->group(function () {
        Route::get('/contacts', [ContactController::class, 'index'])->name('api.contacts.index');
        Route::get('/contacts/export', [ExportController::class, 'contacts'])->name('api.contacts.export');
        Route::post('/contacts', [ContactController::class, 'store'])->name('api.contacts.store');
        Route::get('/contacts/{id}', [ContactController::class, 'show'])->whereNumber('id')->name('api.contacts.show');
        Route::put('/contacts/{id}', [ContactController::class, 'update'])->whereNumber('id')->name('api.contacts.update');
        Route::delete('/contacts/{id}', [ContactController::class, 'destroy'])->whereNumber('id')->name('api.contacts.destroy');
        Route::post('/contacts/{id}/activate', [ContactController::class, 'activate'])->whereNumber('id')->name('api.contacts.activate');
        Route::post('/contacts/{id}/deactivate', [ContactController::class, 'deactivate'])->whereNumber('id')->name('api.contacts.deactivate');
        Route::get('/contacts/{id}/attachments', [ContactAttachmentController::class, 'index'])->whereNumber('id')->name('api.contacts.attachments.index');
        Route::post('/contacts/{id}/attachments', [ContactAttachmentController::class, 'store'])->whereNumber('id')->name('api.contacts.attachments.store');
        Route::delete('/contacts/{id}/attachments/{fileId}', [ContactAttachmentController::class, 'destroy'])->whereNumber(['id', 'fileId'])->name('api.contacts.attachments.destroy');

        Route::get('/items', [ItemController::class, 'index'])->name('api.items.index');
        Route::get('/items/export', [ExportController::class, 'items'])->name('api.items.export');
        Route::post('/items', [ItemController::class, 'store'])->name('api.items.store');
        Route::get('/items/{id}', [ItemController::class, 'show'])->whereNumber('id')->name('api.items.show');
        Route::put('/items/{id}', [ItemController::class, 'update'])->whereNumber('id')->name('api.items.update');
        Route::delete('/items/{id}', [ItemController::class, 'destroy'])->whereNumber('id')->name('api.items.destroy');
        Route::post('/items/{id}/activate', [ItemController::class, 'activate'])->whereNumber('id')->name('api.items.activate');
        Route::post('/items/{id}/deactivate', [ItemController::class, 'deactivate'])->whereNumber('id')->name('api.items.deactivate');
        Route::post('/items/{id}/image', [ItemImageController::class, 'store'])->whereNumber('id')->name('api.items.image.store');
        Route::delete('/items/{id}/image', [ItemImageController::class, 'destroy'])->whereNumber('id')->name('api.items.image.destroy');
        Route::get('/items/{id}/files', [ItemFileController::class, 'index'])->whereNumber('id')->name('api.items.files.index');
        Route::post('/items/{id}/files', [ItemFileController::class, 'store'])->whereNumber('id')->name('api.items.files.store');
        Route::delete('/items/{id}/files/{fileId}', [ItemFileController::class, 'destroy'])->whereNumber(['id', 'fileId'])->name('api.items.files.destroy');

        // A link that downloads one of the business's files (see FileController).
        Route::get('/files/{id}/link', [FileController::class, 'link'])->whereNumber('id')->name('api.files.link');

        // Importing contacts or items from CSV, step by step (see ImportService).
        Route::get('/imports/{kind}/template', [ImportController::class, 'template'])->whereIn('kind', ['contacts', 'items'])->name('api.imports.template');
        Route::post('/imports', [ImportController::class, 'store'])->name('api.imports.store');
        Route::get('/imports/{id}', [ImportController::class, 'show'])->whereNumber('id')->name('api.imports.show');
        Route::put('/imports/{id}/mapping', [ImportController::class, 'map'])->whereNumber('id')->name('api.imports.map');
        Route::get('/imports/{id}/rows', [ImportController::class, 'rows'])->whereNumber('id')->name('api.imports.rows');
        Route::post('/imports/{id}/run', [ImportController::class, 'run'])->whereNumber('id')->name('api.imports.run');
        Route::delete('/imports/{id}', [ImportController::class, 'destroy'])->whereNumber('id')->name('api.imports.destroy');

        // The item categories and brands: /item-categories and /item-brands,
        // two lists that work the same way (see ItemLabel).
        Route::get('/item-{label}', [ItemLabelController::class, 'index'])->whereIn('label', ['categories', 'brands'])->name('api.item-labels.index');
        Route::post('/item-{label}', [ItemLabelController::class, 'store'])->whereIn('label', ['categories', 'brands'])->name('api.item-labels.store');
        Route::put('/item-{label}/{id}', [ItemLabelController::class, 'update'])->whereIn('label', ['categories', 'brands'])->whereNumber('id')->name('api.item-labels.update');
        Route::delete('/item-{label}/{id}', [ItemLabelController::class, 'destroy'])->whereIn('label', ['categories', 'brands'])->whereNumber('id')->name('api.item-labels.destroy');

        Route::get('/payment-accounts', [PaymentAccountController::class, 'index'])->name('api.payment-accounts.index');
        Route::post('/payment-accounts', [PaymentAccountController::class, 'store'])->name('api.payment-accounts.store');
        Route::put('/payment-accounts/{id}', [PaymentAccountController::class, 'update'])->whereNumber('id')->name('api.payment-accounts.update');
        Route::delete('/payment-accounts/{id}', [PaymentAccountController::class, 'destroy'])->whereNumber('id')->name('api.payment-accounts.destroy');
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
