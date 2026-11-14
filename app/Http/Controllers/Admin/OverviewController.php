<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OverviewRequest;
use App\Http\Requests\Admin\SwitchAdminCurrencyRequest;
use App\Http\Requests\Admin\SwitchAdminLanguageRequest;
use App\Services\Admin\CurrencyService;
use App\Services\Admin\LanguageService;
use App\Services\Admin\OverviewService;

class OverviewController extends Controller
{
    public function __construct(
        protected OverviewService $overviewService,
        protected LanguageService $languageService,
        protected CurrencyService $currencyService,
    ) {}

    /**
     * Admin > Overview (Doc 15 section 15.4 - Phase D1): the platform's figures, for the filters chosen.
     */
    public function index(OverviewRequest $request)
    {
        try {
            return view('admin.overview.index', $this->overviewService->dashboard($request->validated(), $request->user()));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Clear application cache.
     */
    public function clearCache()
    {
        try {
            if ($problem = $this->overviewService->clearCache()) {
                return back()->with('error', $problem);
            }

            return back()->with('success', 'Application cache cleared successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Switch admin panel language.
     */
    public function switchLanguage(SwitchAdminLanguageRequest $request)
    {
        try {
            $language = $this->languageService->findActiveByCode($request->locale);
            if ($language) {
                session(['admin_locale' => $language->code]);
            }

            return back();

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Switch admin panel currency.
     */
    public function switchCurrency(SwitchAdminCurrencyRequest $request)
    {
        try {
            $currency = $this->currencyService->findActiveByCode($request->currency);
            if ($currency) {
                session(['admin_currency' => $currency->code]);
            }

            return back();

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
