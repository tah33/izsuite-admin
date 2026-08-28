<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SwitchAdminCurrencyRequest;
use App\Http\Requests\Admin\SwitchAdminLanguageRequest;
use App\Models\Admin\Currency;
use App\Models\Admin\Language;
use App\Services\Admin\OverviewService;
use Illuminate\Support\Facades\Artisan;

class OverviewController extends Controller
{
    public function __construct(
        protected OverviewService $overviewService,
    ) {}

    /**
     * Show admin overview dashboard.
     */
    public function index()
    {
        try {
            $data = $this->overviewService->getOverviewData();

            return view('admin.overview.index', $data);

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
            Artisan::call('optimize:clear');

            return back()->with('success', 'Application cache cleared successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to clear cache: '.$e->getMessage());
        }
    }

    /**
     * Switch admin panel language.
     */
    public function switchLanguage(SwitchAdminLanguageRequest $request)
    {
        try {
            $language = Language::where('code', $request->locale)->where('is_active', true)->first();
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
            $currency = Currency::where('code', $request->currency)->where('is_active', true)->first();
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
