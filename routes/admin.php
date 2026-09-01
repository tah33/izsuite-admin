<?php

use App\Http\Controllers\Admin\AppController;
use App\Http\Controllers\Admin\AppCategoryController;
use App\Http\Controllers\Admin\ContactMessageController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\OverviewController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\PlanController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\SubscriptionController;
use App\Http\Controllers\Admin\TicketController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'admin', 'permission'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => redirect()->route('admin.overview'));
    Route::get('/overview', [OverviewController::class, 'index'])->name('overview');
    Route::get('/cache-clear', [OverviewController::class, 'clearCache'])->name('cache-clear');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('staff', StaffController::class)->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])->parameters(['staff' => 'id']);
    Route::patch('/staff/{id}/toggle-status', [StaffController::class, 'toggleStatus'])->name('staff.toggle-status');

    Route::resource('roles', RoleController::class)->except(['show'])->parameters(['roles' => 'id']);
    Route::resource('pages', PageController::class)->except(['show'])->parameters(['pages' => 'id']);
    Route::resource('apps', AppController::class)->except(['show'])->parameters(['apps' => 'id']);
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

    Route::get('/subscriptions/create', [SubscriptionController::class, 'create'])->name('subscriptions.create');
    Route::post('/subscriptions', [SubscriptionController::class, 'store'])->name('subscriptions.store');
    Route::get('/subscriptions/{id}/invoice', [SubscriptionController::class, 'downloadInvoice'])->name('subscriptions.invoice');
    Route::patch('/subscriptions/{id}/pause', [SubscriptionController::class, 'pause'])->name('subscriptions.pause');
    Route::patch('/subscriptions/{id}/resume', [SubscriptionController::class, 'resume'])->name('subscriptions.resume');
    Route::get('/subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
    Route::resource('plans', PlanController::class)->except(['show'])->parameters(['plans' => 'id']);
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
