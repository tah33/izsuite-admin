<?php

namespace App\Services\Api\Purchases;

use App\Models\User\TaxRate;
use App\Models\User\Workspace;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\LocationRepository;
use App\Repositories\User\PurchaseSettingRepository;
use App\Repositories\User\TaxRateRepository;
use App\Services\Api\BusinessSetup\ExchangeRateService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

/**
 * What the purchase form needs from the server: what a new purchase starts
 * with, and its totals as it is filled in.
 *
 * defaults() follows the supplier once one is chosen (Doc 09 sections 5, 14
 * and 15): their purchase currency and that day's exchange rate, their
 * payment terms - or Settings > Purchases' - for the due date, and the taxes
 * new lines start with: the supplier's own tax treatment, when it is not the
 * standard one, takes the business's purchase rate of that treatment - or no
 * tax when it has none; otherwise the item's purchase tax or the business's
 * default purchase tax.
 *
 * calculate() is SalesCalculator's answer for what the form holds now - the
 * same arithmetic every save uses.
 */
class PurchaseFormService
{
    public function __construct(
        protected PurchaseSettingRepository $purchaseSettingRepository,
        protected ContactRepository $contactRepository,
        protected LocationRepository $locationRepository,
        protected TaxRateRepository $taxRateRepository,
        protected ExchangeRateService $exchangeRateService,
        protected PurchaseService $purchaseService,
        protected PurchaseLines $purchaseLines,
    ) {}

    /** $query is validated: an optional supplier, date and currency. */
    public function defaults(Workspace $workspace, array $query): array
    {
        $date     = $query['document_date'] ?? $this->purchaseService->today($workspace);
        $settings = $this->purchaseSettingRepository->forWorkspace($workspace);
        $contact  = isset($query['contact_id']) ? $this->contactRepository->findFor($workspace, (int) $query['contact_id']) : null;
        $currency = $query['currency'] ?? (in_array($contact?->purchase_currency, $workspace->currencyCodes(), true) ? $contact->purchase_currency : $workspace->base_currency);
        $terms    = $contact?->supplier_payment_terms ?? $settings->payment_terms;

        $taxes     = $workspace->usesTaxes();
        $default   = $taxes ? $this->taxRateRepository->defaultFor($workspace, TaxRate::PURCHASES) : null;
        $treatment = $taxes ? $contact?->purchaseTaxTreatment() : null;

        return [
            'document_date'      => $date,
            'due_date'           => CarbonImmutable::parse($date)->addDays($terms)->toDateString(),
            'payment_terms'      => $terms,
            'currency'           => $currency,
            'exchange_rate'      => $this->exchangeRateService->rateOn($workspace, $currency, $date),
            'location_id'        => $this->locationRepository->getFor($workspace)->first()?->id,
            'notes'              => $settings->notes,
            'price_tax_mode'     => $workspace->price_tax_mode,
            'tax_rounding'       => $workspace->tax_rounding,
            'taxes_enabled'      => $taxes,
            'default_tax_id'     => $default && $this->inEffect($default, $date) ? $default->id : null,
            'supplier_treatment' => $treatment?->value,
            'supplier_tax_id'    => $treatment ? $this->taxRateRepository->forPurchasesWithTreatment($workspace, $treatment, $date)?->id : null,
            // The supplier a purchase started from their profile is made out to - while they can still be bought from.
            'supplier'           => $contact && $contact->is_active && $contact->isSupplier() ? $contact : null,
        ];
    }

    /** $data is validated: the lines and what the totals depend on. */
    public function calculate(Workspace $workspace, array $data): array
    {
        $lines = $workspace->usesTaxes() ? $data['lines'] : array_map(fn (array $line) => [...$line, 'tax_rate_id' => null], $data['lines']);

        [$totals, $rows] = $this->purchaseService->totals([
            'discount_type'  => $data['discount_type'] ?? null,
            'discount_value' => $data['discount_value'] ?? null,
            'price_tax_mode' => $data['price_tax_mode'] ?? $workspace->price_tax_mode,
            'tax_rounding'   => $data['tax_rounding'] ?? $workspace->tax_rounding,
            'exchange_rate'  => $data['exchange_rate'] ?? '1',
        ], $this->purchaseLines->rows($workspace, $lines));

        return [...$totals, 'lines' => array_map(fn (array $row) => Arr::only($row, ['discount_amount', 'amount', 'tax_amount']), $rows)];
    }

    private function inEffect(TaxRate $rate, string $date): bool
    {
        return $rate->is_active && $rate->effective_from <= $date && (! $rate->effective_to || $rate->effective_to >= $date);
    }
}
