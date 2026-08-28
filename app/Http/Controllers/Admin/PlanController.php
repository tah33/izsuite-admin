<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePlanRequest;
use App\Repositories\Admin\PaymentMethodRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\Admin\PlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlanController extends Controller
{
    public function __construct(
        protected PlanService $planService,
        protected PaymentMethodRepository $paymentMethodRepository,
    ) {}

    /**
     * Display plan listing.
     */
    public function index(Request $request)
    {
        try {
            $plans = $this->planService->getPaginated($request->input('search'));

            return view('admin.plans.index', compact('plans'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Show create form.
     */
    public function create()
    {
        try {
            $onlineGateways = $this->supportedOnlineGateways();

            return view('admin.plans.create', compact('onlineGateways'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Store a new plan.
     */
    public function store(SavePlanRequest $request)
    {
        try {
            $validated                = $request->validated();

            $validated['slug']        = Str::slug($validated['name']);
            $validated['is_active']   = $request->boolean('is_active');
            $validated['is_featured'] = $request->boolean('is_featured');
            $validated['trial_days']  = $validated['trial_days'] ?? 0;
            $validated['sort_order']  = $validated['sort_order'] ?? 0;
            $validated                = $this->normalizePrices($validated);
            $validated['features']    = $this->parseFeatures($request->input('features'));

            $providers                = $this->filterProvidersForBillingType($validated['providers'] ?? [], $validated['billing_type']);
            unset($validated['providers']);

            $plan                     = $this->planService->create($validated);

            if (! empty($providers)) {
                $this->planService->syncPaymentProviders($plan, $providers);
            }

            ActivityLogService::record('created', "Created plan \"{$plan->name}\"", $plan);

            return redirect()->route('admin.plans.index')
                ->with('success', 'Plan created successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Show edit form.
     */
    public function edit(int $id)
    {
        try {
            $plan           = $this->planService->find($id);
            $onlineGateways = $this->supportedOnlineGateways();
            abort_unless($plan, 404);

            return view('admin.plans.edit', compact('plan', 'onlineGateways'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Update a plan.
     */
    public function update(SavePlanRequest $request, int $id)
    {
        try {
            $plan                     = $this->planService->find($id);
            abort_unless($plan, 404);

            $validated                = $request->validated();

            $validated['slug']        = Str::slug($validated['name']);
            $validated['is_active']   = $request->boolean('is_active');
            $validated['is_featured'] = $request->boolean('is_featured');
            $validated['trial_days']  = $validated['trial_days'] ?? 0;
            $validated['sort_order']  = $validated['sort_order'] ?? 0;
            $validated                = $this->normalizePrices($validated);
            $validated['features']    = $this->parseFeatures($request->input('features'));

            $providers                = $this->filterProvidersForBillingType($validated['providers'] ?? [], $validated['billing_type']);
            unset($validated['providers']);

            $this->planService->update($plan, $validated);
            $this->planService->syncPaymentProviders($plan, $providers);

            ActivityLogService::record('updated', "Updated plan \"{$plan->name}\"", $plan);

            return redirect()->route('admin.plans.index')
                ->with('success', 'Plan updated successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Delete a plan.
     */
    public function destroy(int $id)
    {
        try {
            $plan = $this->planService->find($id);
            abort_unless($plan, 404);

            if ($plan->subscriptions_count > 0) {
                return back()->with('error', 'Cannot delete a plan that has users subscribed.');
            }

            $name = $plan->name;
            $this->planService->delete($plan);

            ActivityLogService::record('deleted', "Deleted plan \"{$name}\"");

            return redirect()->route('admin.plans.index')
                ->with('success', 'Plan deleted successfully.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Parse comma- or newline-separated features string into an array.
     */
    private function parseFeatures(?string $raw): ?array
    {
        if (! $raw) {
            return null;
        }

        return array_values(array_filter(
            array_map('trim', preg_split('/[\n,]+/', $raw))
        ));
    }

    private function normalizePrices(array $validated): array
    {
        $billingType                = $validated['billing_type'];
        $priceField                 = "{$billingType}_price";

        if (! isset($validated[$priceField]) || $validated[$priceField] === '') {
            throw ValidationException::withMessages([
                $priceField => [ucfirst($billingType).' price is required for this plan type.'],
            ]);
        }

        $validated['monthly_price'] = $validated['monthly_price'] ?? 0;
        $validated['yearly_price']  = $validated['yearly_price'] ?? 0;

        return $validated;
    }

    private function filterProvidersForBillingType(array $providers, string $billingType): array
    {
        return array_values(array_filter(
            $providers,
            fn (array $provider) => ($provider['interval'] ?? null) === $billingType
        ));
    }

    private function supportedOnlineGateways()
    {
        return $this->paymentMethodRepository->getSupportedOnline(
            array_keys(config('payment_gateways.drivers', []))
        );
    }
}
