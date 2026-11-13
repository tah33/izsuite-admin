<?php

use App\Http\Controllers\Admin\AdminAuditLogController;
use App\Http\Controllers\Admin\AdminTwoFactorController;
use App\Http\Controllers\Admin\AppController;
use App\Http\Controllers\Admin\BusinessController;
use App\Http\Controllers\Admin\AppCategoryController;
use App\Http\Controllers\Admin\ClientAccountController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\MenuItemController;
use App\Http\Controllers\Admin\OverviewController;
use App\Http\Controllers\Admin\PackageAccessController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\BillingReportController;
use App\Http\Controllers\Admin\CreditController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\RefundController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\SubscriptionInvoiceController;
use App\Http\Controllers\Admin\SubscriptionTaxController;
use App\Http\Controllers\Admin\SupportSessionController;
use App\Http\Controllers\Admin\TicketController;
use App\Http\Controllers\Admin\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin', 'permission'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => redirect()->route('admin.overview'));
    Route::get('/overview', [OverviewController::class, 'index'])->name('overview');
    Route::get('/cache-clear', [OverviewController::class, 'clearCache'])->name('cache-clear');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Each admin's own two-factor sign-in (Doc 15 section 15.2), open to every role.
    Route::get('/two-factor/setup', [AdminTwoFactorController::class, 'setup'])->name('two-factor.setup');
    Route::post('/two-factor/setup', [AdminTwoFactorController::class, 'confirm'])->name('two-factor.confirm');
    Route::get('/two-factor/recovery-codes', [AdminTwoFactorController::class, 'recoveryCodes'])->name('two-factor.recovery-codes');
    Route::post('/two-factor/recovery-codes', [AdminTwoFactorController::class, 'newRecoveryCodes'])->name('two-factor.new-recovery-codes');

    Route::resource('staff', StaffController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])->parameters(['staff' => 'id']);
    Route::patch('/staff/{id}/toggle-status', [StaffController::class, 'toggleStatus'])->name('staff.toggle-status');
    Route::post('/staff/{id}/two-factor/reset', [StaffController::class, 'resetTwoFactor'])->whereNumber('id')->name('staff.two-factor.reset');

    Route::resource('roles', RoleController::class)->except(['show'])->parameters(['roles' => 'id']);

    // The admin panel's audit log (Doc 15 section 15.26): read and exported, never changed.
    Route::get('/audit-log', [AdminAuditLogController::class, 'index'])->name('audit-log.index');
    Route::get('/audit-log/export', [AdminAuditLogController::class, 'export'])->name('audit-log.export');
    Route::get('/audit-log/{id}', [AdminAuditLogController::class, 'show'])->whereNumber('id')->name('audit-log.show');
    Route::resource('pages', PageController::class)->except(['show'])->parameters(['pages' => 'id']);
    Route::resource('apps', AppController::class)->except(['show'])->parameters(['apps' => 'id']);
    Route::post('/apps/{id}/dependencies', [AppController::class, 'storeDependency'])->name('apps.dependencies.store');
    Route::delete('/apps/{id}/dependencies/{dependencyId}', [AppController::class, 'destroyDependency'])->name('apps.dependencies.destroy');

    // App system: plans, which plan includes which app, the business sidebar, and clients and their businesses' plans.
    Route::get('/packages', [PackageController::class, 'index'])->name('packages.index');
    Route::get('/packages/{id}/edit', [PackageController::class, 'edit'])->name('packages.edit');
    Route::put('/packages/{id}', [PackageController::class, 'update'])->name('packages.update');
    // A plan's prices in each currency and cycle, from a day, and applying one to its subscribers (Doc 15 Phase C3a).
    Route::post('/packages/{id}/prices', [PackageController::class, 'addPrice'])->whereNumber('id')->name('packages.price');
    Route::post('/packages/{id}/prices/apply', [PackageController::class, 'applyPrice'])->whereNumber('id')->name('packages.price-apply');
    Route::delete('/packages/{id}/prices/{priceId}', [PackageController::class, 'removePrice'])->whereNumber(['id', 'priceId'])->name('packages.price-remove');
    Route::get('/package-access', [PackageAccessController::class, 'index'])->name('package-access.index');
    Route::put('/package-access', [PackageAccessController::class, 'update'])->name('package-access.update');
    Route::put('/menu-items/reorder', [MenuItemController::class, 'reorder'])->name('menu-items.reorder');
    Route::resource('menu-items', MenuItemController::class)->except(['show'])->parameters(['menu-items' => 'id']);
    Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
    Route::get('/clients/{id}', [ClientController::class, 'show'])->whereNumber('id')->name('clients.show');
    // Support's actions on a user's account (Doc 15 Phase B2): no password is ever shown or set.
    Route::post('/clients/{id}/verification', [ClientAccountController::class, 'resendVerification'])->whereNumber('id')->name('clients.verification');
    Route::post('/clients/{id}/recovery', [ClientAccountController::class, 'sendRecoveryLink'])->whereNumber('id')->name('clients.recovery');
    Route::post('/clients/{id}/sign-out', [ClientAccountController::class, 'signOut'])->whereNumber('id')->name('clients.sign-out');
    Route::post('/clients/{id}/lock', [ClientAccountController::class, 'lock'])->whereNumber('id')->name('clients.lock');
    Route::post('/clients/{id}/unlock', [ClientAccountController::class, 'unlock'])->whereNumber('id')->name('clients.unlock');
    Route::post('/clients/{id}/two-factor', [ClientAccountController::class, 'resetTwoFactor'])->whereNumber('id')->name('clients.two-factor');
    // Businesses (Doc 15 Phase B1): the list, a business's page, its status, and a message from izSuite.
    Route::get('/businesses', [BusinessController::class, 'index'])->name('businesses.index');
    Route::get('/businesses/{id}', [BusinessController::class, 'show'])->whereNumber('id')->name('businesses.show');
    Route::post('/businesses/{id}/status', [BusinessController::class, 'changeStatus'])->whereNumber('id')->name('businesses.status');
    Route::post('/businesses/{id}/notify', [BusinessController::class, 'notify'])->whereNumber('id')->name('businesses.notify');
    // Support sessions (Doc 15 Phase B4), in place of Login as: read only, or with edit access - each its own permission.
    Route::post('/businesses/{id}/support-sessions', [SupportSessionController::class, 'store'])->whereNumber('id')->name('businesses.support-session');
    Route::post('/businesses/{id}/support-sessions/edit', [SupportSessionController::class, 'storeWithEditAccess'])->whereNumber('id')->name('businesses.support-session-edit');
    Route::get('/support-sessions', [SupportSessionController::class, 'index'])->name('support-sessions.index');
    Route::get('/support-sessions/{id}/open', [SupportSessionController::class, 'enter'])->whereNumber('id')->name('support-sessions.open');
    Route::post('/support-sessions/{id}/end', [SupportSessionController::class, 'end'])->whereNumber('id')->name('support-sessions.end');
    Route::get('/workspaces/{id}/package', [WorkspaceController::class, 'reviewPackage'])->whereNumber('id')->name('workspaces.package.review');
    // izSuite's subscription invoices: confirming a bank transfer issues the receipt and starts the plan (Doc 13 Phase C1).
    Route::get('/subscription-invoices', [SubscriptionInvoiceController::class, 'index'])->name('subscription-invoices.index');
    Route::get('/subscription-invoices/{id}', [SubscriptionInvoiceController::class, 'show'])->whereNumber('id')->name('subscription-invoices.show');
    // Payments for izSuite's invoices and their refunds (Doc 15 Phase C2a); a refund needs the admin's password again.
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments/{id}/refund', [PaymentController::class, 'refund'])->whereNumber('id')->middleware('throttle:10,1')->name('payments.refund');
    Route::get('/refunds', [RefundController::class, 'index'])->name('refunds.index');
    Route::post('/subscription-invoices/{id}/confirm', [SubscriptionInvoiceController::class, 'confirm'])->whereNumber('id')->name('subscription-invoices.confirm');
    Route::post('/subscription-invoices/{id}/void', [SubscriptionInvoiceController::class, 'void'])->whereNumber('id')->name('subscription-invoices.void');
    Route::get('/subscription-invoices/{id}/pdf', [SubscriptionInvoiceController::class, 'pdf'])->whereNumber('id')->name('subscription-invoices.pdf');
    // The tax on those invoices, by country (Doc 15 section 16 - Doc 13 Phase D1).
    Route::resource('subscription-taxes', SubscriptionTaxController::class)->except(['show'])->parameters(['subscription-taxes' => 'id']);
    Route::patch('/workspaces/{id}/package', [WorkspaceController::class, 'updatePackage'])->name('workspaces.package');
    Route::resource('app-categories', AppCategoryController::class)->except(['show'])->parameters(['app-categories' => 'id']);
    Route::patch('/app-categories/{id}/toggle-status', [AppCategoryController::class, 'toggleActive'])->name('app-categories.toggle-status');
    Route::resource('departments', DepartmentController::class)->except(['show'])->parameters(['departments' => 'id']);
    Route::resource('faqs', FaqController::class)->except(['show'])->parameters(['faqs' => 'id']);
    Route::resource('contact-messages', ContactMessageController::class)->only(['index', 'show'])->parameters(['contact-messages' => 'id']);
    Route::post('/contact-messages/{id}/reply', [ContactMessageController::class, 'reply'])->name('contact-messages.reply');
    Route::get('/content', [ContentController::class, 'index'])->name('content.index');
    Route::put('/content', [ContentController::class, 'update'])->name('content.update');
    Route::post('/content/upload-image', [ContentController::class, 'uploadImage'])->name('content.upload-image');
    Route::post('/content/items', [ContentController::class, 'storeItem'])->name('content.items.store');
    Route::put('/content/items/{id}', [ContentController::class, 'updateItem'])->name('content.items.update');
    Route::delete('/content/items/{id}', [ContentController::class, 'destroyItem'])->name('content.items.destroy');
    Route::post('/content/items/reorder', [ContentController::class, 'reorderItems'])->name('content.items.reorder');
    Route::resource('tickets', TicketController::class)->only(['index', 'create', 'store', 'show', 'update'])->parameters(['tickets' => 'id']);
    Route::post('/tickets/{id}/reply', [TicketController::class, 'reply'])->name('tickets.reply');

    // Businesses' subscriptions (Doc 15 Phase C1): the list, one with its history, and the actions on it - each its own permission.
    Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::get('/subscriptions/{id}', [SubscriptionController::class, 'show'])->whereNumber('id')->name('subscriptions.show');
    Route::post('/subscriptions/{id}/plan', [SubscriptionController::class, 'changePlan'])->whereNumber('id')->name('subscriptions.plan');
    Route::post('/subscriptions/{id}/trial', [SubscriptionController::class, 'extendTrial'])->whereNumber('id')->name('subscriptions.trial');
    Route::post('/subscriptions/{id}/grace', [SubscriptionController::class, 'extendGrace'])->whereNumber('id')->name('subscriptions.grace');
    Route::post('/subscriptions/{id}/cancel', [SubscriptionController::class, 'scheduleCancellation'])->whereNumber('id')->name('subscriptions.cancel');
    Route::post('/subscriptions/{id}/undo', [SubscriptionController::class, 'cancelScheduledChange'])->whereNumber('id')->name('subscriptions.undo');
    Route::post('/subscriptions/{id}/resend', [SubscriptionController::class, 'resend'])->whereNumber('id')->name('subscriptions.resend');
    Route::post('/subscriptions/{id}/retry', [SubscriptionController::class, 'retry'])->whereNumber('id')->name('subscriptions.retry');
    // izSuite's credit to a business, and the billing reports (Doc 15 Phase C2b).
    Route::post('/subscriptions/{id}/credit', [SubscriptionController::class, 'addCredit'])->whereNumber('id')->name('subscriptions.credit');
    Route::post('/subscriptions/{id}/credit/remove', [SubscriptionController::class, 'removeCredit'])->whereNumber('id')->name('subscriptions.credit-remove');
    Route::get('/credits', [CreditController::class, 'index'])->name('credits.index');
    Route::get('/billing-reports', [BillingReportController::class, 'index'])->name('billing-reports.index');
    Route::resource('payment-methods', PaymentMethodController::class)->except(['show'])->parameters(['payment-methods' => 'id']);

    Route::get('languages/{id}/translate', [LanguageController::class, 'translate'])->name('languages.translate');
    Route::put('languages/{id}/translate', [LanguageController::class, 'saveTranslations'])->name('languages.save-translations');
    Route::resource('languages', LanguageController::class)->except(['show'])->parameters(['languages' => 'id']);

    Route::resource('currencies', CurrencyController::class)->except(['show'])->parameters(['currencies' => 'id']);
    Route::post('currencies/formatting', [CurrencyController::class, 'saveFormatting'])->name('currencies.save-formatting');

    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('/settings/test-mail', [SettingController::class, 'testMail'])->name('settings.test-mail');

    Route::post('/switch-language', [OverviewController::class, 'switchLanguage'])->name('switch-language');
    Route::post('/switch-currency', [OverviewController::class, 'switchCurrency'])->name('switch-currency');
});
