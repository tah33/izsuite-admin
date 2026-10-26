<?php

namespace App\Services\Admin;

use App\Models\Billing\Plan;
use App\Repositories\Admin\PaymentMethodRepository;
use App\Repositories\Admin\PlanRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlanService
{
    public function __construct(
        protected PlanRepository $planRepo,
        protected PaymentMethodRepository $paymentMethodRepo,
    ) {}

    /**
     * Get paginated plans.
     */
    public function getPaginated(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->planRepo->getPaginated($search, $perPage);
    }

    /**
     * Get active plans.
     */
    public function getActive(): Collection
    {
        return $this->planRepo->getActive();
    }

    /**
     * Find a plan by ID.
     */
    public function find(int $id): ?Plan
    {
        return $this->planRepo->find($id);
    }

    /**
     * Create a plan from the form: $data as SavePlanRequest validates it, with
     * is_active and is_featured read from their boxes.
     */
    public function create(array $data): Plan
    {
        [$data, $providers] = $this->fromForm($data);

        $plan = $this->planRepo->create($data);

        if (! empty($providers)) {
            $this->syncPaymentProviders($plan, $providers);
        }

        ActivityLogService::record('created', "Created plan \"{$plan->name}\"", $plan);

        return $plan;
    }

    /**
     * Update a plan from the form, the same way.
     */
    public function update(Plan $plan, array $data): Plan
    {
        [$data, $providers] = $this->fromForm($data);

        $updated = $this->planRepo->update($plan, $data);

        $this->syncPaymentProviders($plan, $providers);

        ActivityLogService::record('updated', "Updated plan \"{$plan->name}\"", $plan);

        return $updated;
    }

    /**
     * Delete a plan.
     */
    public function delete(Plan $plan): bool
    {
        $name    = $plan->name;
        $deleted = $this->planRepo->delete($plan);

        ActivityLogService::record('deleted', "Deleted plan \"{$name}\"");

        return $deleted;
    }

    /**
     * Why $plan cannot be deleted, if it cannot.
     */
    public function deletionProblem(Plan $plan): ?string
    {
        return $plan->subscriptions_count > 0 ? 'Cannot delete a plan that has users subscribed.' : null;
    }

    /**
     * The online payment methods a plan can name a price for: the active ones
     * this install has a gateway driver for.
     */
    public function supportedOnlineGateways(): Collection
    {
        return $this->paymentMethodRepo->getSupportedOnline(
            array_keys(config('payment_gateways.drivers', []))
        );
    }

    /**
     * Sync payment provider entries for a plan.
     */
    public function syncPaymentProviders(Plan $plan, array $providers): void
    {
        $this->planRepo->deletePaymentProviders($plan);

        foreach ($providers as $entry) {
            if (filled($entry['provider_price_id'] ?? null)) {
                $this->planRepo->addPaymentProvider($plan, $entry);
            }
        }
    }

    /**
     * The plan's columns from the form - its slug, its defaults, its prices
     * and its list of features - and, apart, the payment providers for its
     * billing type.
     *
     * @return array{0: array, 1: array}
     */
    private function fromForm(array $data): array
    {
        $data['slug']       = Str::slug($data['name']);
        $data['trial_days'] = $data['trial_days'] ?? 0;
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data               = $this->normalizePrices($data);
        $data['features']   = $this->parseFeatures($data['features'] ?? null);
        $providers          = $this->filterProvidersForBillingType($data['providers'] ?? [], $data['billing_type']);
        unset($data['providers']);

        return [$data, $providers];
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
}
