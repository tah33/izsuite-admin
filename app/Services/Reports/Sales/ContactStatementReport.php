<?php

namespace App\Services\Reports\Sales;

use App\Enums\DocumentType;
use App\Models\User\SalesPayment;
use App\Models\User\Workspace;
use App\Repositories\User\PurchaseReportRepository;
use App\Repositories\User\SalesReportRepository;
use App\Services\Documents\PaymentDetails;
use App\Services\Reports\Report;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\ReportTable;
use App\Services\Reports\SalesFigures;
use App\Support\Decimal;

/**
 * Contact Statement (Doc 07 section 17), one side at a time. A customer's:
 * the balance they started the period with, then each issued invoice (a
 * debit), credit note and tax they withheld (credits), payment and refund,
 * with the balance running - its notes end with where to pay, the accounts
 * in that currency shown to customers (Doc 05 section 9). A supplier's: each
 * recorded purchase (a debit - owed to them), tax withheld from what was paid
 * them (a credit) and each payment made. In one currency, or - for all of
 * them - in the base currency, each row keeping its own currency and amount.
 * Operational, not a ledger statement, and kept in date order.
 *
 * Expects $filters['contact'] and $filters['side']: customer or supplier.
 */
class ContactStatementReport extends Report
{
    private const DAWN = '1900-01-01';

    public function __construct(
        SalesReportRepository $sales,
        SalesFigures $figures,
        ReportPeriod $period,
        PurchaseReportRepository $purchases,
        protected PaymentDetails $paymentDetails,
    ) {
        parent::__construct($sales, $figures, $period, $purchases);
    }

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $contact  = $filters['contact'];
        $supplier = ($filters['side'] ?? 'customer') === 'supplier';
        $currency = $filters['currency'] ?? null;
        $shown    = $currency ?? $workspace->base_currency;
        $entries  = $supplier ? $this->supplierEntries($workspace, $contact->id, $currency, $filters['to']) : $this->entries($workspace, $contact->id, $currency, $filters['to']);
        $owed     = $supplier ? $this->owedToSupplier($workspace, $contact->id, $currency, $filters['to']) : $this->owed($workspace, $contact->id, $currency, $filters['to']);
        $opening  = '0.00';
        $rows     = [];

        foreach ($entries as $entry) {
            $change = bcsub(bcsub($entry['debit'], $entry['credit'], 2), $entry['payment'], 2);

            if ($entry['date'] < $filters['from']) {
                $opening = bcadd($opening, $change, 2);

                continue;
            }

            $rows[] = [...$entry, 'outstanding' => $entry['invoice'] ? ($owed[$entry['invoice']] ?? '0.00') : null];
        }

        $balance = $opening;

        foreach ($rows as $index => $row) {
            $balance                 = bcadd($balance, bcsub(bcsub($row['debit'], $row['credit'], 2), $row['payment'], 2), 2);
            $rows[$index]['balance'] = $balance;
        }

        $openingRow = [
            'date' => $filters['from'], 'type' => 'Opening Balance', 'number' => null, 'due_date' => null, 'currency' => null, 'original' => null,
            'debit' => null, 'credit' => null, 'payment' => null, 'outstanding' => null, 'balance' => $opening,
        ];

