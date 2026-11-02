<?php

namespace App\Services\Api\Expenses;

use App\Models\User\TaxRate;
use App\Models\User\Workspace;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\ExpenseSettingRepository;
use App\Repositories\User\TaxRateRepository;
use App\Services\Api\BusinessSetup\ExchangeRateService;
use App\Services\Shared\LocationAccess;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

/**
 * What the expense form needs from the server: what a new expense starts
 * with, and its totals as it is filled in.
 *
 * defaults() follows the payee once a contact is chosen (Doc 10 sections 5,
 * 14 and 15): their purchase currency and that day's exchange rate, their
 * payment terms - or Settings > Expenses' - for the due date, and the tax
 * their lines start with: a tax treatment other than the standard one takes
 * the business's expense rate of that treatment - or no tax when it has none;
 * otherwise each line takes its category's tax, else the business's default
 * expense tax (the form applies that order, section 14).
 *
 * calculate() is SalesCalculator's answer for what the form holds now - the
 * same arithmetic every save uses.
 */
class ExpenseFormService
{
    public function __construct(
        protected ExpenseSettingRepository $expenseSettingRepository,
        protected ContactRepository $contactRepository,
        protected LocationAccess $locationAccess,
        protected TaxRateRepository $taxRateRepository,
        protected ExchangeRateService $exchangeRateService,
        protected ExpenseService $expenseService,
        protected ExpenseLines $expenseLines,
    ) {}

    /** $query is validated: an optional payee contact, date and currency. */
    public function defaults(Workspace $workspace, array $query): array
    {
        $date     = $query['document_date'] ?? $this->expenseService->today($workspace);
        $settings = $this->expenseSettingRepository->forWorkspace($workspace);
        $contact  = isset($query['contact_id']) ? $this->contactRepository->findFor($workspace, (int) $query['contact_id']) : null;
        $currency = $query['currency'] ?? (in_array($contact?->purchase_currency, $workspace->currencyCodes(), true) ? $contact->purchase_currency : $workspace->base_currency);
        $terms    = $contact?->supplier_payment_terms ?? $settings->payment_terms;

        $taxes     = $workspace->usesTaxes();
        $default   = $taxes ? $this->taxRateRepository->defaultFor($workspace, TaxRate::EXPENSES) : null;
        $treatment = $taxes ? $contact?->purchaseTaxTreatment() : null;

        return [
            'document_date'   => $date,
            'due_date'        => CarbonImmutable::parse($date)->addDays($terms)->toDateString(),
            'payment_terms'   => $terms,
            'currency'        => $currency,
            'exchange_rate'   => $this->exchangeRateService->rateOn($workspace, $currency, $date),
            'location_id'     => $this->locationAccess->defaultFor($workspace),
            'notes'           => $settings->notes,
            'price_tax_mode'  => $workspace->price_tax_mode,
            'tax_rounding'    => $workspace->tax_rounding,
            'taxes_enabled'   => $taxes,
            'default_tax_id'  => $default && $this->inEffect($default, $date) ? $default->id : null,
            'payee_treatment' => $treatment?->value,
            'payee_tax_id'    => $treatment ? $this->taxRateRepository->forExpensesWithTreatment($workspace, $treatment, $date)?->id : null,
            // The payee an expense started from their profile is paid to - while they can still be chosen.
            'payee'           => $contact && $contact->is_active && $contact->isSupplier() ? $contact : null,
        ];
    }

    /** $data is validated: the lines and what the totals depend on. */
    public function calculate(Workspace $workspace, array $data): array
    {
        $lines = $workspace->usesTaxes() ? $data['lines'] : array_map(fn (array $line) => [...$line, 'tax_rate_id' => null], $data['lines']);

        [$totals, $rows] = $this->expenseService->totals([
            'price_tax_mode' => $data['price_tax_mode'] ?? $workspace->price_tax_mode,
            'tax_rounding'   => $data['tax_rounding'] ?? $workspace->tax_rounding,
            'exchange_rate'  => $data['exchange_rate'] ?? '1',
        ], $this->expenseLines->rows($workspace, $lines));

        return [...$totals, 'discount_total' => '0.00', 'lines' => array_map(fn (array $row) => [...Arr::only($row, ['amount', 'tax_amount']), 'discount_amount' => '0.00'], $rows)];
    }

    private function inEffect(TaxRate $rate, string $date): bool
    {
        return $rate->is_active && $rate->effective_from <= $date && (! $rate->effective_to || $rate->effective_to >= $date);
    }
}
