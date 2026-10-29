<?php

use App\Enums\DocumentType;
use App\Http\Controllers\Api\AccountTransferController;
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
use App\Http\Controllers\Api\CreditNoteController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentIndexController;
use App\Http\Controllers\Api\DocumentNumberingController;
use App\Http\Controllers\Api\DocumentSignatureController;
use App\Http\Controllers\Api\DocumentTemplateController;
use App\Http\Controllers\Api\EmailSettingController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\ExchangeRateController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\ItemFileController;
use App\Http\Controllers\Api\ItemImageController;
use App\Http\Controllers\Api\ItemLabelController;
use App\Http\Controllers\Api\ItemSettingController;
use App\Http\Controllers\Api\ItemTransactionController;
use App\Http\Controllers\Api\LanguageController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\NavigationController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PaymentAccountController;
use App\Http\Controllers\Api\PaymentAccountTransactionController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SalesConversionController;
use App\Http\Controllers\Api\SalesDocumentAttachmentController;
use App\Http\Controllers\Api\SalesDocumentController;
use App\Http\Controllers\Api\SalesDocumentFlowController;
use App\Http\Controllers\Api\SalesDocumentRevisionController;
use App\Http\Controllers\Api\SalesEmailController;
use App\Http\Controllers\Api\SalesFormController;
use App\Http\Controllers\Api\SalesLinkController;
use App\Http\Controllers\Api\SalesPaymentAttachmentController;
use App\Http\Controllers\Api\SalesPaymentController;
use App\Http\Controllers\Api\SalesPdfController;
use App\Http\Controllers\Api\SalesRefundController;
use App\Http\Controllers\Api\SalesSettingController;
use App\Http\Controllers\Api\TaxRateController;
use App\Http\Controllers\Api\UserStaffController;
use App\Http\Controllers\Api\WithholdingTaxController;
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

        // Settings > Currencies and Settings > Taxes (Doc 01 sections 11, 12;
        // Doc 11 section 9): the base currency, the other currencies, whether
        // taxes are used and the tax options. Exchange rates and tax rates are
        // at /exchange-rates and /tax-rates.
        Route::get('/currencies', [BusinessCurrencyController::class, 'show'])->name('api.business-settings.currencies.show');
        Route::put('/currencies', [BusinessCurrencyController::class, 'update'])->name('api.business-settings.currencies.update');
        Route::get('/taxes', [BusinessTaxController::class, 'show'])->name('api.business-settings.taxes.show');
        Route::put('/taxes', [BusinessTaxController::class, 'update'])->name('api.business-settings.taxes.update');

        // Settings > Items (Doc 04 section 7): whether the business uses brands.
        Route::get('/items', [ItemSettingController::class, 'show'])->name('api.business-settings.items.show');
        Route::put('/items', [ItemSettingController::class, 'update'])->name('api.business-settings.items.update');

        // Settings > Sales (Doc 11 section 15): what new quotations, proforma
        // invoices and invoices start with - there while Sales Lite or Sales is on.
        Route::middleware('app:sales')->group(function () {
            Route::get('/sales', [SalesSettingController::class, 'show'])->name('api.business-settings.sales.show');
            Route::put('/sales', [SalesSettingController::class, 'update'])->name('api.business-settings.sales.update');
        });

        // Settings > Documents > Numbering (Doc 11 section 21): how each kind
        // of document the business's apps make is numbered, and a preview of
        // the next number a change would give.
        Route::get('/numbering', [DocumentNumberingController::class, 'index'])->name('api.business-settings.numbering.index');
        Route::put('/numbering/{type}', [DocumentNumberingController::class, 'update'])->name('api.business-settings.numbering.update');
        Route::get('/numbering/{type}/preview', [DocumentNumberingController::class, 'preview'])->name('api.business-settings.numbering.preview');

        // Settings > Documents > Templates (Doc 06 section 14, Doc 11 section
        // 20): how each kind looks, its signature image, and a PDF of a sample
        // drawn with settings not saved yet.
        Route::get('/templates', [DocumentTemplateController::class, 'index'])->name('api.business-settings.templates.index');
        Route::put('/templates/{type}', [DocumentTemplateController::class, 'update'])->name('api.business-settings.templates.update');
        Route::post('/templates/{type}/preview', [DocumentTemplateController::class, 'preview'])->middleware('throttle:30,1')->name('api.business-settings.templates.preview');
        Route::post('/templates/{type}/signature', [DocumentSignatureController::class, 'store'])->name('api.business-settings.templates.signature.store');
        Route::delete('/templates/{type}/signature', [DocumentSignatureController::class, 'destroy'])->name('api.business-settings.templates.signature.destroy');

        // Settings > Email (Doc 11 sections 15 and 22): the sender name, where
        // replies go, and what each document's email starts with.
        Route::get('/email', [EmailSettingController::class, 'show'])->name('api.business-settings.email.show');
        Route::put('/email', [EmailSettingController::class, 'update'])->name('api.business-settings.email.update');

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
     | The Core Workspace's overview pages (Doc 01 section 10): the
     | Dashboard (Doc 02), Documents (Doc 06) and Reports (Doc 07)
     |
     | Each reads what the business's apps keep - Sales Lite's so far - and
     | changes nothing. Totals are in the base currency.
     | -------------------------------------------------------- */
    Route::middleware(['auth:sanctum', 'workspace:required'])->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('api.dashboard');

        Route::get('/documents', [DocumentIndexController::class, 'index'])->name('api.documents.index');
        Route::get('/documents/export', [DocumentIndexController::class, 'export'])->name('api.documents.export');

        Route::get('/reports', [ReportController::class, 'index'])->name('api.reports.index');
        Route::get('/reports/{report}', [ReportController::class, 'show'])->where('report', '[a-z-]+')->name('api.reports.show');
        Route::get('/reports/{report}/export', [ReportController::class, 'export'])->where('report', '[a-z-]+')->name('api.reports.export');
        Route::get('/reports/{report}/pdf', [ReportController::class, 'pdf'])->where('report', '[a-z-]+')->middleware('throttle:30,1')->name('api.reports.pdf');
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
        Route::get('/items/{id}/transactions', [ItemTransactionController::class, 'index'])->whereNumber('id')->name('api.items.transactions.index');

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
        Route::get('/payment-accounts/{id}', [PaymentAccountController::class, 'show'])->whereNumber('id')->name('api.payment-accounts.show');
        Route::put('/payment-accounts/{id}', [PaymentAccountController::class, 'update'])->whereNumber('id')->name('api.payment-accounts.update');
        Route::delete('/payment-accounts/{id}', [PaymentAccountController::class, 'destroy'])->whereNumber('id')->name('api.payment-accounts.destroy');
        Route::post('/payment-accounts/{id}/default', [PaymentAccountController::class, 'makeDefault'])->whereNumber('id')->name('api.payment-accounts.default');
        Route::post('/payment-accounts/{id}/activate', [PaymentAccountController::class, 'activate'])->whereNumber('id')->name('api.payment-accounts.activate');
        Route::post('/payment-accounts/{id}/deactivate', [PaymentAccountController::class, 'deactivate'])->whereNumber('id')->name('api.payment-accounts.deactivate');

        // An account's history, and transfers between accounts (Doc 05 sections 11 and 13).
        Route::get('/payment-accounts/{id}/transactions', [PaymentAccountTransactionController::class, 'index'])->whereNumber('id')->name('api.payment-accounts.transactions.index');
        Route::get('/payment-accounts/{id}/transactions/export', [PaymentAccountTransactionController::class, 'export'])->whereNumber('id')->name('api.payment-accounts.transactions.export');
        Route::post('/account-transfers', [AccountTransferController::class, 'store'])->name('api.account-transfers.store');
        Route::get('/account-transfers/{id}', [AccountTransferController::class, 'show'])->whereNumber('id')->name('api.account-transfers.show');
        Route::put('/account-transfers/{id}', [AccountTransferController::class, 'update'])->whereNumber('id')->name('api.account-transfers.update');
        Route::delete('/account-transfers/{id}', [AccountTransferController::class, 'destroy'])->whereNumber('id')->name('api.account-transfers.destroy');
        Route::post('/account-transfers/{id}/complete', [AccountTransferController::class, 'complete'])->whereNumber('id')->name('api.account-transfers.complete');
        Route::post('/account-transfers/{id}/void', [AccountTransferController::class, 'void'])->whereNumber('id')->name('api.account-transfers.void');

        // Tax rates and exchange rates (Doc 11 sections 7 and 10), all at once:
        // a business has a handful of each.
        Route::get('/tax-rates', [TaxRateController::class, 'index'])->name('api.tax-rates.index');
        Route::post('/tax-rates', [TaxRateController::class, 'store'])->name('api.tax-rates.store');
        Route::put('/tax-rates/{id}', [TaxRateController::class, 'update'])->whereNumber('id')->name('api.tax-rates.update');
        Route::delete('/tax-rates/{id}', [TaxRateController::class, 'destroy'])->whereNumber('id')->name('api.tax-rates.destroy');

        Route::get('/withholding-taxes', [WithholdingTaxController::class, 'index'])->name('api.withholding-taxes.index');
        Route::post('/withholding-taxes', [WithholdingTaxController::class, 'store'])->name('api.withholding-taxes.store');
        Route::put('/withholding-taxes/{id}', [WithholdingTaxController::class, 'update'])->whereNumber('id')->name('api.withholding-taxes.update');
        Route::delete('/withholding-taxes/{id}', [WithholdingTaxController::class, 'destroy'])->whereNumber('id')->name('api.withholding-taxes.destroy');

        Route::get('/exchange-rates', [ExchangeRateController::class, 'index'])->name('api.exchange-rates.index');
        Route::post('/exchange-rates', [ExchangeRateController::class, 'store'])->name('api.exchange-rates.store');
        Route::delete('/exchange-rates/{id}', [ExchangeRateController::class, 'destroy'])->whereNumber('id')->name('api.exchange-rates.destroy');
    });

    /* ----------------------------------------------------------
     | Sales Lite (Doc 08): quotations, proforma invoices, invoices and
     | credit notes, addressed by their kind - /sales/quotations,
     | /sales/proformas, /sales/invoices, /sales/credit-notes - while Sales
     | Lite or Sales is on
     |
     | A new document is a draft. issue (an invoice or credit note), send,
     | accept, reject, cancel and void change its status, each only where its
     | status allows (see SalesDocumentRules); convert makes a draft proforma
     | or invoice from it. The form asks /sales/{kind}/defaults and
     | /sales/calculate as it is filled in.
     |
     | An issued invoice takes payments and credit notes; a credit note pays
     | refunds. Payments Received lists the payments; a payment or refund is
     | reversed, never deleted. Another business's records are a 404.
     |
     | Each document has its PDF and an email form, and a secure link once it
     | is numbered (see routes/web.php for what the link opens); a payment's
     | receipt has its PDF and email.
     | -------------------------------------------------------- */
    Route::middleware(['auth:sanctum', 'workspace:required', 'app:sales'])->prefix('sales')->group(function () {
        Route::post('/calculate', [SalesFormController::class, 'calculate'])->name('api.sales.calculate');

        Route::get('/payments', [SalesPaymentController::class, 'index'])->name('api.sales.payments.index');
        Route::get('/payments/{id}', [SalesPaymentController::class, 'show'])->whereNumber('id')->name('api.sales.payments.show');
        Route::post('/payments/{id}/reverse', [SalesPaymentController::class, 'reverse'])->whereNumber('id')->name('api.sales.payments.reverse');
        Route::get('/payments/{id}/pdf', [SalesPdfController::class, 'receipt'])->whereNumber('id')->name('api.sales.payments.pdf');
        Route::get('/payments/{id}/email-draft', [SalesEmailController::class, 'receiptDraft'])->whereNumber('id')->name('api.sales.payments.email-draft');
        Route::post('/payments/{id}/email', [SalesEmailController::class, 'sendReceipt'])->whereNumber('id')->middleware('throttle:30,1')->name('api.sales.payments.email');
        Route::get('/payments/{id}/attachments', [SalesPaymentAttachmentController::class, 'index'])->whereNumber('id')->name('api.sales.payments.attachments.index');
        Route::post('/payments/{id}/attachments', [SalesPaymentAttachmentController::class, 'store'])->whereNumber('id')->name('api.sales.payments.attachments.store');
        Route::delete('/payments/{id}/attachments/{fileId}', [SalesPaymentAttachmentController::class, 'destroy'])->whereNumber(['id', 'fileId'])->name('api.sales.payments.attachments.destroy');

        Route::post('/invoices/{id}/payments', [SalesPaymentController::class, 'store'])->whereNumber('id')->name('api.sales.invoices.payments.store');
        Route::get('/invoices/{id}/credit-note-defaults', [CreditNoteController::class, 'defaults'])->whereNumber('id')->name('api.sales.credit-notes.defaults');
        Route::post('/invoices/{id}/credit-notes/calculate', [CreditNoteController::class, 'calculate'])->whereNumber('id')->name('api.sales.credit-notes.calculate');
        Route::post('/invoices/{id}/credit-notes', [CreditNoteController::class, 'store'])->whereNumber('id')->name('api.sales.credit-notes.store');
        Route::put('/credit-notes/{id}', [CreditNoteController::class, 'update'])->whereNumber('id')->name('api.sales.credit-notes.update');
        Route::post('/credit-notes/{id}/refunds', [SalesRefundController::class, 'store'])->whereNumber('id')->name('api.sales.credit-notes.refunds.store');

        // Every kind.
        Route::prefix('{kind}')->where(['kind' => implode('|', array_keys(DocumentType::KINDS))])->group(function () {
            Route::get('/', [SalesDocumentController::class, 'index'])->name('api.sales.index');
            Route::get('/{id}', [SalesDocumentController::class, 'show'])->whereNumber('id')->name('api.sales.show');
            Route::delete('/{id}', [SalesDocumentController::class, 'destroy'])->whereNumber('id')->name('api.sales.destroy');
            Route::get('/{id}/pdf', [SalesPdfController::class, 'document'])->whereNumber('id')->name('api.sales.pdf');
            Route::get('/{id}/email-draft', [SalesEmailController::class, 'draft'])->whereNumber('id')->name('api.sales.email-draft');
            Route::post('/{id}/email', [SalesEmailController::class, 'send'])->whereNumber('id')->middleware('throttle:30,1')->name('api.sales.email');
            Route::post('/{id}/link', [SalesLinkController::class, 'store'])->whereNumber('id')->name('api.sales.link.store');
            Route::delete('/{id}/link', [SalesLinkController::class, 'destroy'])->whereNumber('id')->name('api.sales.link.destroy');

            foreach (['issue', 'send', 'accept', 'reject', 'cancel', 'void'] as $action) {
                Route::post("/{id}/{$action}", [SalesDocumentFlowController::class, $action])->whereNumber('id')->name("api.sales.{$action}");
            }

            Route::get('/{id}/attachments', [SalesDocumentAttachmentController::class, 'index'])->whereNumber('id')->name('api.sales.attachments.index');
            Route::post('/{id}/attachments', [SalesDocumentAttachmentController::class, 'store'])->whereNumber('id')->name('api.sales.attachments.store');
            Route::delete('/{id}/attachments/{fileId}', [SalesDocumentAttachmentController::class, 'destroy'])->whereNumber(['id', 'fileId'])->name('api.sales.attachments.destroy');
        });

        // The kinds the sales document form makes and changes - a credit note is made from its invoice.
        Route::prefix('{kind}')->where(['kind' => implode('|', DocumentType::FORM_KINDS)])->group(function () {
            Route::post('/', [SalesDocumentController::class, 'store'])->name('api.sales.store');
            Route::get('/defaults', [SalesFormController::class, 'defaults'])->name('api.sales.defaults');
            Route::put('/{id}', [SalesDocumentController::class, 'update'])->whereNumber('id')->name('api.sales.update');
            Route::post('/{id}/convert', [SalesConversionController::class, 'store'])->whereNumber('id')->name('api.sales.convert');
            Route::get('/{id}/revisions', [SalesDocumentRevisionController::class, 'index'])->whereNumber('id')->name('api.sales.revisions.index');
            Route::get('/{id}/revisions/{revision}', [SalesDocumentRevisionController::class, 'show'])->whereNumber(['id', 'revision'])->name('api.sales.revisions.show');
        });
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