        return new ReportTable(
            title: "Statement for {$contact->name}",
            period: $this->between($filters).' · '.($currency ? $currency : "All currencies in {$shown}").($contact->isCustomer() && $contact->isSupplier() ? ($supplier ? ' · As supplier' : ' · As customer') : ''),
            columns: array_values(array_filter([
                $this->column('date', 'Date', 'date'),
                $this->column('type', 'Type'),
                $this->column('number', 'Number'),
                $this->column('due_date', 'Due Date', 'date'),
                // All currencies at once: each row's own, beside its amount in the base one.
                $currency ? null : $this->column('currency', 'Currency'),
                $currency ? null : $this->column('original', 'Original Amount', 'money'),
                $this->column('debit', 'Debit', 'money', $shown),
                $this->column('credit', 'Credit', 'money', $shown),
                $this->column('payment', 'Payment', 'money', $shown),
                $this->column('outstanding', 'Outstanding', 'money', $shown),
                $this->column('balance', 'Running Balance', 'money', $shown),
            ])),
            rows: [$openingRow, ...$rows],
            totals: ['date' => 'Total', ...$this->totals($rows, ['debit', 'credit', 'payment']), 'balance' => $balance],
            summary: [
                ['label' => 'Opening Balance', 'value' => $opening, 'currency' => $shown],
                ['label' => $supplier ? 'Purchased' : 'Invoiced', 'value' => Decimal::sum(array_column(array_filter($rows, fn (array $row) => $row['invoice']), 'debit')), 'currency' => $shown],
                ['label' => 'Credits', 'value' => Decimal::sum(array_column($rows, 'credit')), 'currency' => $shown],
                ['label' => 'Payments', 'value' => Decimal::sum(array_column($rows, 'payment')), 'currency' => $shown],
                ['label' => 'Closing Balance', 'value' => $balance, 'currency' => $shown],
            ],
            notes: $supplier
                ? ['Debits are purchases recorded - what is owed to the supplier; credits are tax withheld from what was paid them. Drafts, void purchases and reversed payments are left out.']
                : [
                    'Debits are invoices and refunds paid out; credits are credit notes and tax the customer withheld. Drafts, cancelled and void documents and reversed payments are left out.',
                    ...array_map(fn (array $account) => $this->paymentDetails->line($account), $this->paymentDetails->for($workspace, $shown, null)),
                ],
            sortable: false,
        );
    }

    /** Every movement on the customer's account up to $to, oldest first: invoices, credit notes, then money on the day. */
    private function entries(Workspace $workspace, int $contactId, ?string $currency, string $to): array
    {
        $entries = [];
        $scope   = ['contact_id' => $contactId, 'currency' => $currency];

        foreach ([DocumentType::Invoice, DocumentType::CreditNote] as $order => $type) {
            foreach ($this->sales->issued($workspace, $type, self::DAWN, $to, $scope) as $document) {
                $amount = $currency ? (string) $document->grand_total : Decimal::round(Decimal::of($document->base_grand_total));

                $entries[] = $this->entry($document->document_date, $order, $document->id, $type->label(), $document->number, $document->due_date, [
                    'currency' => $document->currency,
                    'original' => (string) $document->grand_total,
                    'debit'    => $type === DocumentType::Invoice ? $amount : '0.00',
                    'credit'   => $type === DocumentType::CreditNote ? $amount : '0.00',
                    'invoice'  => $type === DocumentType::Invoice ? $document->id : null,
                    'link'     => "/sales/{$type->kind()}/{$document->id}",
                ]);
            }
        }

        foreach ($this->sales->moneyMoved($workspace, self::DAWN, $to, ['contact_id' => $contactId, 'document_currency' => $currency]) as $money) {
            $refund   = $money->kind === SalesPayment::REFUND;
            $amount   = $currency ? Decimal::round(Decimal::of($money->applied_amount)) : Decimal::round(Decimal::of($money->base_amount));
            $withheld = $currency ? Decimal::round(Decimal::of($money->withholding_amount)) : $this->figures->base($money->withholding_amount, $money->document?->exchange_rate ?? '1');

            $entries[] = $this->entry($money->payment_date, 2, $money->id, $refund ? 'Refund' : 'Payment', $refund ? $money->document?->number : $money->number, null, [
                'currency' => $money->document?->currency,
                'original' => Decimal::round(Decimal::of($money->applied_amount)),
                'debit'    => $refund ? $amount : '0.00',
                'credit'   => $refund ? '0.00' : $withheld,
                'payment'  => $refund ? '0.00' : $amount,
                'link'     => "/sales/payments/{$money->id}",
            ]);
        }

        usort($entries, fn (array $a, array $b) => [$a['date'], $a['order'], $a['id']] <=> [$b['date'], $b['order'], $b['id']]);

        return $entries;
    }

    /** Every movement on the supplier's account up to $to, oldest first: purchases, then payments on the day. */
    private function supplierEntries(Workspace $workspace, int $contactId, ?string $currency, string $to): array
    {
        $entries = [];

        foreach ($this->purchases->recorded($workspace, self::DAWN, $to, ['contact_id' => $contactId, 'currency' => $currency]) as $purchase) {
            $entries[] = $this->entry($purchase->document_date, 0, $purchase->id, 'Purchase', $purchase->number, $purchase->due_date, [
                'currency' => $purchase->currency,
                'original' => (string) $purchase->grand_total,
                'debit'    => $currency ? (string) $purchase->grand_total : Decimal::round(Decimal::of($purchase->base_grand_total)),
                'invoice'  => $purchase->id,
                'link'     => "/purchases/{$purchase->id}",
            ]);
        }

        foreach ($this->purchases->paymentsMade($workspace, self::DAWN, $to, ['contact_id' => $contactId, 'document_currency' => $currency]) as $payment) {
            $entries[] = $this->entry($payment->payment_date, 2, $payment->id, 'Payment', $payment->number, null, [
                'currency' => $payment->purchase?->currency,
                'original' => Decimal::round(Decimal::of($payment->applied_amount)),
                'credit'   => $currency ? Decimal::round(Decimal::of($payment->withholding_amount)) : $this->figures->base($payment->withholding_amount, $payment->purchase?->exchange_rate ?? '1'),
                'payment'  => $currency ? Decimal::round(Decimal::of($payment->applied_amount)) : Decimal::round(Decimal::of($payment->base_amount)),
                'link'     => "/purchases/payments/{$payment->id}",
            ]);
        }

        usort($entries, fn (array $a, array $b) => [$a['date'], $a['order'], $a['id']] <=> [$b['date'], $b['order'], $b['id']]);

        return $entries;
    }

    private function entry(string $date, int $order, int $id, string $type, ?string $number, ?string $dueDate, array $amounts): array
    {
        return ['date' => $date, 'order' => $order, 'id' => $id, 'type' => $type, 'number' => $number, 'due_date' => $dueDate, 'debit' => '0.00', 'credit' => '0.00', 'payment' => '0.00', 'invoice' => null, ...$amounts];
    }

    /** What each of the supplier's purchases was still owed at $to, by id - in its currency, or the base one. */
    private function owedToSupplier(Workspace $workspace, int $contactId, ?string $currency, string $to): array
    {
        return $this->purchases->purchasesAsOf($workspace, $to, ['contact_id' => $contactId, 'currency' => $currency])
            ->mapWithKeys(fn ($purchase) => [$purchase->id => $currency
                ? $this->figures->purchaseOwedAsOf($purchase)
                : $this->figures->base($this->figures->purchaseOwedAsOf($purchase), $purchase->exchange_rate)])
            ->all();
    }

    /** What each of the customer's invoices still owed at $to, by id - in its currency, or the base one. */
    private function owed(Workspace $workspace, int $contactId, ?string $currency, string $to): array
    {
        return $this->sales->invoicesAsOf($workspace, $to, ['contact_id' => $contactId, 'currency' => $currency])
            ->mapWithKeys(fn ($invoice) => [$invoice->id => $currency
                ? $this->figures->owedAsOf($invoice)
                : $this->figures->base($this->figures->owedAsOf($invoice), $invoice->exchange_rate)])
            ->all();
    }
}
