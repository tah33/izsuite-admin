<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListSubscriptionTaxesRequest;
use App\Http\Requests\Admin\SaveSubscriptionTaxRequest;
use App\Services\Admin\SubscriptionTaxService;
use App\Support\Countries;

/**
 * izSuite's subscription taxes (Doc 15 section 16 - Doc 13 Phase D1): one
 * rule per country from a day on, added to businesses' subscription invoices
 * - separate from the businesses' own taxes.
 */
class SubscriptionTaxController extends Controller
{
    public function __construct(
        protected SubscriptionTaxService $taxService,
    ) {}

    public function index(ListSubscriptionTaxesRequest $request)
    {
        try {
            [$taxes, $inForce] = $this->taxService->paginate(trim((string) $request->validated('search')), requested_per_page());

            return view('admin.subscription-taxes.index', compact('taxes', 'inForce'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function create()
    {
        try {
            return view('admin.subscription-taxes.create', ['countries' => Countries::all()]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveSubscriptionTaxRequest $request)
    {
        try {
            $this->taxService->create($request->validated());

            return redirect()->route('admin.subscription-taxes.index')
                ->with('success', 'Subscription tax added. Invoices issued from its start date carry it.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function edit(int $id)
    {
        try {
            return view('admin.subscription-taxes.edit', ['tax' => $this->taxService->find($id), 'countries' => Countries::all()]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveSubscriptionTaxRequest $request, int $id)
    {
        try {
            $this->taxService->update($this->taxService->find($id), $request->validated());

            return redirect()->route('admin.subscription-taxes.index')
                ->with('success', 'Subscription tax updated. Invoices already issued keep the tax they had.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(int $id)
    {
        try {
            $this->taxService->delete($this->taxService->find($id));

            return redirect()->route('admin.subscription-taxes.index')
                ->with('success', 'Subscription tax deleted. Invoices already issued keep the tax they had.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
