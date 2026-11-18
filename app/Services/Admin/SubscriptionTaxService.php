<?php

namespace App\Services\Admin;

use App\Models\Billing\SubscriptionTax;
use App\Repositories\Billing\SubscriptionTaxRepository;
use App\Services\Shared\ActivityLogService;
use App\Support\Countries;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * izSuite's subscription tax rules (Doc 15 section 16 - Doc 13 Phase D1), as
 * the admin keeps them: one per country from a day on, a later one taking
 * over from its day. A change applies to invoices issued after it; issued
 * ones keep their tax.
 */
class SubscriptionTaxService
{
    public function __construct(
        protected SubscriptionTaxRepository $taxes,
    ) {}

    /**
     * A page of the rules - $search matching a country's name or code - with
     * which of them is in force today, by id.
     *
     * @return array{0: LengthAwarePaginator, 1: list<int>}
     */
    public function paginate(string $search, int $perPage): array
    {
        $term      = mb_strtolower($search);
        $countries = $term === '' ? null : array_values(array_filter(
            Countries::codes(),
            fn (string $code) => str_contains(mb_strtolower(Countries::name($code).' '.$code), $term),
        ));

        $page      = $this->taxes->paginate($countries, $perPage);

        return [$page, $this->taxes->inForceIds($page->getCollection()->pluck('country')->unique()->all(), today()->toDateString())];
    }

    public function find(int $id): SubscriptionTax
    {
        return $this->taxes->find($id) ?? abort(404);
    }

    public function create(array $data): SubscriptionTax
    {
        $tax = $this->taxes->create($this->clean($data));

        ActivityLogService::record('created', "Added the subscription tax {$this->describe($tax)}", $tax);

        return $tax;
    }

    public function update(SubscriptionTax $tax, array $data): SubscriptionTax
    {
        $before = $this->describe($tax);
        $tax    = $this->taxes->update($tax, $this->clean($data));

        ActivityLogService::record('updated', "Changed the subscription tax {$before} to {$this->describe($tax)}", $tax);

        return $tax;
    }

    public function delete(SubscriptionTax $tax): void
    {
        $this->taxes->delete($tax);

        ActivityLogService::record('deleted', "Deleted the subscription tax {$this->describe($tax)}", $tax);
    }

    /** A note only with a reverse charge. */
    private function clean(array $data): array
    {
        $data['reverse_charge']      = (bool) ($data['reverse_charge'] ?? false);
        $data['reverse_charge_note'] = $data['reverse_charge'] ? (trim((string) ($data['reverse_charge_note'] ?? '')) ?: null) : null;

        return $data;
    }

    /** "VAT 15% for Bangladesh from 1 Dec 2026, a reverse charge with a tax ID" */
    private function describe(SubscriptionTax $tax): string
    {
        return "{$tax->name} {$tax->percent()}% for ".(Countries::name($tax->country) ?? $tax->country)
            ." from {$tax->starts_on->format('j M Y')}".($tax->reverse_charge ? ', a reverse charge with a tax ID' : '');
    }
}
