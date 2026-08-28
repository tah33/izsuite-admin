<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCurrencyFormattingRequest;
use App\Http\Requests\Admin\StoreCurrencyRequest;
use App\Http\Requests\Admin\UpdateCurrencyRequest;
use App\Services\Shared\ActivityLogService;
use App\Services\Admin\CurrencyService;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function __construct(
        protected CurrencyService $currencyService,
    ) {}

    public function index(Request $request)
    {
        try {
            $currencies = $this->currencyService->getPaginated($request->input('search'));

            return view('admin.currencies.index', compact('currencies'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function create()
    {
        try {
            return view('admin.currencies.create');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(StoreCurrencyRequest $request)
    {
        try {
            $validated               = $request->validated();

            $validated['is_active']  = $request->boolean('is_active');
            $validated['is_default'] = $request->boolean('is_default');

            $currency                = $this->currencyService->create($validated);

            ActivityLogService::record('created', "Created currency \"{$currency->name}\"", $currency);

            return redirect()->route('admin.currencies.index')
                ->with('success', 'Currency created.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function edit(int $id)
    {
        try {
            $currency = $this->currencyService->find($id);
            abort_unless($currency, 404);

            return view('admin.currencies.edit', compact('currency'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(UpdateCurrencyRequest $request, int $id)
    {
        try {
            $currency                = $this->currencyService->find($id);
            abort_unless($currency, 404);

            $validated               = $request->validated();

            $validated['is_active']  = $request->boolean('is_active');
            $validated['is_default'] = $request->boolean('is_default');

            $this->currencyService->update($currency, $validated);

            ActivityLogService::record('updated', "Updated currency \"{$currency->name}\"", $currency);

            return redirect()->route('admin.currencies.index')
                ->with('success', 'Currency updated.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(int $id)
    {
        try {
            $currency = $this->currencyService->find($id);
            abort_unless($currency, 404);

            if ($currency->is_default) {
                return back()->with('error', 'Cannot delete the default currency.');
            }

            $name     = $currency->name;
            $this->currencyService->delete($currency);

            ActivityLogService::record('deleted', "Deleted currency \"{$name}\"");

            return redirect()->route('admin.currencies.index')
                ->with('success', 'Currency deleted.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Save currency formatting settings.
     */
    public function saveFormatting(SaveCurrencyFormattingRequest $request)
    {
        try {
            $data = $request->validated();

            setting($data);

            ActivityLogService::record('updated', 'Updated currency formatting settings');

            return redirect()->route('admin.currencies.index')
                ->with('success', 'Formatting settings saved.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
