<?php

use App\Enums\DocumentType;
use App\Http\Controllers\Api\AccountTransferController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AppActivationController;
use App\Http\Controllers\Api\AppCategoryController;
use App\Http\Controllers\Api\AppController;
use App\Http\Controllers\Api\AppDetailsController;
use App\Http\Controllers\Api\AppsCenterController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessCurrencyController;
use App\Http\Controllers\Api\BusinessSettingController;
use App\Http\Controllers\Api\BusinessTaxController;
use App\Http\Controllers\Api\BusinessUsageController;
use App\Http\Controllers\Api\ContactAttachmentController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ContactMergeController;
use App\Http\Controllers\Api\ContactMessageController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\SalesOverviewController;
use App\Http\Controllers\Api\SalesTeamController;
use App\Http\Controllers\Api\CreditNoteController;
use App\Http\Controllers\Api\CrmLeadAttachmentController;
use App\Http\Controllers\Api\CrmLeadController;
use App\Http\Controllers\Api\CrmLeadConversionController;
use App\Http\Controllers\Api\CrmLeadDuplicateController;
use App\Http\Controllers\Api\CrmLeadFlowController;
use App\Http\Controllers\Api\CrmOpportunityAttachmentController;
use App\Http\Controllers\Api\CrmOpportunityClosingController;
use App\Http\Controllers\Api\CrmForecastController;
use App\Http\Controllers\Api\CrmOverviewController;
use App\Http\Controllers\Api\CrmActivityController;
use App\Http\Controllers\Api\CrmConsentController;
use App\Http\Controllers\Api\CrmContactSummaryController;
use App\Http\Controllers\Api\CrmCustomFieldController;
use App\Http\Controllers\Api\CrmEmailController;
use App\Http\Controllers\Api\CrmAssignmentRuleController;
use App\Http\Controllers\Api\CrmEmailTemplateController;
use App\Http\Controllers\Api\CrmScoringRuleController;
use App\Http\Controllers\Api\CrmTimelineController;
use App\Http\Controllers\Api\CrmOpportunityController;
use App\Http\Controllers\Api\CrmOpportunityDocumentController;
use App\Http\Controllers\Api\CrmOpportunityViewController;
use App\Http\Controllers\Api\CrmOpportunityFlowController;
use App\Http\Controllers\Api\CrmOptionController;
use App\Http\Controllers\Api\CrmPipelineController;
use App\Http\Controllers\Api\CrmSettingController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentIndexController;
use App\Http\Controllers\Api\DocumentNumberingController;
use App\Http\Controllers\Api\DocumentSignatureController;
use App\Http\Controllers\Api\DocumentTemplateController;
use App\Http\Controllers\Api\EmailSettingController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\ExchangeRateController;
use App\Http\Controllers\Api\ExpenseAttachmentController;
use App\Http\Controllers\Api\ExpenseCategoryController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\ExpenseFlowController;
use App\Http\Controllers\Api\ExpenseFormController;
use App\Http\Controllers\Api\ExpensePaymentAttachmentController;
use App\Http\Controllers\Api\ExpensePaymentController;
use App\Http\Controllers\Api\ExpensePdfController;
use App\Http\Controllers\Api\ExpenseSettingController;
use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\GlobalSearchController;
use App\Http\Controllers\Api\ImportController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\ItemAttachmentController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\ItemFileController;
use App\Http\Controllers\Api\ItemImageController;
use App\Http\Controllers\Api\ItemLabelController;
use App\Http\Controllers\Api\ItemSettingController;
use App\Http\Controllers\Api\ItemTransactionController;
use App\Http\Controllers\Api\LanguageController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\LocationUserController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\NavigationController;
use App\Http\Controllers\Api\NotificationSettingController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PaymentAccountController;
use App\Http\Controllers\Api\CatalogCategoryController;
use App\Http\Controllers\Api\CatalogCollectionController;
use App\Http\Controllers\Api\CatalogVariantController;
use App\Http\Controllers\Api\StoreAccountAddressController;
use App\Http\Controllers\Api\StoreAccountAuthController;
use App\Http\Controllers\Api\StoreAccountController;
use App\Http\Controllers\Api\StoreAccountOrderController;
use App\Http\Controllers\Api\StoreCartController;
use App\Http\Controllers\Api\StoreCheckoutController;
use App\Http\Controllers\Api\StoreOrderController;
use App\Http\Controllers\Api\StorePaymentLinkController;
use App\Http\Controllers\Api\StoreCatalogController;
use App\Http\Controllers\Api\StorefrontPageController;
use App\Http\Controllers\Api\StorefrontThemeController;
use App\Http\Controllers\Api\CatalogPictureController;
use App\Http\Controllers\Api\EcommerceCatalogController;
use App\Http\Controllers\Api\EcommerceSettingController;
use App\Http\Controllers\Api\EcommerceShippingController;
use App\Http\Controllers\Api\EcommercePaymentMethodController;
use App\Http\Controllers\Api\EcommerceOrderController;
use App\Http\Controllers\Api\EcommerceOrderDeliveryController;
use App\Http\Controllers\Api\EcommerceOrderEditController;
use App\Http\Controllers\Api\EcommerceOrderFulfilmentController;
use App\Http\Controllers\Api\EcommerceOrderPaymentController;
use App\Http\Controllers\Api\EcommerceOrderStatusController;
use App\Http\Controllers\Api\EcommercePaymentLinkController;
use App\Http\Controllers\Api\StorefrontController;
use App\Http\Controllers\Api\StorefrontDomainController;
use App\Http\Controllers\Api\StorefrontHostController;
use App\Http\Controllers\Api\PosApprovalController;
use App\Http\Controllers\Api\PosCashMovementController;
use App\Http\Controllers\Api\PosDeviceController;
use App\Http\Controllers\Api\PosFormalInvoiceController;
use App\Http\Controllers\Api\PosHeldOrderController;
use App\Http\Controllers\Api\PosLockController;
use App\Http\Controllers\Api\PosOrderController;
use App\Http\Controllers\Api\PosOverviewController;
use App\Http\Controllers\Api\PosPendingPaymentController;
use App\Http\Controllers\Api\PosPendingRefundController;
use App\Http\Controllers\Api\PosReceiptController;
use App\Http\Controllers\Api\PosReportChoiceController;
use App\Http\Controllers\Api\PosReturnController;
use App\Http\Controllers\Api\PosRegisterController;
use App\Http\Controllers\Api\PosSellController;
use App\Http\Controllers\Api\PosSessionController;
use App\Http\Controllers\Api\PosSettingController;
use App\Http\Controllers\Api\PaymentAccountTransactionController;
use App\Http\Controllers\Api\PaymentTermController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProfileNotificationController;
use App\Http\Controllers\Api\ProfileSessionController;
use App\Http\Controllers\Api\ProfileTwoFactorController;
use App\Http\Controllers\Api\PurchaseAttachmentController;
use App\Http\Controllers\Api\PurchaseController;
use App\Http\Controllers\Api\PurchaseFlowController;
use App\Http\Controllers\Api\PurchaseFormController;
use App\Http\Controllers\Api\PurchasePdfController;
use App\Http\Controllers\Api\PurchaseSettingController;
use App\Http\Controllers\Api\RecordActivityController;
use App\Http\Controllers\Api\RecordCommentController;
use App\Http\Controllers\Api\RegionalSettingController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SalesConversionController;
use App\Http\Controllers\Api\CustomerAdvanceController;
use App\Http\Controllers\Api\CustomerCreditController;
use App\Http\Controllers\Api\PriceListController;
use App\Http\Controllers\Api\SalesAllocationController;
use App\Http\Controllers\Api\SalesReturnAttachmentController;
use App\Http\Controllers\Api\SalesReturnController;
use App\Http\Controllers\Api\SalesReturnFlowController;
use App\Http\Controllers\Api\SalesDeliveryAttachmentController;
use App\Http\Controllers\Api\SalesDeliveryController;
use App\Http\Controllers\Api\SalesDeliveryFlowController;
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
use App\Http\Controllers\Api\SetupController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\SubscriptionCardController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\SubscriptionInvoiceController;
use App\Http\Controllers\Api\SupportCaseController;
use App\Http\Controllers\Api\SupportSessionController;
use App\Http\Controllers\Api\SupplierPaymentAttachmentController;
use App\Http\Controllers\Api\SupplierPaymentController;
use App\Http\Controllers\Api\TaxRateController;
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
    // The website's Explore Apps and Pricing pages: the apps by category and the plans (Doc 13 sections 2 and 4).
    Route::get('/catalog', CatalogController::class)->name('api.catalog');
    Route::get('/plans', [PlanController::class, 'index'])->name('api.plans.index');
    Route::get('/currencies', [CurrencyController::class, 'index'])->name('api.currencies.index');
    Route::get('/languages', [LanguageController::class, 'index'])->name('api.languages.index');
    Route::get('/countries', [CountryController::class, 'index'])->name('api.countries.index');

    // Whether izSuite answers for a name - a storefront's izSuite address or a verified domain - for the
    // server before it issues a certificate, and for the storefront (Doc 19 Phase A3).
    Route::get('/storefront-hosts', [StorefrontHostController::class, 'show'])->middleware('throttle:120,1')->name('api.storefront-hosts.show');

    // A storefront as its customers see it, by its code and without signing in: the store, its products and a
    // product by its link (Doc 19 Phase C1).
    Route::middleware('throttle:120,1')->prefix('stores/{code}')->where(['code' => '[A-Za-z0-9-]+'])->group(function () {
        // From Phase E3 the store, its products, cart and checkout are as its visitor sees them: its signed-in
        // customer (X-Customer-Token) sees the listings kept for customers, at their own price lists.
        Route::middleware('store.customer:optional')->group(function () {
            Route::get('/', [StoreCatalogController::class, 'show'])->name('api.stores.show');
            // Phase C4b: its home page, as its theme lays it out.
            Route::get('/home', [StoreCatalogController::class, 'home'])->name('api.stores.home');
            Route::get('/products', [StoreCatalogController::class, 'products'])->name('api.stores.products');
            Route::get('/products/{slug}', [StoreCatalogController::class, 'product'])->where('slug', '[A-Za-z0-9-]+')->name('api.stores.product');
            // Phase C2: its categories' and collections' pages, and what its search suggests.
            Route::get('/categories/{slug}', [StoreCatalogController::class, 'category'])->where('slug', '[A-Za-z0-9-]+')->name('api.stores.category');
            Route::get('/collections', [StoreCatalogController::class, 'collections'])->name('api.stores.collections');
            Route::get('/collections/{slug}', [StoreCatalogController::class, 'collection'])->where('slug', '[A-Za-z0-9-]+')->name('api.stores.collection');
            Route::get('/suggestions', [StoreCatalogController::class, 'suggestions'])->name('api.stores.suggestions');
            // Phase C3: what its products offer to filter by - price, options and attributes.
            Route::get('/facets', [StoreCatalogController::class, 'facets'])->name('api.stores.facets');
            // Phase D3: the visitor's cart, known by its token (X-Cart-Token).
            Route::get('/cart', [StoreCartController::class, 'show'])->name('api.stores.cart.show');
            Route::post('/cart/lines', [StoreCartController::class, 'addLine'])->name('api.stores.cart.lines.store');
            Route::put('/cart/lines/{line}', [StoreCartController::class, 'updateLine'])->whereNumber('line')->name('api.stores.cart.lines.update');
            Route::delete('/cart/lines/{line}', [StoreCartController::class, 'removeLine'])->whereNumber('line')->name('api.stores.cart.lines.destroy');
            // Phase D4: checkout - what the cart comes to with the choices made so far, and the order placed. From
            // Phase E2 a signed-in customer's is their account's.
            Route::post('/checkout/quote', [StoreCheckoutController::class, 'quote'])->name('api.stores.checkout.quote');
            Route::post('/checkout', [StoreCheckoutController::class, 'place'])->name('api.stores.checkout.place');
        });
        // Phase C5a: a page of the store's - its contact or about page, or a policy - by its link.
        Route::get('/pages/{slug}', [StoreCatalogController::class, 'page'])->where('slug', '[A-Za-z0-9-]+')->name('api.stores.page');
        // Phase C6a: what its sitemap lists - for everyone alone.
        Route::get('/sitemap', [StoreCatalogController::class, 'sitemap'])->name('api.stores.sitemap');
        // Phase D5: an order's own page, by its link's key.
        Route::get('/orders/{key}', [StoreOrderController::class, 'show'])->where('key', '[a-f0-9]{64}')->name('api.stores.orders.show');
        // Phase F6a: a payment link's page, by the token it carries - and (Phase F6b) its customer saying they paid through it.
        Route::get('/pay/{token}', [StorePaymentLinkController::class, 'show'])->where('token', '[a-f0-9]{64}')->name('api.stores.pay.show');
        Route::post('/pay/{token}', [StorePaymentLinkController::class, 'report'])->where('token', '[a-f0-9]{64}')->name('api.stores.pay.report');
        // Phase E1: a customer's account - signing up, verifying the e-mail, signing in and a forgotten password -
        // and, signed in by its own token (X-Customer-Token), the account itself. What sends an e-mail is held to
        // ten a minute from one address, on a key of its own (the prefix), apart from the group's.
        Route::post('/account', [StoreAccountAuthController::class, 'signUp'])->middleware('throttle:10,1,store-account-mail')->name('api.stores.account.sign-up');
        Route::post('/account/verify', [StoreAccountAuthController::class, 'verify'])->name('api.stores.account.verify');
        Route::post('/account/verify/resend', [StoreAccountAuthController::class, 'resend'])->middleware('throttle:10,1,store-account-mail')->name('api.stores.account.verify.resend');
        Route::post('/account/sign-in', [StoreAccountAuthController::class, 'signIn'])->name('api.stores.account.sign-in');
        Route::post('/account/password/forgot', [StoreAccountAuthController::class, 'forgot'])->middleware('throttle:10,1,store-account-mail')->name('api.stores.account.password.forgot');
        Route::post('/account/password/reset', [StoreAccountAuthController::class, 'reset'])->name('api.stores.account.password.reset');
        Route::middleware('store.customer')->group(function () {
            Route::post('/account/sign-out', [StoreAccountAuthController::class, 'signOut'])->name('api.stores.account.sign-out');
            Route::get('/account', [StoreAccountController::class, 'show'])->name('api.stores.account.show');
            Route::put('/account', [StoreAccountController::class, 'update'])->name('api.stores.account.update');
            Route::put('/account/password', [StoreAccountController::class, 'password'])->name('api.stores.account.password');
            Route::delete('/account', [StoreAccountController::class, 'close'])->name('api.stores.account.close');
            // Phase E2: the account's orders, and its saved addresses.
            Route::get('/account/orders', [StoreAccountOrderController::class, 'index'])->name('api.stores.account.orders.index');
            Route::get('/account/orders/{order}', [StoreAccountOrderController::class, 'show'])->whereNumber('order')->name('api.stores.account.orders.show');
            Route::get('/account/addresses', [StoreAccountAddressController::class, 'index'])->name('api.stores.account.addresses.index');
            Route::post('/account/addresses', [StoreAccountAddressController::class, 'store'])->name('api.stores.account.addresses.store');
            Route::put('/account/addresses/{address}', [StoreAccountAddressController::class, 'update'])->whereNumber('address')->name('api.stores.account.addresses.update');
            Route::delete('/account/addresses/{address}', [StoreAccountAddressController::class, 'destroy'])->whereNumber('address')->name('api.stores.account.addresses.destroy');
        });
    });

    // Stripe's notifications of finished checkouts, taken only with its signature (Doc 13 Phase D2).
    Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])->name('api.webhooks.stripe');

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
        // With two-factor authentication on (Doc 12 section 22): the authenticator app's code, after the password.
        Route::post('/login/two-factor', [AuthController::class, 'loginWithCode'])
            ->middleware('throttle:login')
            ->name('api.auth.login.two-factor');
        // A support session from the admin panel (Doc 15 section 15.22): its one-time code, traded for a session in the business.
        Route::post('/support-session', [SupportSessionController::class, 'redeem'])
            ->middleware('throttle:login')
            ->name('api.auth.support-session');
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
     | An invitation to a business (Doc 12 section 6), by its link's secret:
     | shown to whoever holds the link, accepted with the account signed in,
     | or by making that account. Throttled: the link carries a secret.
     | -------------------------------------------------------- */
    Route::prefix('invitations')->middleware('throttle:invitations')->group(function () {
        Route::get('/{token}', [InvitationController::class, 'show'])->where('token', '[A-Za-z0-9]{48}')->name('api.invitations.show');
        Route::post('/{token}/register', [InvitationController::class, 'register'])->where('token', '[A-Za-z0-9]{48}')->name('api.invitations.register');
        Route::post('/{token}/accept', [InvitationController::class, 'accept'])->middleware('auth:sanctum')->where('token', '[A-Za-z0-9]{48}')->name('api.invitations.accept');
    });

    /* ----------------------------------------------------------
     | Signed-in user's profile (Doc 12 section 22): their own details and
     | image, the same in every business, and their signed-in devices. The
     | image is sent as a file, so it is saved with POST.
     | -------------------------------------------------------- */
    Route::middleware('auth:sanctum')->prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show'])->name('api.profile.show');
        Route::put('/', [ProfileController::class, 'update'])->name('api.profile.update');
        Route::post('/image', [ProfileController::class, 'changeImage'])->name('api.profile.image.update');
        Route::delete('/image', [ProfileController::class, 'removeImage'])->name('api.profile.image.destroy');

        // Two-factor authentication: each step but the app's first code asks for
        // the password again, so is limited as a password change is.
        Route::prefix('two-factor')->group(function () {
            Route::post('/setup', [ProfileTwoFactorController::class, 'setup'])->middleware('throttle:reset-password')->name('api.profile.two-factor.setup');
            Route::post('/confirm', [ProfileTwoFactorController::class, 'confirm'])->middleware('throttle:30,1')->name('api.profile.two-factor.confirm');
            Route::post('/recovery-codes', [ProfileTwoFactorController::class, 'recoveryCodes'])->middleware('throttle:reset-password')->name('api.profile.two-factor.recovery-codes');
            Route::post('/disable', [ProfileTwoFactorController::class, 'disable'])->middleware('throttle:reset-password')->name('api.profile.two-factor.disable');
        });

        Route::get('/sessions', [ProfileSessionController::class, 'index'])->name('api.profile.sessions.index');
        Route::post('/sessions/sign-out-others', [ProfileSessionController::class, 'signOutOthers'])->name('api.profile.sessions.sign-out-others');
        Route::post('/sessions/{id}/sign-out', [ProfileSessionController::class, 'signOut'])->whereNumber('id')->name('api.profile.sessions.sign-out');

        // Which notifications the user receives, in every business (Doc 14 Phase B2).
        Route::get('/notifications', [ProfileNotificationController::class, 'show'])->name('api.profile.notifications.show');
        Route::put('/notifications', [ProfileNotificationController::class, 'update'])->name('api.profile.notifications.update');
    });

    /* ----------------------------------------------------------
     | The business's settings - one set per business
     |
     | This group, locations and members answer for the business picked in
     | the top bar (X-Workspace-Id; without it, the account's oldest). Each
     | business's data is its own: another business's ids are a 404, even the
     | same owner's. An account with no workspace gets a 404 asking for one.
     |
     | Saved with POST, not PUT: the form sends the logo as a file, and PHP only
     | parses multipart bodies on POST. It is still create-or-overwrite.
     | -------------------------------------------------------- */

    // The First-Time Business Setup wizard (Doc 11 section 3): where a new
    // business's setup stands, the step it moves on to, and finishing it. Each
    // step saves through the settings below.
    Route::middleware(['auth:sanctum', 'workspace:required', 'access'])->prefix('setup')->group(function () {
        Route::get('/', [SetupController::class, 'show'])->name('api.setup.show');
        Route::put('/', [SetupController::class, 'update'])->name('api.setup.update');
        Route::post('/complete', [SetupController::class, 'complete'])->name('api.setup.complete');
    });

    Route::middleware(['auth:sanctum', 'workspace:required', 'access'])->prefix('business-settings')->group(function () {
        Route::get('/', [BusinessSettingController::class, 'show'])->name('api.business-settings.show');
        Route::post('/', [BusinessSettingController::class, 'save'])->name('api.business-settings.save');

        // Settings > Regional & Localization (Doc 11 section 5): date, time and
        // number formats, the week start, paper and fiscal year start.
        Route::get('/regional', [RegionalSettingController::class, 'show'])->name('api.business-settings.regional.show');
        Route::put('/regional', [RegionalSettingController::class, 'update'])->name('api.business-settings.regional.update');

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

        // Settings → Point of Sale (Doc 18 Phase A1): the walk-in customer, the
        // receipt's words and the payment methods - there while POS is on.
        Route::middleware('app:pos')->prefix('pos')->group(function () {
            Route::get('/', [PosSettingController::class, 'show'])->name('api.business-settings.pos.show');
            Route::put('/', [PosSettingController::class, 'update'])->name('api.business-settings.pos.update');
            // Phase B2b: closing sessions - the variance limit, and counting cash by notes and coins.
            Route::put('/cash-count', [PosSettingController::class, 'saveCashCount'])->name('api.business-settings.pos.cash-count.update');
            // Doc 18 Phase C3: selling - the discount limit and the cash rounding step.
            Route::put('/selling', [PosSettingController::class, 'saveSelling'])->name('api.business-settings.pos.selling.update');
            Route::post('/payment-methods', [PosSettingController::class, 'storeMethod'])->middleware('idempotent')->name('api.business-settings.pos.payment-methods.store');
            Route::put('/payment-methods/{id}', [PosSettingController::class, 'updateMethod'])->whereNumber('id')->name('api.business-settings.pos.payment-methods.update');
            Route::delete('/payment-methods/{id}', [PosSettingController::class, 'destroyMethod'])->whereNumber('id')->name('api.business-settings.pos.payment-methods.destroy');
        });

        // Settings → eCommerce (Doc 19 Phase A1): the business's own rules for
        // its storefronts - there while eCommerce is on.
        Route::middleware('app:ecommerce')->prefix('ecommerce')->group(function () {
            Route::get('/', [EcommerceSettingController::class, 'show'])->name('api.business-settings.ecommerce.show');
            Route::put('/', [EcommerceSettingController::class, 'update'])->name('api.business-settings.ecommerce.update');

            // Phase D1: shipping zones and their methods, and pickup at the business's locations.
            Route::get('/shipping', [EcommerceShippingController::class, 'show'])->name('api.business-settings.ecommerce.shipping.show');
            Route::post('/shipping-zones', [EcommerceShippingController::class, 'storeZone'])->middleware('idempotent')->name('api.business-settings.ecommerce.shipping-zones.store');
            Route::put('/shipping-zones/{id}', [EcommerceShippingController::class, 'updateZone'])->whereNumber('id')->name('api.business-settings.ecommerce.shipping-zones.update');
            Route::delete('/shipping-zones/{id}', [EcommerceShippingController::class, 'destroyZone'])->whereNumber('id')->name('api.business-settings.ecommerce.shipping-zones.destroy');
            Route::post('/shipping-methods', [EcommerceShippingController::class, 'storeMethod'])->middleware('idempotent')->name('api.business-settings.ecommerce.shipping-methods.store');
            Route::put('/shipping-methods/{id}', [EcommerceShippingController::class, 'updateMethod'])->whereNumber('id')->name('api.business-settings.ecommerce.shipping-methods.update');
            Route::delete('/shipping-methods/{id}', [EcommerceShippingController::class, 'destroyMethod'])->whereNumber('id')->name('api.business-settings.ecommerce.shipping-methods.destroy');
            Route::put('/pickup-points/{location}', [EcommerceShippingController::class, 'updatePickup'])->whereNumber('location')->name('api.business-settings.ecommerce.pickup-points.update');

            // Phase D2: payment methods without a gateway.
            Route::get('/payment-methods', [EcommercePaymentMethodController::class, 'index'])->name('api.business-settings.ecommerce.payment-methods.index');
            Route::post('/payment-methods', [EcommercePaymentMethodController::class, 'store'])->middleware('idempotent')->name('api.business-settings.ecommerce.payment-methods.store');
            Route::put('/payment-methods/{id}', [EcommercePaymentMethodController::class, 'update'])->whereNumber('id')->name('api.business-settings.ecommerce.payment-methods.update');
            Route::delete('/payment-methods/{id}', [EcommercePaymentMethodController::class, 'destroy'])->whereNumber('id')->name('api.business-settings.ecommerce.payment-methods.destroy');
        });

        // Settings > CRM (Doc 17 Phase A1): the mode, and the lead sources,
        // lost reasons and activity types - there while CRM is on.
        Route::middleware('app:crm')->prefix('crm')->group(function () {
            Route::get('/', [CrmSettingController::class, 'show'])->name('api.business-settings.crm.show');
            Route::put('/', [CrmSettingController::class, 'update'])->name('api.business-settings.crm.update');
            Route::post('/options', [CrmOptionController::class, 'store'])->name('api.business-settings.crm.options.store');
            Route::put('/options/{id}', [CrmOptionController::class, 'update'])->whereNumber('id')->name('api.business-settings.crm.options.update');
            Route::delete('/options/{id}', [CrmOptionController::class, 'destroy'])->whereNumber('id')->name('api.business-settings.crm.options.destroy');
            // A pipeline and its stages (Doc 17 Phase A2).
            Route::put('/pipelines/{id}', [CrmPipelineController::class, 'update'])->whereNumber('id')->name('api.business-settings.crm.pipelines.update');
            // Several pipelines (Doc 17 Phase D3b).
            Route::post('/pipelines', [CrmPipelineController::class, 'store'])->middleware('idempotent')->name('api.business-settings.crm.pipelines.store');
            Route::delete('/pipelines/{id}', [CrmPipelineController::class, 'destroy'])->whereNumber('id')->name('api.business-settings.crm.pipelines.destroy');
            // Phase F3b: its e-mail templates.
            Route::post('/email-templates', [CrmEmailTemplateController::class, 'store'])->middleware('idempotent')->name('api.business-settings.crm.email-templates.store');
            Route::put('/email-templates/{id}', [CrmEmailTemplateController::class, 'update'])->whereNumber('id')->name('api.business-settings.crm.email-templates.update');
            Route::delete('/email-templates/{id}', [CrmEmailTemplateController::class, 'destroy'])->whereNumber('id')->name('api.business-settings.crm.email-templates.destroy');
            // Phase I1: the lead assignment rules, in their order.
            Route::get('/assignment-rules', [CrmAssignmentRuleController::class, 'index'])->name('api.business-settings.crm.assignment-rules.index');
            Route::post('/assignment-rules', [CrmAssignmentRuleController::class, 'store'])->middleware('idempotent')->name('api.business-settings.crm.assignment-rules.store');
            Route::put('/assignment-rules/order', [CrmAssignmentRuleController::class, 'reorder'])->name('api.business-settings.crm.assignment-rules.order');
            Route::put('/assignment-rules/{id}', [CrmAssignmentRuleController::class, 'update'])->whereNumber('id')->name('api.business-settings.crm.assignment-rules.update');
            Route::delete('/assignment-rules/{id}', [CrmAssignmentRuleController::class, 'destroy'])->whereNumber('id')->name('api.business-settings.crm.assignment-rules.destroy');
            // Phase I2: the lead scoring rules.
            Route::get('/scoring-rules', [CrmScoringRuleController::class, 'index'])->name('api.business-settings.crm.scoring-rules.index');
            Route::post('/scoring-rules', [CrmScoringRuleController::class, 'store'])->middleware('idempotent')->name('api.business-settings.crm.scoring-rules.store');
            Route::put('/scoring-rules/{id}', [CrmScoringRuleController::class, 'update'])->whereNumber('id')->name('api.business-settings.crm.scoring-rules.update');
            Route::delete('/scoring-rules/{id}', [CrmScoringRuleController::class, 'destroy'])->whereNumber('id')->name('api.business-settings.crm.scoring-rules.destroy');
            // Phase I4: the custom fields for leads and opportunities.
            Route::get('/custom-fields', [CrmCustomFieldController::class, 'index'])->name('api.business-settings.crm.custom-fields.index');
            Route::post('/custom-fields', [CrmCustomFieldController::class, 'store'])->middleware('idempotent')->name('api.business-settings.crm.custom-fields.store');
            Route::put('/custom-fields/order', [CrmCustomFieldController::class, 'reorder'])->name('api.business-settings.crm.custom-fields.order');
            Route::put('/custom-fields/{id}', [CrmCustomFieldController::class, 'update'])->whereNumber('id')->name('api.business-settings.crm.custom-fields.update');
            Route::delete('/custom-fields/{id}', [CrmCustomFieldController::class, 'destroy'])->whereNumber('id')->name('api.business-settings.crm.custom-fields.destroy');
        });

        // Sales teams (Doc 16 Phase A): the full Sales app's - and, since Doc
        // 17 Phase A2, CRM's - with the people they can have.
        Route::middleware('app.any:sales:full,crm')->prefix('sales-teams')->group(function () {
            Route::get('/', [SalesTeamController::class, 'index'])->name('api.business-settings.sales-teams.index');
            Route::get('/people', [SalesTeamController::class, 'people'])->name('api.business-settings.sales-teams.people');
            Route::post('/', [SalesTeamController::class, 'store'])->name('api.business-settings.sales-teams.store');
            Route::put('/{id}', [SalesTeamController::class, 'update'])->whereNumber('id')->name('api.business-settings.sales-teams.update');
            Route::delete('/{id}', [SalesTeamController::class, 'destroy'])->whereNumber('id')->name('api.business-settings.sales-teams.destroy');
        });

        // Settings > Purchases (Doc 11 section 16): what new purchases start
        // with, and the duplicate supplier invoice check - there while
        // Purchases Lite or Purchases is on.
        Route::middleware('app:purchases')->group(function () {
            Route::get('/purchases', [PurchaseSettingController::class, 'show'])->name('api.business-settings.purchases.show');
            Route::put('/purchases', [PurchaseSettingController::class, 'update'])->name('api.business-settings.purchases.update');
        });

        // Settings > Expenses (Doc 11 section 17): what new expenses start
        // with, and the duplicate expense check - there while Expenses Lite
        // or Expenses is on. The categories are /expense-categories.
        Route::middleware('app:expenses')->group(function () {
            Route::get('/expenses', [ExpenseSettingController::class, 'show'])->name('api.business-settings.expenses.show');
            Route::put('/expenses', [ExpenseSettingController::class, 'update'])->name('api.business-settings.expenses.update');
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

        // Settings > Notifications & Email (Doc 11 section 22 - Doc 14 Phase B2):
        // which notifications the business sends, in the app and by e-mail.
        Route::get('/notifications', [NotificationSettingController::class, 'show'])->name('api.business-settings.notifications.show');
        Route::put('/notifications', [NotificationSettingController::class, 'update'])->name('api.business-settings.notifications.update');

        // Usage and Limits (Doc 13 section 13.14): the package and the storage used.
        Route::get('/usage', BusinessUsageController::class)->name('api.business-settings.usage');
    });

    /* ----------------------------------------------------------
     | The business's locations - branches, warehouses, outlets
     |
     | Every id is looked up among the business's own rows, so another
     | business's location is a 404, the same as one that does not exist.
     | -------------------------------------------------------- */
    Route::middleware(['auth:sanctum', 'workspace:required', 'access'])->prefix('locations')->group(function () {
        Route::get('/', [LocationController::class, 'index'])->name('api.locations.index');
        Route::post('/', [LocationController::class, 'store'])->middleware('idempotent')->name('api.locations.store');
        Route::put('/{id}', [LocationController::class, 'update'])->whereNumber('id')->name('api.locations.update');
        Route::post('/{id}/activate', [LocationController::class, 'activate'])->whereNumber('id')->name('api.locations.activate');
        Route::post('/{id}/deactivate', [LocationController::class, 'deactivate'])->whereNumber('id')->name('api.locations.deactivate');
        Route::post('/{id}/primary', [LocationController::class, 'primary'])->whereNumber('id')->name('api.locations.primary');
        Route::put('/{id}/users', [LocationUserController::class, 'update'])->whereNumber('id')->name('api.locations.users.update');
        Route::delete('/{id}', [LocationController::class, 'destroy'])->whereNumber('id')->name('api.locations.destroy');
    });

    /* ----------------------------------------------------------
     | Signed-in user's workspaces
     |
     | The list is those the caller owns and those they are an active member
     | of (Doc 12 section 4); only an owned one is changed or deleted here -
     | another id is a 404. Who works in a business is its members' list.
     | -------------------------------------------------------- */
    Route::middleware('auth:sanctum')->prefix('workspaces')->group(function () {
        Route::get('/', [WorkspaceController::class, 'index'])->name('api.workspaces.index');
        Route::post('/', [WorkspaceController::class, 'store'])->name('api.workspaces.store');
        Route::put('/{id}', [WorkspaceController::class, 'update'])->whereNumber('id')->name('api.workspaces.update');
        Route::delete('/{id}', [WorkspaceController::class, 'destroy'])->whereNumber('id')->name('api.workspaces.destroy');
    });

    /* ----------------------------------------------------------
     | The business's roles (Doc 12 sections 10 to 15): the default
     | templates and its own, each with the permissions it allows -
     | permissions lists what a role can hold among the apps on now
     | -------------------------------------------------------- */
    /* ----------------------------------------------------------
     | The business's subscription (Doc 13 sections 11 and 14): its plan and
     | status, subscribing to a paid plan, izSuite's invoices for it, the plans
     | to compare, and its billing details - for the owner and roles with
     | Manage Subscription - and, over the plan's limits, the owner's choice of
     | what stays active
     | -------------------------------------------------------- */
    // The bell: the signed-in user's own notifications in the business (Doc 14 section 15).
    Route::middleware(['auth:sanctum', 'workspace:required', 'access'])->prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('api.notifications.index');
        Route::get('/unread', [NotificationController::class, 'unread'])->name('api.notifications.unread');
        Route::post('/read', [NotificationController::class, 'readAll'])->name('api.notifications.read-all');
        Route::post('/{id}/read', [NotificationController::class, 'read'])->whereNumber('id')->name('api.notifications.read');
    });

    // Platform announcements (Doc 15 section 15.21): izSuite's banners for the business's people, each dismissed for oneself.
    Route::middleware(['auth:sanctum', 'workspace:required', 'access'])->prefix('announcements')->group(function () {
        Route::get('/', [AnnouncementController::class, 'index'])->name('api.announcements.index');
        Route::post('/{id}/dismiss', [AnnouncementController::class, 'dismiss'])->whereNumber('id')->name('api.announcements.dismiss');
    });

    // Support cases (Doc 15 section 15.22): the business asks izSuite, whatever its status.
    Route::middleware(['auth:sanctum', 'workspace:required', 'access'])->prefix('support-cases')->group(function () {
        Route::get('/', [SupportCaseController::class, 'index'])->name('api.support-cases.index');
        Route::post('/', [SupportCaseController::class, 'store'])->middleware('throttle:10,1')->name('api.support-cases.store');
        Route::get('/{id}', [SupportCaseController::class, 'show'])->whereNumber('id')->name('api.support-cases.show');
        Route::post('/{id}/replies', [SupportCaseController::class, 'reply'])->whereNumber('id')->middleware('throttle:30,1')->name('api.support-cases.reply');
    });

    Route::middleware(['auth:sanctum', 'workspace:required', 'access'])->prefix('subscription')->group(function () {
        Route::get('/', [SubscriptionController::class, 'show'])->name('api.subscription.show');
        Route::post('/', [SubscriptionController::class, 'subscribe'])->middleware('throttle:10,1')->name('api.subscription.subscribe');
        Route::get('/quote', [SubscriptionController::class, 'quote'])->name('api.subscription.quote');
        Route::post('/withdraw', [SubscriptionController::class, 'withdraw'])->name('api.subscription.withdraw');
        Route::post('/cancel', [SubscriptionController::class, 'cancel'])->name('api.subscription.cancel');
        Route::post('/resume', [SubscriptionController::class, 'resume'])->name('api.subscription.resume');
        Route::get('/change', [SubscriptionController::class, 'previewChange'])->name('api.subscription.change.preview');
        Route::post('/change', [SubscriptionController::class, 'change'])->middleware('throttle:10,1')->name('api.subscription.change');
        Route::delete('/change', [SubscriptionController::class, 'undoChange'])->name('api.subscription.change.undo');
        Route::put('/billing-details', [SubscriptionController::class, 'updateBillingDetails'])->name('api.subscription.billing-details');
        Route::get('/over-limit', [SubscriptionController::class, 'overLimit'])->name('api.subscription.over-limit');
        Route::post('/over-limit', [SubscriptionController::class, 'keepWithinLimits'])->name('api.subscription.keep');
        Route::get('/invoices', [SubscriptionInvoiceController::class, 'index'])->name('api.subscription.invoices');
        Route::put('/invoices/{id}/reference', [SubscriptionInvoiceController::class, 'reference'])->whereNumber('id')->name('api.subscription.invoices.reference');
        Route::get('/invoices/{id}/pdf', [SubscriptionInvoiceController::class, 'pdf'])->whereNumber('id')->name('api.subscription.invoices.pdf');
        // Paying by card on Stripe's page, and the saved card (Doc 13 Phase D2).
        Route::post('/invoices/{id}/card-checkout', [SubscriptionCardController::class, 'checkout'])->whereNumber('id')->middleware('throttle:10,1')->name('api.subscription.card.checkout');
        Route::post('/card-checkout', [SubscriptionCardController::class, 'complete'])->middleware('throttle:20,1')->name('api.subscription.card.complete');
        Route::post('/invoices/{id}/charge', [SubscriptionCardController::class, 'charge'])->whereNumber('id')->middleware('throttle:10,1')->name('api.subscription.card.charge');
        Route::post('/card', [SubscriptionCardController::class, 'setup'])->middleware('throttle:10,1')->name('api.subscription.card.setup');
        Route::delete('/card', [SubscriptionCardController::class, 'remove'])->name('api.subscription.card.remove');
    });

    Route::middleware(['auth:sanctum', 'workspace:required', 'access'])->prefix('roles')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('api.roles.index');
        Route::get('/permissions', [RoleController::class, 'permissions'])->name('api.roles.permissions');
        Route::post('/', [RoleController::class, 'store'])->name('api.roles.store');
        Route::get('/{id}', [RoleController::class, 'show'])->whereNumber('id')->name('api.roles.show');
        Route::put('/{id}', [RoleController::class, 'update'])->whereNumber('id')->name('api.roles.update');
        Route::delete('/{id}', [RoleController::class, 'destroy'])->whereNumber('id')->name('api.roles.destroy');
        Route::post('/{id}/copy', [RoleController::class, 'copy'])->whereNumber('id')->name('api.roles.copy');
        Route::post('/{id}/activate', [RoleController::class, 'activate'])->whereNumber('id')->name('api.roles.activate');
        Route::post('/{id}/deactivate', [RoleController::class, 'deactivate'])->whereNumber('id')->name('api.roles.deactivate');
    });

    /* ----------------------------------------------------------
     | The business's users (Doc 12 sections 4 to 9): its members and
     | invitations - invited, changed, suspended, removed, signed out - and
     | the business handed to one of them, by its owner alone
     | -------------------------------------------------------- */
    Route::middleware(['auth:sanctum', 'workspace:required', 'access'])->prefix('members')->group(function () {
        Route::get('/', [MemberController::class, 'index'])->name('api.members.index');
        Route::get('/export', [MemberController::class, 'export'])->name('api.members.export');
        Route::post('/', [MemberController::class, 'store'])->name('api.members.store');
        Route::get('/{id}', [MemberController::class, 'show'])->whereNumber('id')->name('api.members.show');
        Route::put('/{id}', [MemberController::class, 'update'])->whereNumber('id')->name('api.members.update');
        Route::delete('/{id}', [MemberController::class, 'destroy'])->whereNumber('id')->name('api.members.destroy');
        Route::post('/{id}/resend', [MemberController::class, 'resend'])->whereNumber('id')->name('api.members.resend');
        Route::post('/{id}/suspend', [MemberController::class, 'suspend'])->whereNumber('id')->name('api.members.suspend');
        Route::post('/{id}/reactivate', [MemberController::class, 'reactivate'])->whereNumber('id')->name('api.members.reactivate');
        Route::post('/{id}/remove', [MemberController::class, 'remove'])->whereNumber('id')->name('api.members.remove');
        Route::post('/{id}/sign-out', [MemberController::class, 'signOut'])->whereNumber('id')->name('api.members.sign-out');
        Route::post('/{id}/transfer-ownership', [MemberController::class, 'transfer'])->whereNumber('id')->middleware('throttle:reset-password')->name('api.members.transfer');
    });

    /* ----------------------------------------------------------
     | The Core Workspace's overview pages (Doc 01 section 10): the
     | Dashboard (Doc 02), Documents (Doc 06) and Reports (Doc 07)
     |
     | Each reads what the business's apps keep - Sales Lite's so far - and
     | changes nothing. Totals are in the base currency.
     | -------------------------------------------------------- */
    Route::middleware(['auth:sanctum', 'workspace:required', 'access'])->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('api.dashboard');

        Route::get('/documents', [DocumentIndexController::class, 'index'])->name('api.documents.index');
        Route::get('/documents/export', [DocumentIndexController::class, 'export'])->name('api.documents.export');

        Route::get('/reports', [ReportController::class, 'index'])->name('api.reports.index');
        // Doc 18 Phase H1: the registers and cashiers POS's reports can be narrowed to.
        Route::get('/reports/pos/choices', [PosReportChoiceController::class, 'index'])->name('api.reports.pos-choices');
        Route::get('/reports/{report}', [ReportController::class, 'show'])->where('report', '[a-z-]+')->name('api.reports.show');
        Route::get('/reports/{report}/export', [ReportController::class, 'export'])->where('report', '[a-z-]+')->name('api.reports.export');
        Route::get('/reports/{report}/excel', [ReportController::class, 'excel'])->where('report', '[a-z-]+')->name('api.reports.excel');
        Route::get('/reports/{report}/pdf',[ReportController::class, 'pdf'])->where('report', '[a-z-]+')->middleware('throttle:30,1')->name('api.reports.pdf');

        // The header's Global Search (Doc 14 section 16), asked as the user types.
        Route::get('/search', GlobalSearchController::class)->middleware('throttle:120,1')->name('api.search');
    });

    /* ----------------------------------------------------------
     | The business's shared records (Doc 01 section 10): contacts, items and
     | payment accounts - the one source every app points at
     |
     | The same rule as locations: every id is looked up among the business's
     | own rows, so another business's is a 404. Contacts and items come a
     | page at a time; payment accounts all at once.
     | -------------------------------------------------------- */
    Route::middleware(['auth:sanctum', 'workspace:required', 'access'])->group(function () {
        Route::get('/contacts', [ContactController::class, 'index'])->name('api.contacts.index');
        Route::get('/contacts/export', [ExportController::class, 'contacts'])->name('api.contacts.export');
        Route::post('/contacts', [ContactController::class, 'store'])->middleware('idempotent')->name('api.contacts.store');
        Route::get('/contacts/{id}', [ContactController::class, 'show'])->whereNumber('id')->name('api.contacts.show');
        Route::put('/contacts/{id}', [ContactController::class, 'update'])->whereNumber('id')->name('api.contacts.update');
        Route::delete('/contacts/{id}', [ContactController::class, 'destroy'])->whereNumber('id')->name('api.contacts.destroy');
        Route::post('/contacts/{id}/activate', [ContactController::class, 'activate'])->whereNumber('id')->name('api.contacts.activate');
        Route::post('/contacts/{id}/deactivate', [ContactController::class, 'deactivate'])->whereNumber('id')->name('api.contacts.deactivate');
        Route::get('/contacts/{id}/attachments', [ContactAttachmentController::class, 'index'])->whereNumber('id')->name('api.contacts.attachments.index');
        Route::post('/contacts/{id}/attachments', [ContactAttachmentController::class, 'store'])->whereNumber('id')->name('api.contacts.attachments.store');
        Route::delete('/contacts/{id}/attachments/{fileId}', [ContactAttachmentController::class, 'destroy'])->whereNumber(['id', 'fileId'])->name('api.contacts.attachments.destroy');
        // Merging another contact into one (Doc 17 Phase C3): its likely copies, a preview, the merge.
        Route::get('/contacts/{id}/merge/candidates', [ContactMergeController::class, 'candidates'])->whereNumber('id')->name('api.contacts.merge.candidates');
        Route::get('/contacts/{id}/merge', [ContactMergeController::class, 'preview'])->whereNumber('id')->name('api.contacts.merge.preview');
        Route::post('/contacts/{id}/merge', [ContactMergeController::class, 'store'])->whereNumber('id')->middleware('idempotent')->name('api.contacts.merge');

        Route::get('/items', [ItemController::class, 'index'])->name('api.items.index');
        Route::get('/items/export', [ExportController::class, 'items'])->name('api.items.export');
        Route::post('/items', [ItemController::class, 'store'])->middleware('idempotent')->name('api.items.store');
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
        // An item's attachments, whatever its type: documents kept with it.
        Route::get('/items/{id}/attachments', [ItemAttachmentController::class, 'index'])->whereNumber('id')->name('api.items.attachments.index');
        Route::post('/items/{id}/attachments', [ItemAttachmentController::class, 'store'])->whereNumber('id')->name('api.items.attachments.store');
        Route::delete('/items/{id}/attachments/{fileId}', [ItemAttachmentController::class, 'destroy'])->whereNumber(['id', 'fileId'])->name('api.items.attachments.destroy');
        Route::get('/items/{id}/transactions', [ItemTransactionController::class, 'index'])->whereNumber('id')->name('api.items.transactions.index');

        // A link that downloads one of the business's files (see FileController).
        Route::get('/files/{id}/link', [FileController::class, 'link'])->whereNumber('id')->name('api.files.link');
        Route::get('/files/limits', [FileController::class, 'limits'])->name('api.files.limits');

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
        Route::post('/payment-accounts', [PaymentAccountController::class, 'store'])->middleware('idempotent')->name('api.payment-accounts.store');
        Route::get('/payment-accounts/{id}', [PaymentAccountController::class, 'show'])->whereNumber('id')->name('api.payment-accounts.show');
        Route::put('/payment-accounts/{id}', [PaymentAccountController::class, 'update'])->whereNumber('id')->name('api.payment-accounts.update');
        Route::delete('/payment-accounts/{id}', [PaymentAccountController::class, 'destroy'])->whereNumber('id')->name('api.payment-accounts.destroy');
        Route::post('/payment-accounts/{id}/default', [PaymentAccountController::class, 'makeDefault'])->whereNumber('id')->name('api.payment-accounts.default');
        Route::post('/payment-accounts/{id}/activate', [PaymentAccountController::class, 'activate'])->whereNumber('id')->name('api.payment-accounts.activate');
        Route::post('/payment-accounts/{id}/deactivate', [PaymentAccountController::class, 'deactivate'])->whereNumber('id')->name('api.payment-accounts.deactivate');

        // An account's history, and transfers between accounts (Doc 05 sections 11 and 13).
        Route::get('/payment-accounts/{id}/transactions', [PaymentAccountTransactionController::class, 'index'])->whereNumber('id')->name('api.payment-accounts.transactions.index');
        Route::get('/payment-accounts/{id}/transactions/export', [PaymentAccountTransactionController::class, 'export'])->whereNumber('id')->name('api.payment-accounts.transactions.export');
        Route::post('/account-transfers', [AccountTransferController::class, 'store'])->middleware('idempotent')->name('api.account-transfers.store');
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

        // Settings > Security & Audit > Activity Log (Doc 11 section 24): the business's activity, and who appears in it.
        Route::get('/activity-log', [ActivityLogController::class, 'index'])->name('api.activity-log.index');
        Route::get('/activity-log/users', [ActivityLogController::class, 'users'])->name('api.activity-log.users');

        // Payment terms (Doc 11 section 14): one list for sales, purchases and expenses.
        Route::get('/payment-terms', [PaymentTermController::class, 'index'])->name('api.payment-terms.index');
        Route::post('/payment-terms', [PaymentTermController::class, 'store'])->name('api.payment-terms.store');
        Route::put('/payment-terms/{id}', [PaymentTermController::class, 'update'])->whereNumber('id')->name('api.payment-terms.update');
        Route::delete('/payment-terms/{id}', [PaymentTermController::class, 'destroy'])->whereNumber('id')->name('api.payment-terms.destroy');
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
    Route::middleware(['auth:sanctum', 'workspace:required', 'app:sales', 'access'])->prefix('sales')->group(function () {
        Route::post('/calculate', [SalesFormController::class, 'calculate'])->name('api.sales.calculate');

        // Deliveries (Doc 16 Phase C): a sales order's delivery notes, the full Sales app's.
        Route::middleware('app:sales,full')->group(function () {
            Route::post('/orders/{id}/deliveries', [SalesDeliveryController::class, 'store'])->whereNumber('id')->middleware('idempotent')->name('api.sales.deliveries.store');

            Route::prefix('deliveries')->group(function () {
                Route::get('/', [SalesDeliveryController::class, 'index'])->name('api.sales.deliveries.index');
                Route::get('/{id}', [SalesDeliveryController::class, 'show'])->whereNumber('id')->name('api.sales.deliveries.show');
                Route::put('/{id}', [SalesDeliveryController::class, 'update'])->whereNumber('id')->name('api.sales.deliveries.update');
                Route::delete('/{id}', [SalesDeliveryController::class, 'destroy'])->whereNumber('id')->name('api.sales.deliveries.destroy');
                Route::get('/{id}/pdf', [SalesDeliveryController::class, 'pdf'])->whereNumber('id')->name('api.sales.deliveries.pdf');

                foreach (['ready', 'deliver', 'cancel'] as $action) {
                    Route::post("/{id}/{$action}", [SalesDeliveryFlowController::class, $action])->whereNumber('id')->name("api.sales.deliveries.{$action}");
                }

                Route::get('/{id}/attachments', [SalesDeliveryAttachmentController::class, 'index'])->whereNumber('id')->name('api.sales.deliveries.attachments.index');
                Route::post('/{id}/attachments', [SalesDeliveryAttachmentController::class, 'store'])->whereNumber('id')->name('api.sales.deliveries.attachments.store');
                Route::delete('/{id}/attachments/{fileId}', [SalesDeliveryAttachmentController::class, 'destroy'])->whereNumber(['id', 'fileId'])->name('api.sales.deliveries.attachments.destroy');
            });

            // Price lists, and what a sales form's lines cost by them (Doc 16 Phase F1).
            Route::prefix('price-lists')->group(function () {
                Route::get('/', [PriceListController::class, 'index'])->name('api.sales.price-lists.index');
                Route::post('/', [PriceListController::class, 'store'])->middleware('idempotent')->name('api.sales.price-lists.store');
                Route::get('/{id}', [PriceListController::class, 'show'])->whereNumber('id')->name('api.sales.price-lists.show');
                Route::put('/{id}', [PriceListController::class, 'update'])->whereNumber('id')->name('api.sales.price-lists.update');
                Route::delete('/{id}', [PriceListController::class, 'destroy'])->whereNumber('id')->name('api.sales.price-lists.destroy');
            });
            Route::post('/prices', [PriceListController::class, 'quote'])->name('api.sales.prices');

            // Customer returns of an order's delivered goods (Doc 16 Phase E).
            Route::post('/orders/{id}/returns', [SalesReturnController::class, 'store'])->whereNumber('id')->middleware('idempotent')->name('api.sales.returns.store');

            Route::prefix('returns')->group(function () {
                Route::get('/', [SalesReturnController::class, 'index'])->name('api.sales.returns.index');
                Route::get('/{id}', [SalesReturnController::class, 'show'])->whereNumber('id')->name('api.sales.returns.show');
                Route::put('/{id}', [SalesReturnController::class, 'update'])->whereNumber('id')->name('api.sales.returns.update');
                Route::delete('/{id}', [SalesReturnController::class, 'destroy'])->whereNumber('id')->name('api.sales.returns.destroy');

                foreach (['submit', 'authorise', 'reject', 'receive', 'complete', 'cancel'] as $step) {
                    Route::post("/{id}/{$step}", [SalesReturnFlowController::class, $step])->whereNumber('id')->name("api.sales.returns.{$step}");
                }

                Route::get('/{id}/attachments', [SalesReturnAttachmentController::class, 'index'])->whereNumber('id')->name('api.sales.returns.attachments.index');
                Route::post('/{id}/attachments', [SalesReturnAttachmentController::class, 'store'])->whereNumber('id')->name('api.sales.returns.attachments.store');
                Route::delete('/{id}/attachments/{fileId}', [SalesReturnAttachmentController::class, 'destroy'])->whereNumber(['id', 'fileId'])->name('api.sales.returns.attachments.destroy');
            });

            // Customer advances and credit applied to invoices (Doc 16 Phase D1).
            Route::prefix('advances')->group(function () {
                Route::get('/', [CustomerAdvanceController::class, 'index'])->name('api.sales.advances.index');
                Route::post('/', [CustomerAdvanceController::class, 'store'])->middleware('idempotent')->name('api.sales.advances.store');
                Route::get('/{id}', [CustomerAdvanceController::class, 'show'])->whereNumber('id')->name('api.sales.advances.show');
                Route::post('/{id}/reverse', [CustomerAdvanceController::class, 'reverse'])->whereNumber('id')->name('api.sales.advances.reverse');
                Route::get('/{id}/invoices', [SalesAllocationController::class, 'advanceTargets'])->whereNumber('id')->name('api.sales.advances.invoices');
            });

            Route::get('/invoices/{id}/credit', [SalesAllocationController::class, 'sources'])->whereNumber('id')->name('api.sales.allocations.sources');
            Route::post('/invoices/{id}/allocations', [SalesAllocationController::class, 'store'])->whereNumber('id')->middleware('idempotent')->name('api.sales.allocations.store');
            Route::get('/credit-notes/{id}/invoices', [SalesAllocationController::class, 'noteTargets'])->whereNumber('id')->name('api.sales.allocations.note-invoices');
            Route::post('/allocations/{id}/remove', [SalesAllocationController::class, 'remove'])->whereNumber('id')->name('api.sales.allocations.remove');
        });

        // The full Sales app's (Doc 16 Phase A): its Overview, and whom the form can name as salesperson and team.
        Route::middleware('app:sales,full')->group(function () {
            Route::get('/overview', [SalesOverviewController::class, 'show'])->name('api.sales.overview');
            Route::get('/salespeople', [SalesTeamController::class, 'options'])->name('api.sales.salespeople');

            // A customer's credit control and exposure (Doc 16 Phase G1).
            Route::get('/customers/{id}/credit', [CustomerCreditController::class, 'show'])->whereNumber('id')->name('api.sales.customers.credit');
            Route::put('/customers/{id}/credit', [CustomerCreditController::class, 'update'])->whereNumber('id')->name('api.sales.customers.credit.update');
        });

        Route::get('/payments', [SalesPaymentController::class, 'index'])->name('api.sales.payments.index');
        Route::get('/payments/{id}', [SalesPaymentController::class, 'show'])->whereNumber('id')->name('api.sales.payments.show');
        Route::post('/payments/{id}/reverse', [SalesPaymentController::class, 'reverse'])->whereNumber('id')->name('api.sales.payments.reverse');
        Route::get('/payments/{id}/pdf', [SalesPdfController::class, 'receipt'])->whereNumber('id')->name('api.sales.payments.pdf');
        Route::get('/payments/{id}/email-draft', [SalesEmailController::class, 'receiptDraft'])->whereNumber('id')->name('api.sales.payments.email-draft');
        Route::post('/payments/{id}/email', [SalesEmailController::class, 'sendReceipt'])->whereNumber('id')->middleware('throttle:30,1')->name('api.sales.payments.email');
        Route::get('/payments/{id}/attachments', [SalesPaymentAttachmentController::class, 'index'])->whereNumber('id')->name('api.sales.payments.attachments.index');
        Route::post('/payments/{id}/attachments', [SalesPaymentAttachmentController::class, 'store'])->whereNumber('id')->name('api.sales.payments.attachments.store');
        Route::delete('/payments/{id}/attachments/{fileId}', [SalesPaymentAttachmentController::class, 'destroy'])->whereNumber(['id', 'fileId'])->name('api.sales.payments.attachments.destroy');

        Route::post('/invoices/{id}/payments', [SalesPaymentController::class, 'store'])->whereNumber('id')->middleware('idempotent')->name('api.sales.invoices.payments.store');
        Route::get('/invoices/{id}/credit-note-defaults', [CreditNoteController::class, 'defaults'])->whereNumber('id')->name('api.sales.credit-notes.defaults');
        Route::post('/invoices/{id}/credit-notes/calculate', [CreditNoteController::class, 'calculate'])->whereNumber('id')->name('api.sales.credit-notes.calculate');
        Route::post('/invoices/{id}/credit-notes', [CreditNoteController::class, 'store'])->whereNumber('id')->middleware('idempotent')->name('api.sales.credit-notes.store');
        Route::put('/credit-notes/{id}', [CreditNoteController::class, 'update'])->whereNumber('id')->name('api.sales.credit-notes.update');
        Route::post('/credit-notes/{id}/refunds', [SalesRefundController::class, 'store'])->whereNumber('id')->middleware('idempotent')->name('api.sales.credit-notes.refunds.store');

        // Every kind. Sales orders (Doc 16 Phase B) are the full Sales app's: on Sales Lite they are only read (sales.kind).
        Route::prefix('{kind}')->where(['kind' => implode('|', array_keys(DocumentType::KINDS))])->middleware('sales.kind')->group(function () {
            Route::get('/', [SalesDocumentController::class, 'index'])->name('api.sales.index');
            Route::get('/{id}', [SalesDocumentController::class, 'show'])->whereNumber('id')->name('api.sales.show');
            Route::delete('/{id}', [SalesDocumentController::class, 'destroy'])->whereNumber('id')->name('api.sales.destroy');
            Route::get('/{id}/pdf', [SalesPdfController::class, 'document'])->whereNumber('id')->name('api.sales.pdf');
            Route::get('/{id}/email-draft', [SalesEmailController::class, 'draft'])->whereNumber('id')->name('api.sales.email-draft');
            Route::post('/{id}/email', [SalesEmailController::class, 'send'])->whereNumber('id')->middleware('throttle:30,1')->name('api.sales.email');
            Route::post('/{id}/link', [SalesLinkController::class, 'store'])->whereNumber('id')->name('api.sales.link.store');
            Route::delete('/{id}/link', [SalesLinkController::class, 'destroy'])->whereNumber('id')->name('api.sales.link.destroy');

            // confirm, hold, resume and complete are a sales order's.
            foreach (['issue', 'send', 'accept', 'reject', 'cancel', 'void', 'confirm', 'hold', 'resume', 'complete'] as $action) {
                Route::post("/{id}/{$action}", [SalesDocumentFlowController::class, $action])->whereNumber('id')->name("api.sales.{$action}");
            }

            Route::get('/{id}/attachments', [SalesDocumentAttachmentController::class, 'index'])->whereNumber('id')->name('api.sales.attachments.index');
            Route::post('/{id}/attachments', [SalesDocumentAttachmentController::class, 'store'])->whereNumber('id')->name('api.sales.attachments.store');
            Route::delete('/{id}/attachments/{fileId}', [SalesDocumentAttachmentController::class, 'destroy'])->whereNumber(['id', 'fileId'])->name('api.sales.attachments.destroy');

            // Its comments and activity timeline (Doc 14 Phase D).
            Route::get('/{id}/activity', RecordActivityController::class)->whereNumber('id')->defaults('family', 'sales')->name('api.sales.activity');
            Route::get('/{id}/comments', [RecordCommentController::class, 'index'])->whereNumber('id')->defaults('family', 'sales')->name('api.sales.comments.index');
            Route::get('/{id}/comments/people', [RecordCommentController::class, 'people'])->whereNumber('id')->defaults('family', 'sales')->name('api.sales.comments.people');
            Route::post('/{id}/comments', [RecordCommentController::class, 'store'])->whereNumber('id')->defaults('family', 'sales')->middleware('idempotent')->name('api.sales.comments.store');
            Route::delete('/{id}/comments/{commentId}', [RecordCommentController::class, 'destroy'])->whereNumber(['id', 'commentId'])->defaults('family', 'sales')->name('api.sales.comments.destroy');
        });

        // The kinds the sales document form makes and changes - a credit note is made from its invoice.
        Route::prefix('{kind}')->where(['kind' => implode('|', DocumentType::FORM_KINDS)])->middleware('sales.kind')->group(function () {
            Route::post('/', [SalesDocumentController::class, 'store'])->middleware('idempotent')->name('api.sales.store');
            Route::get('/defaults', [SalesFormController::class, 'defaults'])->name('api.sales.defaults');
            Route::put('/{id}', [SalesDocumentController::class, 'update'])->whereNumber('id')->name('api.sales.update');
            // A confirmed sales order changed, with a reason (Doc 16 section 16.9).
            Route::put('/{id}/amend', [SalesDocumentController::class, 'amend'])->whereNumber('id')->name('api.sales.amend');
            Route::post('/{id}/convert', [SalesConversionController::class, 'store'])->whereNumber('id')->middleware('idempotent')->name('api.sales.convert');
            Route::post('/{id}/duplicate', [SalesConversionController::class, 'duplicate'])->whereNumber('id')->middleware('idempotent')->name('api.sales.duplicate');
            // A sales order's lines invoiced, some or all (Doc 16 Phase B2).
            Route::post('/{id}/invoice', [SalesConversionController::class, 'invoice'])->whereNumber('id')->middleware('idempotent')->name('api.sales.invoice');
            // A deposit invoice of a confirmed sales order (Doc 16 Phase D2).
            Route::post('/{id}/deposit', [SalesConversionController::class, 'deposit'])->whereNumber('id')->middleware('idempotent')->name('api.sales.deposit');
            Route::get('/{id}/revisions', [SalesDocumentRevisionController::class, 'index'])->whereNumber('id')->name('api.sales.revisions.index');
            Route::get('/{id}/revisions/{revision}', [SalesDocumentRevisionController::class, 'show'])->whereNumber(['id', 'revision'])->name('api.sales.revisions.show');
        });
    });

    /* ----------------------------------------------------------
     | Purchases Lite (Doc 09): what the business buys from its suppliers. A
     | draft is added, changed and deleted; recording it numbers it and makes
     | it owed (Pay Later); a recorded one is voided, never deleted. The form
     | asks for what a new purchase starts with and its totals. A recorded
     | purchase takes supplier payments, each with its voucher; Payments Made
     | lists them, and a payment is reversed, never deleted. Another
     | business's records are a 404.
     | -------------------------------------------------------- */
    Route::middleware(['auth:sanctum', 'workspace:required', 'app:purchases', 'access'])->prefix('purchases')->group(function () {
        Route::get('/payments', [SupplierPaymentController::class, 'index'])->name('api.purchases.payments.index');
        Route::get('/payments/{id}', [SupplierPaymentController::class, 'show'])->whereNumber('id')->name('api.purchases.payments.show');
        Route::post('/payments/{id}/reverse', [SupplierPaymentController::class, 'reverse'])->whereNumber('id')->name('api.purchases.payments.reverse');
        Route::get('/payments/{id}/pdf', [PurchasePdfController::class, 'voucher'])->whereNumber('id')->name('api.purchases.payments.pdf');
        Route::get('/payments/{id}/attachments', [SupplierPaymentAttachmentController::class, 'index'])->whereNumber('id')->name('api.purchases.payments.attachments.index');
        Route::post('/payments/{id}/attachments', [SupplierPaymentAttachmentController::class, 'store'])->whereNumber('id')->name('api.purchases.payments.attachments.store');
        Route::delete('/payments/{id}/attachments/{fileId}', [SupplierPaymentAttachmentController::class, 'destroy'])->whereNumber(['id', 'fileId'])->name('api.purchases.payments.attachments.destroy');

        Route::get('/', [PurchaseController::class, 'index'])->name('api.purchases.index');
        Route::post('/', [PurchaseController::class, 'store'])->middleware('idempotent')->name('api.purchases.store');
        Route::get('/defaults', [PurchaseFormController::class, 'defaults'])->name('api.purchases.defaults');
        Route::post('/calculate', [PurchaseFormController::class, 'calculate'])->name('api.purchases.calculate');
        Route::get('/export', [PurchaseController::class, 'export'])->name('api.purchases.export');
        Route::get('/{id}', [PurchaseController::class, 'show'])->whereNumber('id')->name('api.purchases.show');
        Route::put('/{id}', [PurchaseController::class, 'update'])->whereNumber('id')->name('api.purchases.update');
        Route::delete('/{id}', [PurchaseController::class, 'destroy'])->whereNumber('id')->name('api.purchases.destroy');
        Route::post('/{id}/record', [PurchaseFlowController::class, 'record'])->whereNumber('id')->name('api.purchases.record');
        Route::post('/{id}/void', [PurchaseFlowController::class, 'void'])->whereNumber('id')->name('api.purchases.void');
        Route::post('/{id}/payments', [SupplierPaymentController::class, 'store'])->whereNumber('id')->middleware('idempotent')->name('api.purchases.payments.store');
        Route::get('/{id}/pdf', [PurchasePdfController::class, 'purchase'])->whereNumber('id')->name('api.purchases.pdf');
        Route::get('/{id}/attachments', [PurchaseAttachmentController::class, 'index'])->whereNumber('id')->name('api.purchases.attachments.index');
        Route::post('/{id}/attachments', [PurchaseAttachmentController::class, 'store'])->whereNumber('id')->name('api.purchases.attachments.store');
        Route::delete('/{id}/attachments/{fileId}', [PurchaseAttachmentController::class, 'destroy'])->whereNumber(['id', 'fileId'])->name('api.purchases.attachments.destroy');

        // Its comments and activity timeline (Doc 14 Phase D).
        Route::get('/{id}/activity', RecordActivityController::class)->whereNumber('id')->defaults('family', 'purchases')->name('api.purchases.activity');
        Route::get('/{id}/comments', [RecordCommentController::class, 'index'])->whereNumber('id')->defaults('family', 'purchases')->name('api.purchases.comments.index');
        Route::get('/{id}/comments/people', [RecordCommentController::class, 'people'])->whereNumber('id')->defaults('family', 'purchases')->name('api.purchases.comments.people');
        Route::post('/{id}/comments', [RecordCommentController::class, 'store'])->whereNumber('id')->defaults('family', 'purchases')->middleware('idempotent')->name('api.purchases.comments.store');
        Route::delete('/{id}/comments/{commentId}', [RecordCommentController::class, 'destroy'])->whereNumber(['id', 'commentId'])->defaults('family', 'purchases')->name('api.purchases.comments.destroy');
    });

    /* ----------------------------------------------------------
     | Expenses Lite (Doc 10): what the business spends on running itself,
     | by category. A draft is added, changed and deleted; recording it
     | numbers it and makes it owed (Pay Later); a recorded one is voided,
     | never deleted. The form asks for what a new expense starts with and
     | its totals. A recorded expense takes payments, each with its voucher;
     | Payments Made lists them, and a payment is reversed, never deleted.
     | The categories - one list - are listed, added, changed and deleted
     | while unused. Another business's records are a 404.
     | -------------------------------------------------------- */
    Route::middleware(['auth:sanctum', 'workspace:required', 'app:expenses', 'access'])->group(function () {
        Route::get('/expense-categories', [ExpenseCategoryController::class, 'index'])->name('api.expense-categories.index');
        Route::post('/expense-categories', [ExpenseCategoryController::class, 'store'])->name('api.expense-categories.store');
        Route::put('/expense-categories/{id}', [ExpenseCategoryController::class, 'update'])->whereNumber('id')->name('api.expense-categories.update');
        Route::delete('/expense-categories/{id}', [ExpenseCategoryController::class, 'destroy'])->whereNumber('id')->name('api.expense-categories.destroy');

        Route::prefix('expenses')->group(function () {
            Route::get('/payments', [ExpensePaymentController::class, 'index'])->name('api.expenses.payments.index');
            Route::get('/payments/{id}', [ExpensePaymentController::class, 'show'])->whereNumber('id')->name('api.expenses.payments.show');
            Route::post('/payments/{id}/reverse', [ExpensePaymentController::class, 'reverse'])->whereNumber('id')->name('api.expenses.payments.reverse');
            Route::get('/payments/{id}/pdf', [ExpensePdfController::class, 'voucher'])->whereNumber('id')->name('api.expenses.payments.pdf');
            Route::get('/payments/{id}/attachments', [ExpensePaymentAttachmentController::class, 'index'])->whereNumber('id')->name('api.expenses.payments.attachments.index');
            Route::post('/payments/{id}/attachments', [ExpensePaymentAttachmentController::class, 'store'])->whereNumber('id')->name('api.expenses.payments.attachments.store');
            Route::delete('/payments/{id}/attachments/{fileId}', [ExpensePaymentAttachmentController::class, 'destroy'])->whereNumber(['id', 'fileId'])->name('api.expenses.payments.attachments.destroy');

            Route::get('/', [ExpenseController::class, 'index'])->name('api.expenses.index');
            Route::post('/', [ExpenseController::class, 'store'])->middleware('idempotent')->name('api.expenses.store');
            Route::get('/defaults', [ExpenseFormController::class, 'defaults'])->name('api.expenses.defaults');
            Route::post('/calculate', [ExpenseFormController::class, 'calculate'])->name('api.expenses.calculate');
            Route::get('/export', [ExpenseController::class, 'export'])->name('api.expenses.export');
            Route::get('/{id}', [ExpenseController::class, 'show'])->whereNumber('id')->name('api.expenses.show');
            Route::put('/{id}', [ExpenseController::class, 'update'])->whereNumber('id')->name('api.expenses.update');
            Route::delete('/{id}', [ExpenseController::class, 'destroy'])->whereNumber('id')->name('api.expenses.destroy');
            Route::post('/{id}/record', [ExpenseFlowController::class, 'record'])->whereNumber('id')->name('api.expenses.record');
            Route::post('/{id}/void', [ExpenseFlowController::class, 'void'])->whereNumber('id')->name('api.expenses.void');
            Route::post('/{id}/payments', [ExpensePaymentController::class, 'store'])->whereNumber('id')->middleware('idempotent')->name('api.expenses.payments.store');
            Route::get('/{id}/pdf', [ExpensePdfController::class, 'expense'])->whereNumber('id')->name('api.expenses.pdf');
            Route::get('/{id}/attachments', [ExpenseAttachmentController::class, 'index'])->whereNumber('id')->name('api.expenses.attachments.index');
            Route::post('/{id}/attachments', [ExpenseAttachmentController::class, 'store'])->whereNumber('id')->name('api.expenses.attachments.store');
            Route::delete('/{id}/attachments/{fileId}', [ExpenseAttachmentController::class, 'destroy'])->whereNumber(['id', 'fileId'])->name('api.expenses.attachments.destroy');

            // Its comments and activity timeline (Doc 14 Phase D).
            Route::get('/{id}/activity', RecordActivityController::class)->whereNumber('id')->defaults('family', 'expenses')->name('api.expenses.activity');
            Route::get('/{id}/comments', [RecordCommentController::class, 'index'])->whereNumber('id')->defaults('family', 'expenses')->name('api.expenses.comments.index');
            Route::get('/{id}/comments/people', [RecordCommentController::class, 'people'])->whereNumber('id')->defaults('family', 'expenses')->name('api.expenses.comments.people');
            Route::post('/{id}/comments', [RecordCommentController::class, 'store'])->whereNumber('id')->defaults('family', 'expenses')->middleware('idempotent')->name('api.expenses.comments.store');
            Route::delete('/{id}/comments/{commentId}', [RecordCommentController::class, 'destroy'])->whereNumber(['id', 'commentId'])->defaults('family', 'expenses')->name('api.expenses.comments.destroy');
        });
    });

    /* ----------------------------------------------------------
     | CRM (Doc 17): the Overview's figures (Phase G1) and the Forecast
     | (Phase G2), counting only what the user sees.
     | -------------------------------------------------------- */
    /* --------------------------------------------------------
     | Point of Sale (Doc 18): its registers (Phase A2) - the Registers page,
     | what a register's form chooses from, adding, changing and deleting
     | one. Each is seen at the locations the user may use.
     | -------------------------------------------------------- */
    Route::middleware(['auth:sanctum', 'workspace:required', 'app:pos', 'access'])->prefix('pos/registers')->group(function () {
        Route::get('/', [PosRegisterController::class, 'index'])->name('api.pos.registers.index');
        Route::get('/options', [PosRegisterController::class, 'options'])->name('api.pos.registers.options');
        Route::post('/', [PosRegisterController::class, 'store'])->middleware('idempotent')->name('api.pos.registers.store');
        Route::put('/{id}', [PosRegisterController::class, 'update'])->whereNumber('id')->name('api.pos.registers.update');
        Route::delete('/{id}', [PosRegisterController::class, 'destroy'])->whereNumber('id')->name('api.pos.registers.destroy');
        // Doc 18 Phase I3: its devices - listed, added, changed, removed and tested.
        Route::get('/{register}/devices', [PosDeviceController::class, 'index'])->whereNumber('register')->name('api.pos.devices.index');
        Route::post('/{register}/devices', [PosDeviceController::class, 'store'])->whereNumber('register')->middleware('idempotent')->name('api.pos.devices.store');
        Route::put('/{register}/devices/{device}', [PosDeviceController::class, 'update'])->whereNumber(['register', 'device'])->name('api.pos.devices.update');
        Route::delete('/{register}/devices/{device}', [PosDeviceController::class, 'destroy'])->whereNumber(['register', 'device'])->name('api.pos.devices.destroy');
        Route::post('/{register}/devices/{device}/test', [PosDeviceController::class, 'test'])->whereNumber(['register', 'device'])->name('api.pos.devices.test');
        Route::get('/{register}/devices/{device}/test-page', [PosDeviceController::class, 'testPage'])->whereNumber(['register', 'device'])->middleware('throttle:30,1')->name('api.pos.devices.test-page');
    });

    /* --------------------------------------------------------
     | Point of Sale (Doc 18): its sessions (Phase B1) - the Sessions page
     | and its choices, opening one on a register, a session's page and
     | closing it with the cash counted. Each is seen where its register's
     | location may be used.
     | -------------------------------------------------------- */
    Route::middleware(['auth:sanctum', 'workspace:required', 'app:pos', 'access'])->prefix('pos/sessions')->group(function () {
        Route::get('/', [PosSessionController::class, 'index'])->name('api.pos.sessions.index');
        Route::get('/options', [PosSessionController::class, 'options'])->name('api.pos.sessions.options');
        Route::post('/', [PosSessionController::class, 'store'])->middleware('idempotent')->name('api.pos.sessions.store');
        Route::get('/{id}', [PosSessionController::class, 'show'])->whereNumber('id')->name('api.pos.sessions.show');
        Route::post('/{id}/close', [PosSessionController::class, 'close'])->whereNumber('id')->middleware('idempotent')->name('api.pos.sessions.close');
        // Phase B2a: a supervisor approves a variance beyond the limit, and reopens a counted session.
        Route::post('/{id}/approve', [PosSessionController::class, 'approve'])->whereNumber('id')->middleware('idempotent')->name('api.pos.sessions.approve');
        Route::post('/{id}/reopen', [PosSessionController::class, 'reopen'])->whereNumber('id')->middleware('idempotent')->name('api.pos.sessions.reopen');
        // Phase D2: its payments waiting to be confirmed.
        Route::get('/{id}/pending-payments', [PosPendingPaymentController::class, 'index'])->whereNumber('id')->name('api.pos.sessions.pending-payments');
        // Phase E2: its returns' refunds waiting to be confirmed.
        Route::get('/{id}/pending-refunds', [PosPendingRefundController::class, 'index'])->whereNumber('id')->name('api.pos.sessions.pending-refunds');
        // Phase F1: its cash movements.
        Route::get('/{id}/cash-movements', [PosCashMovementController::class, 'index'])->whereNumber('id')->name('api.pos.sessions.cash-movements');
        // Phase F3: its X report while open, its Z report once counted.
        Route::get('/{id}/{kind}-report', [PosSessionController::class, 'report'])->whereNumber('id')->whereIn('kind', ['x', 'z'])->name('api.pos.sessions.report');
    });

    /* --------------------------------------------------------
     | Point of Sale (Doc 18): its Overview (Phase G2) - today at the
     | registers of the locations the user may use.
     | -------------------------------------------------------- */
    Route::middleware(['auth:sanctum', 'workspace:required', 'app:pos', 'access'])->get('/pos/overview', [PosOverviewController::class, 'show'])->name('api.pos.overview');

    /* --------------------------------------------------------
     | Point of Sale (Doc 18): the selling screen (Phase C1), in the user's
     | own open session - its register, customer, categories and most-sold
     | items, the items found or scanned, and the cart priced and totalled.
     | The cart's quote only reads, so it is a GET: nothing is logged for it.
     | -------------------------------------------------------- */
    Route::middleware(['auth:sanctum', 'workspace:required', 'app:pos', 'access'])->prefix('pos/sell')->group(function () {
        Route::get('/', [PosSellController::class, 'show'])->name('api.pos.sell.show');
        Route::get('/items', [PosSellController::class, 'items'])->name('api.pos.sell.items');
        Route::get('/quote', [PosSellController::class, 'quote'])->name('api.pos.sell.quote');
        // Phase D3b: what credit the customer holds.
        Route::get('/credit', [PosSellController::class, 'credit'])->name('api.pos.sell.credit');
    });

    /* --------------------------------------------------------
     | Point of Sale (Doc 18 Phase I1): the cashier's PIN, and locking and
     | unlocking their register - tries at a PIN or password held back.
     | -------------------------------------------------------- */
    Route::middleware(['auth:sanctum', 'workspace:required', 'app:pos', 'access'])->prefix('pos')->group(function () {
        Route::post('/lock', [PosLockController::class, 'lock'])->name('api.pos.lock');
        Route::post('/unlock', [PosLockController::class, 'unlock'])->middleware('throttle:10,1')->name('api.pos.unlock');
        Route::put('/pin', [PosLockController::class, 'pin'])->middleware('throttle:10,1')->name('api.pos.pin');
        // Phase I2: a manager's approval at the register - who may give it, and giving it.
        Route::get('/approvers', [PosApprovalController::class, 'approvers'])->name('api.pos.approvers');
        Route::post('/approvals', [PosApprovalController::class, 'store'])->middleware('throttle:10,1')->name('api.pos.approvals.store');
    });

    /* --------------------------------------------------------
     | Point of Sale (Doc 18): a sale paid and completed (Phase C2), in the
     | user's own open session - its invoice, payments and order at once -
     | and its receipt (Phase C4): its PDF, each copy counted, and e-mailed.
     | -------------------------------------------------------- */
    Route::middleware(['auth:sanctum', 'workspace:required', 'app:pos', 'access'])->prefix('pos/orders')->group(function () {
        // Phase G1: the Orders page.
        Route::get('/', [PosOrderController::class, 'index'])->name('api.pos.orders.index');
        Route::post('/', [PosOrderController::class, 'store'])->middleware('idempotent')->name('api.pos.orders.store');
        Route::get('/{id}/receipt', [PosReceiptController::class, 'show'])->whereNumber('id')->name('api.pos.orders.receipt');
        Route::post('/{id}/receipt/email', [PosReceiptController::class, 'email'])->whereNumber('id')->middleware('throttle:30,1')->name('api.pos.orders.receipt.email');
        // Phase D4: its invoice given the business's invoice number, and that invoice's PDF.
        Route::post('/{id}/formal-invoice', [PosFormalInvoiceController::class, 'store'])->whereNumber('id')->middleware('idempotent')->name('api.pos.orders.formal-invoice.store');
        Route::get('/{id}/formal-invoice', [PosFormalInvoiceController::class, 'show'])->whereNumber('id')->name('api.pos.orders.formal-invoice.show');
    });

    /* --------------------------------------------------------
     | Point of Sale (Doc 18): orders held at the user's own register
     | (Phase D1) - the list, a cart held, one as resuming would load it,
     | resumed and cancelled.
     | -------------------------------------------------------- */
    /* --------------------------------------------------------
     | Point of Sale (Doc 18): a sale's payment waiting to be confirmed
     | (Phase D2), confirmed - the customer payment it becomes - or declined.
     | -------------------------------------------------------- */
    Route::middleware(['auth:sanctum', 'workspace:required', 'app:pos', 'access'])->prefix('pos/order-payments')->group(function () {
        Route::post('/{id}/confirm', [PosPendingPaymentController::class, 'confirm'])->whereNumber('id')->middleware('idempotent')->name('api.pos.order-payments.confirm');
        Route::post('/{id}/decline', [PosPendingPaymentController::class, 'decline'])->whereNumber('id')->name('api.pos.order-payments.decline');
    });

    /* --------------------------------------------------------
     | Point of Sale (Doc 18): cash moved into and out of the drawer of the
     | user's own open session (Phase F1) - what recording one starts from,
     | one recorded - and one waiting for approval, approved or declined.
     | -------------------------------------------------------- */
    Route::middleware(['auth:sanctum', 'workspace:required', 'app:pos', 'access'])->prefix('pos/cash-movements')->group(function () {
        // Phase G1: the Cash Movements page.
        Route::get('/', [PosCashMovementController::class, 'list'])->name('api.pos.cash-movements.index');
        Route::get('/options', [PosCashMovementController::class, 'options'])->name('api.pos.cash-movements.options');
        Route::post('/', [PosCashMovementController::class, 'store'])->middleware('idempotent')->name('api.pos.cash-movements.store');
        Route::post('/{id}/approve', [PosCashMovementController::class, 'approve'])->whereNumber('id')->middleware('idempotent')->name('api.pos.cash-movements.approve');
        Route::post('/{id}/decline', [PosCashMovementController::class, 'decline'])->whereNumber('id')->name('api.pos.cash-movements.decline');
    });

    /* --------------------------------------------------------
     | Point of Sale (Doc 18): returns (Phase E1), in the user's own open
     | session - the sale one starts from, by its receipt number, what it
     | would credit, and the return recorded with its refund.
     | -------------------------------------------------------- */
    Route::middleware(['auth:sanctum', 'workspace:required', 'app:pos', 'access'])->prefix('pos/returns')->group(function () {
        // Phase G1: the Returns and Refunds page.
        Route::get('/', [PosReturnController::class, 'index'])->name('api.pos.returns.index');
        Route::get('/lookup', [PosReturnController::class, 'lookup'])->name('api.pos.returns.lookup');
        Route::get('/quote', [PosReturnController::class, 'quote'])->name('api.pos.returns.quote');
        Route::post('/', [PosReturnController::class, 'store'])->middleware('idempotent')->name('api.pos.returns.store');
        // Phase E2: its refund receipt, and a refund waiting to be confirmed - confirmed or declined.
        Route::get('/{id}/receipt', [PosReturnController::class, 'receipt'])->whereNumber('id')->name('api.pos.returns.receipt');
        Route::post('/{id}/refund/confirm', [PosPendingRefundController::class, 'confirm'])->whereNumber('id')->middleware('idempotent')->name('api.pos.returns.refund.confirm');
        Route::post('/{id}/refund/decline', [PosPendingRefundController::class, 'decline'])->whereNumber('id')->name('api.pos.returns.refund.decline');
    });

    Route::middleware(['auth:sanctum', 'workspace:required', 'app:pos', 'access'])->prefix('pos/held-orders')->group(function () {
        Route::get('/', [PosHeldOrderController::class, 'index'])->name('api.pos.held-orders.index');
        Route::post('/', [PosHeldOrderController::class, 'store'])->middleware('idempotent')->name('api.pos.held-orders.store');
        Route::get('/{id}', [PosHeldOrderController::class, 'show'])->whereNumber('id')->name('api.pos.held-orders.show');
        Route::post('/{id}/resume', [PosHeldOrderController::class, 'resume'])->whereNumber('id')->middleware('idempotent')->name('api.pos.held-orders.resume');
        Route::post('/{id}/cancel', [PosHeldOrderController::class, 'cancel'])->whereNumber('id')->name('api.pos.held-orders.cancel');
    });

    /* --------------------------------------------------------
     | eCommerce (Doc 19): its storefronts (Phase A1) - the Storefronts page,
     | what a storefront's form chooses from, adding, changing and deleting
     | one. Each is seen where its location may be used.
     | -------------------------------------------------------- */
    Route::middleware(['auth:sanctum', 'workspace:required', 'app:ecommerce', 'access'])->prefix('ecommerce/storefronts')->group(function () {
        Route::get('/', [StorefrontController::class, 'index'])->name('api.ecommerce.storefronts.index');
        Route::get('/options', [StorefrontController::class, 'options'])->name('api.ecommerce.storefronts.options');
        Route::post('/', [StorefrontController::class, 'store'])->middleware('idempotent')->name('api.ecommerce.storefronts.store');
        Route::put('/{id}', [StorefrontController::class, 'update'])->whereNumber('id')->name('api.ecommerce.storefronts.update');
        Route::delete('/{id}', [StorefrontController::class, 'destroy'])->whereNumber('id')->name('api.ecommerce.storefronts.destroy');
        // Phase A3: its domains - listed, added, verified, made primary and removed.
        Route::get('/{storefront}/domains', [StorefrontDomainController::class, 'index'])->whereNumber('storefront')->name('api.ecommerce.domains.index');
        Route::post('/{storefront}/domains', [StorefrontDomainController::class, 'store'])->whereNumber('storefront')->middleware('idempotent')->name('api.ecommerce.domains.store');
        Route::post('/{storefront}/domains/primary', [StorefrontDomainController::class, 'primary'])->whereNumber('storefront')->name('api.ecommerce.domains.primary');
        Route::post('/{storefront}/domains/{domain}/verify', [StorefrontDomainController::class, 'verify'])->whereNumber(['storefront', 'domain'])->middleware('throttle:20,1')->name('api.ecommerce.domains.verify');
        Route::delete('/{storefront}/domains/{domain}', [StorefrontDomainController::class, 'destroy'])->whereNumber(['storefront', 'domain'])->name('api.ecommerce.domains.destroy');
        // Phase C4a: its theme and branding - the draft, its pictures, publishing or discarding the draft.
        Route::get('/{storefront}/theme', [StorefrontThemeController::class, 'show'])->whereNumber('storefront')->name('api.ecommerce.themes.show');
        Route::put('/{storefront}/theme', [StorefrontThemeController::class, 'update'])->whereNumber('storefront')->name('api.ecommerce.themes.update');
        Route::post('/{storefront}/theme/pictures', [StorefrontThemeController::class, 'storePictures'])->whereNumber('storefront')->name('api.ecommerce.themes.pictures');
        Route::post('/{storefront}/theme/publish', [StorefrontThemeController::class, 'publish'])->whereNumber('storefront')->name('api.ecommerce.themes.publish');
        Route::post('/{storefront}/theme/discard', [StorefrontThemeController::class, 'discard'])->whereNumber('storefront')->name('api.ecommerce.themes.discard');
        // Phase C5a: its pages - contact, about and the four policies - each by its kind; Phase C5b, the business's own, by id.
        Route::get('/{storefront}/pages', [StorefrontPageController::class, 'index'])->whereNumber('storefront')->name('api.ecommerce.pages.index');
        Route::post('/{storefront}/pages', [StorefrontPageController::class, 'store'])->whereNumber('storefront')->name('api.ecommerce.pages.store');
        Route::get('/{storefront}/pages/{page}', [StorefrontPageController::class, 'show'])->whereNumber('storefront')->where('page', 'about|contact|shipping|returns|privacy|terms|[0-9]+')->name('api.ecommerce.pages.show');
        Route::put('/{storefront}/pages/{page}', [StorefrontPageController::class, 'update'])->whereNumber('storefront')->where('page', 'about|contact|shipping|returns|privacy|terms|[0-9]+')->name('api.ecommerce.pages.update');
        Route::delete('/{storefront}/pages/{page}', [StorefrontPageController::class, 'destroy'])->whereNumber('storefront')->whereNumber('page')->name('api.ecommerce.pages.destroy');
    });

    /* --------------------------------------------------------
     | eCommerce (Doc 19): its catalog (Phase B1) - the items for sale with
     | their online listings, an item's listing saved or taken out, and its
     | pictures.
     | -------------------------------------------------------- */
    Route::middleware(['auth:sanctum', 'workspace:required', 'app:ecommerce', 'access'])->prefix('ecommerce/catalog')->group(function () {
        Route::get('/', [EcommerceCatalogController::class, 'index'])->name('api.ecommerce.catalog.index');
        Route::get('/{item}', [EcommerceCatalogController::class, 'show'])->whereNumber('item')->name('api.ecommerce.catalog.show');
        Route::put('/{item}', [EcommerceCatalogController::class, 'update'])->whereNumber('item')->name('api.ecommerce.catalog.update');
        Route::delete('/{item}', [EcommerceCatalogController::class, 'destroy'])->whereNumber('item')->name('api.ecommerce.catalog.destroy');
        Route::post('/{item}/pictures', [CatalogPictureController::class, 'store'])->whereNumber('item')->name('api.ecommerce.catalog.pictures.store');
        Route::delete('/{item}/pictures/{file}', [CatalogPictureController::class, 'destroy'])->whereNumber(['item', 'file'])->name('api.ecommerce.catalog.pictures.destroy');
        // Phase B3: the items a listing may group as its variants, and its options and variants saved.
        Route::get('/{item}/variant-items', [CatalogVariantController::class, 'items'])->whereNumber('item')->name('api.ecommerce.catalog.variants.items');
        Route::put('/{item}/variants', [CatalogVariantController::class, 'update'])->whereNumber('item')->name('api.ecommerce.catalog.variants.update');
    });

    // Phase B2: the item categories as the storefronts show them, and the collections.
    Route::middleware(['auth:sanctum', 'workspace:required', 'app:ecommerce', 'access'])->prefix('ecommerce')->group(function () {
        Route::get('/catalog-categories', [CatalogCategoryController::class, 'index'])->name('api.ecommerce.categories.index');
        Route::put('/catalog-categories/{category}', [CatalogCategoryController::class, 'update'])->whereNumber('category')->name('api.ecommerce.categories.update');
        Route::post('/catalog-categories/{category}/image', [CatalogCategoryController::class, 'storeImage'])->whereNumber('category')->name('api.ecommerce.categories.image.store');
        Route::delete('/catalog-categories/{category}/image', [CatalogCategoryController::class, 'destroyImage'])->whereNumber('category')->name('api.ecommerce.categories.image.destroy');
        Route::get('/collections', [CatalogCollectionController::class, 'index'])->name('api.ecommerce.collections.index');
        Route::get('/collections/options', [CatalogCollectionController::class, 'options'])->name('api.ecommerce.collections.options');
        Route::post('/collections', [CatalogCollectionController::class, 'store'])->middleware('idempotent')->name('api.ecommerce.collections.store');
        Route::put('/collections/{id}', [CatalogCollectionController::class, 'update'])->whereNumber('id')->name('api.ecommerce.collections.update');
        Route::delete('/collections/{id}', [CatalogCollectionController::class, 'destroy'])->whereNumber('id')->name('api.ecommerce.collections.destroy');
        Route::post('/collections/{id}/image', [CatalogCollectionController::class, 'storeImage'])->whereNumber('id')->name('api.ecommerce.collections.image.store');
        Route::delete('/collections/{id}/image', [CatalogCollectionController::class, 'destroyImage'])->whereNumber('id')->name('api.ecommerce.collections.image.destroy');
    });

    // Phase F1: the business's online orders - the Orders page, one order, and its timeline.
    Route::middleware(['auth:sanctum', 'workspace:required', 'app:ecommerce', 'access'])->prefix('ecommerce/orders')->group(function () {
        Route::get('/', [EcommerceOrderController::class, 'index'])->name('api.ecommerce.orders.index');
        Route::get('/{id}', [EcommerceOrderController::class, 'show'])->whereNumber('id')->name('api.ecommerce.orders.show');
        Route::get('/{id}/activity', RecordActivityController::class)->whereNumber('id')->defaults('family', 'ecommerce_orders')->name('api.ecommerce.orders.activity');
        // Phase F2: confirming it - what confirming would do, then confirming it, its invoice issued.
        Route::get('/{id}/confirmation', [EcommerceOrderController::class, 'confirmation'])->whereNumber('id')->name('api.ecommerce.orders.confirmation');
        Route::post('/{id}/confirm', [EcommerceOrderController::class, 'confirm'])->whereNumber('id')->middleware('idempotent')->name('api.ecommerce.orders.confirm');
        // Phase F3: its payment - what recording one would do, recording it, and marking it failed.
        Route::get('/{id}/payment', [EcommerceOrderPaymentController::class, 'show'])->whereNumber('id')->name('api.ecommerce.orders.payment');
        Route::post('/{id}/payments', [EcommerceOrderPaymentController::class, 'store'])->whereNumber('id')->middleware('idempotent')->name('api.ecommerce.orders.payments.store');
        Route::post('/{id}/payment-failed', [EcommerceOrderPaymentController::class, 'fail'])->whereNumber('id')->middleware('idempotent')->name('api.ecommerce.orders.payment-failed');
        // Phase F4: held for review, released, and cancelled - each with a reason.
        Route::post('/{id}/hold', [EcommerceOrderStatusController::class, 'hold'])->whereNumber('id')->middleware('idempotent')->name('api.ecommerce.orders.hold');
        Route::post('/{id}/release', [EcommerceOrderStatusController::class, 'release'])->whereNumber('id')->middleware('idempotent')->name('api.ecommerce.orders.release');
        Route::post('/{id}/cancel', [EcommerceOrderStatusController::class, 'cancel'])->whereNumber('id')->middleware('idempotent')->name('api.ecommerce.orders.cancel');
        // Phase F5a: edited before payment - its customer's details, and its addresses and delivery (with what an address would offer).
        Route::put('/{id}/customer', [EcommerceOrderEditController::class, 'customer'])->whereNumber('id')->name('api.ecommerce.orders.customer');
        Route::post('/{id}/delivery/quote', [EcommerceOrderEditController::class, 'quote'])->whereNumber('id')->name('api.ecommerce.orders.delivery.quote');
        Route::put('/{id}/delivery', [EcommerceOrderEditController::class, 'delivery'])->whereNumber('id')->name('api.ecommerce.orders.delivery');
        // Phase F5b: its products - with what its customer could be sold - and its discount.
        Route::get('/{id}/products/offered', [EcommerceOrderEditController::class, 'offered'])->whereNumber('id')->name('api.ecommerce.orders.products.offered');
        Route::put('/{id}/products', [EcommerceOrderEditController::class, 'products'])->whereNumber('id')->name('api.ecommerce.orders.products');
        Route::put('/{id}/discount', [EcommerceOrderEditController::class, 'discount'])->whereNumber('id')->name('api.ecommerce.orders.discount');
        // Phase F6a: its payment links - what sending one would ask, sending it, and revoking one still active.
        Route::get('/{id}/payment-link', [EcommercePaymentLinkController::class, 'review'])->whereNumber('id')->name('api.ecommerce.orders.payment-link');
        Route::post('/{id}/payment-links', [EcommercePaymentLinkController::class, 'send'])->whereNumber('id')->middleware('idempotent')->name('api.ecommerce.orders.payment-links.store');
        Route::post('/{id}/payment-links/{link}/revoke', [EcommercePaymentLinkController::class, 'revoke'])->whereNumber(['id', 'link'])->name('api.ecommerce.orders.payment-links.revoke');
        // Phase G1: its shipments and pickup - what fulfilling it would do, a shipment, readiness for pickup, and one corrected, completed or cancelled.
        Route::get('/{id}/fulfilment', [EcommerceOrderFulfilmentController::class, 'review'])->whereNumber('id')->name('api.ecommerce.orders.fulfilment');
        Route::post('/{id}/shipments', [EcommerceOrderFulfilmentController::class, 'ship'])->whereNumber('id')->middleware('idempotent')->name('api.ecommerce.orders.shipments.store');
        Route::post('/{id}/pickup', [EcommerceOrderFulfilmentController::class, 'ready'])->whereNumber('id')->middleware('idempotent')->name('api.ecommerce.orders.pickup');
        Route::put('/{id}/fulfilments/{fulfilment}', [EcommerceOrderFulfilmentController::class, 'update'])->whereNumber(['id', 'fulfilment'])->name('api.ecommerce.orders.fulfilments.update');
        Route::post('/{id}/fulfilments/{fulfilment}/complete', [EcommerceOrderFulfilmentController::class, 'complete'])->whereNumber(['id', 'fulfilment'])->middleware('idempotent')->name('api.ecommerce.orders.fulfilments.complete');
        Route::post('/{id}/fulfilments/{fulfilment}/cancel', [EcommerceOrderFulfilmentController::class, 'cancel'])->whereNumber(['id', 'fulfilment'])->middleware('idempotent')->name('api.ecommerce.orders.fulfilments.cancel');
        // Phase G2: a shipment's packing slip.
        Route::get('/{id}/fulfilments/{fulfilment}/pdf', [EcommerceOrderFulfilmentController::class, 'packingSlip'])->whereNumber(['id', 'fulfilment'])->name('api.ecommerce.orders.fulfilments.pdf');
        // Phase G3a: a digital product delivered or its access revoked, and a service performed.
        Route::post('/{id}/lines/{line}/deliver', [EcommerceOrderDeliveryController::class, 'deliver'])->whereNumber(['id', 'line'])->middleware('idempotent')->name('api.ecommerce.orders.lines.deliver');
        Route::post('/{id}/lines/{line}/perform', [EcommerceOrderDeliveryController::class, 'perform'])->whereNumber(['id', 'line'])->middleware('idempotent')->name('api.ecommerce.orders.lines.perform');
        Route::post('/{id}/lines/{line}/revoke', [EcommerceOrderDeliveryController::class, 'revoke'])->whereNumber(['id', 'line'])->middleware('idempotent')->name('api.ecommerce.orders.lines.revoke');
    });

    Route::middleware(['auth:sanctum', 'workspace:required', 'app:crm', 'access'])->prefix('crm')->group(function () {
        Route::get('/overview', [CrmOverviewController::class, 'show'])->name('api.crm.overview');
        Route::get('/forecast', [CrmForecastController::class, 'show'])->name('api.crm.forecast');
    });

    /* --------------------------------------------------------
     | CRM (Doc 17): leads (Phase B1) - the Leads page, what a lead's
     | form chooses from, one lead, adding and changing one, and its steps.
     | Each user sees the leads LeadVisibility gives them, only at the
     | locations they may use. Working with opportunities only, a business
     | reads its leads but changes none (crm.leads).
     | -------------------------------------------------------- */
    Route::middleware(['auth:sanctum', 'workspace:required', 'app:crm', 'access', 'crm.leads'])->prefix('crm/leads')->group(function () {
        Route::get('/', [CrmLeadController::class, 'index'])->name('api.crm.leads.index');
        Route::get('/options', [CrmLeadController::class, 'options'])->name('api.crm.leads.options');
        Route::post('/', [CrmLeadController::class, 'store'])->middleware('idempotent')->name('api.crm.leads.store');
        Route::get('/{id}', [CrmLeadController::class, 'show'])->whereNumber('id')->name('api.crm.leads.show');
        Route::put('/{id}', [CrmLeadController::class, 'update'])->whereNumber('id')->name('api.crm.leads.update');

        foreach (['assign', 'contact', 'qualify', 'unqualify', 'archive', 'reopen'] as $step) {
            Route::post("/{id}/{$step}", [CrmLeadFlowController::class, $step])->whereNumber('id')->name("api.crm.leads.{$step}");
        }

        // Phase I1: a lead in the queue sent through the assignment rules.
        Route::post('/{id}/assign-by-rules', [CrmLeadFlowController::class, 'assignByRules'])->whereNumber('id')->name('api.crm.leads.assign-by-rules');

        // Converting a qualified lead into a contact and an opportunity (Phase C1).
        Route::get('/{id}/conversion', [CrmLeadConversionController::class, 'show'])->whereNumber('id')->name('api.crm.leads.conversion');
        Route::post('/{id}/convert', [CrmLeadConversionController::class, 'store'])->whereNumber('id')->middleware('idempotent')->name('api.crm.leads.convert');

        // Its possible duplicates, and a decision about each (Phase C2).
        Route::get('/{id}/duplicates', [CrmLeadDuplicateController::class, 'index'])->whereNumber('id')->name('api.crm.leads.duplicates.index');
        foreach (['ignore', 'link', 'clear'] as $decision) {
            Route::post("/{id}/duplicates/{$decision}", [CrmLeadDuplicateController::class, $decision])->whereNumber('id')->name("api.crm.leads.duplicates.{$decision}");
        }

        // Its files, comments and activity timeline (Phase B2b), Doc 14's.
        Route::get('/{id}/attachments', [CrmLeadAttachmentController::class, 'index'])->whereNumber('id')->name('api.crm.leads.attachments.index');
        Route::post('/{id}/attachments', [CrmLeadAttachmentController::class, 'store'])->whereNumber('id')->name('api.crm.leads.attachments.store');
        Route::delete('/{id}/attachments/{fileId}', [CrmLeadAttachmentController::class, 'destroy'])->whereNumber(['id', 'fileId'])->name('api.crm.leads.attachments.destroy');
        Route::get('/{id}/activity', RecordActivityController::class)->whereNumber('id')->defaults('family', 'crm')->name('api.crm.leads.activity');
        Route::get('/{id}/comments', [RecordCommentController::class, 'index'])->whereNumber('id')->defaults('family', 'crm')->name('api.crm.leads.comments.index');
        Route::get('/{id}/comments/people', [RecordCommentController::class, 'people'])->whereNumber('id')->defaults('family', 'crm')->name('api.crm.leads.comments.people');
        Route::post('/{id}/comments', [RecordCommentController::class, 'store'])->whereNumber('id')->defaults('family', 'crm')->middleware('idempotent')->name('api.crm.leads.comments.store');
        Route::delete('/{id}/comments/{commentId}', [RecordCommentController::class, 'destroy'])->whereNumber(['id', 'commentId'])->defaults('family', 'crm')->name('api.crm.leads.comments.destroy');

        // Its marketing consent, apart from sales follow-up (Phase I3).
        Route::get('/{id}/consents', [CrmConsentController::class, 'index'])->whereNumber('id')->defaults('kind', 'lead')->name('api.crm.leads.consents.index');
        Route::post('/{id}/consents', [CrmConsentController::class, 'store'])->whereNumber('id')->defaults('kind', 'lead')->middleware('idempotent')->name('api.crm.leads.consents.store');
        Route::post('/{id}/consents/{consentId}/withdraw', [CrmConsentController::class, 'withdraw'])->whereNumber(['id', 'consentId'])->defaults('kind', 'lead')->name('api.crm.leads.consents.withdraw');
    });

    /* ----------------------------------------------------------
     | CRM's opportunities (Doc 17 Phase D1), in either way of working:
     | the Opportunities page, one opportunity, adding and changing one,
     | and moving it to another stage.
     | -------------------------------------------------------- */
    Route::middleware(['auth:sanctum', 'workspace:required', 'app:crm', 'access'])->prefix('crm/opportunities')->group(function () {
        Route::get('/', [CrmOpportunityController::class, 'index'])->name('api.crm.opportunities.index');
        Route::get('/options', [CrmOpportunityController::class, 'options'])->name('api.crm.opportunities.options');
        // Phase D4: one pipeline's board, and a month's calendar.
        Route::get('/board', [CrmOpportunityViewController::class, 'board'])->name('api.crm.opportunities.board');
        Route::get('/calendar', [CrmOpportunityViewController::class, 'calendar'])->name('api.crm.opportunities.calendar');
        Route::post('/', [CrmOpportunityController::class, 'store'])->middleware('idempotent')->name('api.crm.opportunities.store');
        Route::get('/{id}', [CrmOpportunityController::class, 'show'])->whereNumber('id')->name('api.crm.opportunities.show');
        Route::put('/{id}', [CrmOpportunityController::class, 'update'])->whereNumber('id')->name('api.crm.opportunities.update');
        Route::post('/{id}/stage', [CrmOpportunityFlowController::class, 'stage'])->whereNumber('id')->name('api.crm.opportunities.stage');
        // How it ends (Phase D2a): won or lost, reopened, archived and restored.
        Route::post('/{id}/win', [CrmOpportunityClosingController::class, 'win'])->whereNumber('id')->name('api.crm.opportunities.win');
        Route::post('/{id}/lose', [CrmOpportunityClosingController::class, 'lose'])->whereNumber('id')->name('api.crm.opportunities.lose');
        Route::post('/{id}/reopen', [CrmOpportunityClosingController::class, 'reopen'])->whereNumber('id')->name('api.crm.opportunities.reopen');
        Route::post('/{id}/archive', [CrmOpportunityFlowController::class, 'archive'])->whereNumber('id')->name('api.crm.opportunities.archive');
        Route::post('/{id}/restore', [CrmOpportunityFlowController::class, 'restore'])->whereNumber('id')->name('api.crm.opportunities.restore');

        // Its files, comments and activity timeline (Phase D2b), Doc 14's.
        Route::get('/{id}/attachments', [CrmOpportunityAttachmentController::class, 'index'])->whereNumber('id')->name('api.crm.opportunities.attachments.index');
        Route::post('/{id}/attachments', [CrmOpportunityAttachmentController::class, 'store'])->whereNumber('id')->name('api.crm.opportunities.attachments.store');
        Route::delete('/{id}/attachments/{fileId}', [CrmOpportunityAttachmentController::class, 'destroy'])->whereNumber(['id', 'fileId'])->name('api.crm.opportunities.attachments.destroy');
        Route::get('/{id}/activity', RecordActivityController::class)->whereNumber('id')->defaults('family', 'opportunities')->name('api.crm.opportunities.activity');
        // The sales documents made from it (Phase F1).
        Route::get('/{id}/documents', CrmOpportunityDocumentController::class)->whereNumber('id')->name('api.crm.opportunities.documents');
        Route::get('/{id}/comments', [RecordCommentController::class, 'index'])->whereNumber('id')->defaults('family', 'opportunities')->name('api.crm.opportunities.comments.index');
        Route::get('/{id}/comments/people', [RecordCommentController::class, 'people'])->whereNumber('id')->defaults('family', 'opportunities')->name('api.crm.opportunities.comments.people');
        Route::post('/{id}/comments', [RecordCommentController::class, 'store'])->whereNumber('id')->defaults('family', 'opportunities')->middleware('idempotent')->name('api.crm.opportunities.comments.store');
        Route::delete('/{id}/comments/{commentId}', [RecordCommentController::class, 'destroy'])->whereNumber(['id', 'commentId'])->defaults('family', 'opportunities')->name('api.crm.opportunities.comments.destroy');
    });

    /*
     | CRM's activities (Doc 17 Phase E1): the Activities page, a lead's, an
     | opportunity's or a contact's, and planning, changing, completing and
     | cancelling one. A record's are seen by whoever sees the record.
     */
    Route::middleware(['auth:sanctum', 'workspace:required', 'app:crm', 'access'])->prefix('crm/activities')->group(function () {
        Route::get('/', [CrmActivityController::class, 'index'])->name('api.crm.activities.index');
        Route::get('/options', [CrmActivityController::class, 'options'])->name('api.crm.activities.options');
        Route::get('/regarding', [CrmActivityController::class, 'regarding'])->name('api.crm.activities.regarding');
        Route::get('/for/{kind}/{id}', [CrmActivityController::class, 'forRecord'])->where('kind', 'lead|opportunity|contact')->whereNumber('id')->name('api.crm.activities.for');
        Route::post('/', [CrmActivityController::class, 'store'])->middleware('idempotent')->name('api.crm.activities.store');
        Route::put('/{id}', [CrmActivityController::class, 'update'])->whereNumber('id')->name('api.crm.activities.update');
        Route::post('/{id}/complete', [CrmActivityController::class, 'complete'])->whereNumber('id')->name('api.crm.activities.complete');
        Route::post('/{id}/cancel', [CrmActivityController::class, 'cancel'])->whereNumber('id')->name('api.crm.activities.cancel');
    });

    /*
     | The timeline of a lead, an opportunity or a contact (Doc 17 Phase E3):
     | its activities done, notes, changes, files and a contact's sales
     | documents, newest first, for whoever sees the record.
     */
    Route::middleware(['auth:sanctum', 'workspace:required', 'app:crm', 'access'])->group(function () {
        Route::get('/crm/timeline/{kind}/{id}', CrmTimelineController::class)->where('kind', 'lead|opportunity|contact')->whereNumber('id')->name('api.crm.timeline');
        // Phase F4: the CRM part of a contact's profile - where they came from, and their opportunities.
        Route::get('/crm/contacts/{id}/summary', CrmContactSummaryController::class)->whereNumber('id')->name('api.crm.contacts.summary');
        // Phase I3: a contact's marketing consent, apart from sales follow-up.
        Route::get('/crm/contacts/{id}/consents', [CrmConsentController::class, 'index'])->whereNumber('id')->defaults('kind', 'contact')->name('api.crm.contacts.consents.index');
        Route::post('/crm/contacts/{id}/consents', [CrmConsentController::class, 'store'])->whereNumber('id')->defaults('kind', 'contact')->middleware('idempotent')->name('api.crm.contacts.consents.store');
        Route::post('/crm/contacts/{id}/consents/{consentId}/withdraw', [CrmConsentController::class, 'withdraw'])->whereNumber(['id', 'consentId'])->defaults('kind', 'contact')->name('api.crm.contacts.consents.withdraw');
    });

    /*
     | E-mail from a lead, an opportunity or a contact (Doc 17 Phase F3a): what
     | its form starts with, and sending it - through the queue, kept on the
     | record's timeline.
     */
    Route::middleware(['auth:sanctum', 'workspace:required', 'app:crm', 'access'])->prefix('crm/emails')->group(function () {
        Route::get('/draft/{kind}/{id}', [CrmEmailController::class, 'draft'])->where('kind', 'lead|opportunity|contact')->whereNumber('id')->name('api.crm.emails.draft');
        Route::post('/', [CrmEmailController::class, 'store'])->middleware(['throttle:30,1', 'idempotent'])->name('api.crm.emails.store');
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
    Route::middleware(['auth:sanctum', 'workspace', 'access'])->group(function () {
        Route::get('/navigation', NavigationController::class)->name('api.navigation');
        Route::get('/apps-center', AppsCenterController::class)->name('api.apps-center');
        // An app's own page, and what turning one off would do (Doc 13 sections 6 and 8).
        Route::get('/apps-center/{family}', AppDetailsController::class)->where('family', '[a-z0-9][a-z0-9_-]*')->name('api.apps-center.show');
        Route::get('/apps/{appKey}/deactivation', [AppActivationController::class, 'impact'])->name('api.apps.deactivation');
        Route::post('/apps/{appKey}/activate', [AppActivationController::class, 'activate'])->name('api.apps.activate');
        Route::post('/apps/{appKey}/deactivate', [AppActivationController::class, 'deactivate'])->name('api.apps.deactivate');
    });
});
