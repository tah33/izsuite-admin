<?php

namespace App\Services\Api\Sales;

use App\Enums\DocumentType;
use App\Models\User\TaxRate;
use App\Models\User\Workspace;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\LocationRepository;
use App\Repositories\User\SalesSettingRepository;
use App\Repositories\User\TaxRateRepository;
use App\Services\Api\BusinessSetup\ExchangeRateService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

/**
 * What the sales document form needs from the server: what a new document
 * starts with, and its totals as it is filled in.
 *
 * defaults() follows the customer once one is chosen (Doc 08 sections 4, 17
 * and 18): their sales currency and that day's exchange rate, their payment
 * terms - or the business's - for an invoice's due date, Settings > Sales'
 * validity for a quotation's or proforma's expiry date, and the taxes new
 * lines start with: Exempt for a customer with an exemption number, or else
 * the item's own tax or the business's default.
 *
 * calculate() is SalesCalculator's answer for what the form holds now - the
 * same arithmetic every save uses, so the form shows the totals the document
 * will have.
 */
class SalesFormService
{
    public function __construct(
        protected SalesSettingRepository $salesSettingRepository,
        protected ContactRepository $contactRepository,
        protected LocationRepository $locationRepository,
        protected TaxRateRepository $taxRateRepository,
        protected ExchangeRateService $exchangeRateService,
        protected SalesDocumentService $salesDocumentService,
        protected SalesLines $salesLines,
    ) {}

    /** $query is validated: an optional customer, date and currency. */
    public function defaults(Workspace $workspace, DocumentType $type, array $query): array
    {
        $date     = $query['document_date'] ?? $this->salesDocumentService->today($workspace);
        $settings = $this->salesSettingRepository->forWorkspace($workspace);
        $contact  = isset($query['contact_id']) ? $this->contactRepository->findFor($workspace, (int) $query['contact_id']) : null;
        $currency = $query['currency'] ?? (in_array($contact?->sales_currency, $workspace->currencyCodes(), true) ? $contact->sales_currency : $workspace->base_currency);
        $terms    = $contact?->customer_payment_terms ?? $settings->payment_terms;
        $days     = fn (int $days) => CarbonImmutable::parse($date)->addDays($days)->toDateString();
        $taxes    = $workspace->usesTaxes();
        $default  = $taxes ? $this->taxRateRepository->defaultFor($workspace, TaxRate::SALES) : null;

        return [
            'document_date'      => $date,
            'due_date'           => $type === DocumentType::Invoice ? $days($terms) : null,
            'expiry_date'        => $type->isOffer() ? $days($type === DocumentType::Quotation ? $settings->quotation_validity : $settings->proforma_validity) : null,
            'payment_terms'      => $type === DocumentType::Invoice ? $terms : null,
            'currency'           => $currency,
            'exchange_rate'      => $this->exchangeRateService->rateOn($workspace, $currency, $date),
            'location_id'        => $this->locationRepository->getFor($workspace)->first()?->id,
            'notes'              => $settings->notes,
            'terms'              => $settings->terms,
            'price_tax_mode'     => $workspace->price_tax_mode,
            'tax_rounding'       => $workspace->tax_rounding,
            'taxes_enabled'      => $taxes,
            'line_discounts'     => $settings->line_discounts,
            'document_discounts' => $settings->document_discounts,
            'default_tax_id'     => $default && $this->inEffect($default, $date) ? $default->id : null,
            'exempt_tax_id'      => $taxes && $contact?->tax_exemption_number ? $this->taxRateRepository->exemptForSales($workspace, $date)?->id : null,
        ];
    }

    /** $data is validated: the lines and what the totals depend on. */
    public function calculate(Workspace $workspace, array $data): array
    {
        $lines = $workspace->usesTaxes() ? $data['lines'] : array_map(fn (array $line) => [...$line, 'tax_rate_id' => null], $data['lines']);

        [$totals, $rows] = $this->salesDocumentService->totals([
            'discount_type'  => $data['discount_type'] ?? null,
            'discount_value' => $data['discount_value'] ?? null,
            'price_tax_mode' => $data['price_tax_mode'] ?? $workspace->price_tax_mode,
            'tax_rounding'   => $data['tax_rounding'] ?? $workspace->tax_rounding,
            'exchange_rate'  => $data['exchange_rate'] ?? '1',
        ], $this->salesLines->rows($workspace, $lines));

        return [...$totals, 'lines' => array_map(fn (array $row) => Arr::only($row, ['discount_amount', 'amount', 'tax_amount']), $rows)];
    }

    private function inEffect(TaxRate $rate, string $date): bool
    {
        return $rate->is_active && $rate->effective_from <= $date && (! $rate->effective_to || $rate->effective_to >= $date);
    }
}
