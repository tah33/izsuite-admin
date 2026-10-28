<?php

namespace App\Services\Reports\Sales;

use App\Enums\DocumentType;
use App\Models\User\SalesPayment;
use App\Models\User\Workspace;
use App\Services\Reports\Report;
use App\Services\Reports\ReportTable;
use App\Support\Decimal;

/**
 * Contact Statement, customer side (Doc 07 section 17): the balance a
 * customer started the period with, then each issued invoice (a debit),
 * credit note and tax they withheld (credits), payment and refund, with the
 * balance running. In one currency, or - for all of them - in the base
 * currency. Operational, not a ledger statement.
 *
 * Expects $filters['contact'], the customer.
 */
class CustomerStatementReport extends Report
{
    private const DAWN = '1900-01-01';

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $contact  = $filters['contact'];
        $currency = $filters['currency'] ?? null;
        $shown    = $currency ?? $workspace->base_currency;
        $entries  = $this->entries($workspace, $contact->id, $currency, $filters['to']);
        $owed     = $this->owed($workspace, $contact->id, $currency, $filters['to']);
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

        $openingRow = ['date' => $filters['from'], 'type' => 'Opening Balance', 'number' => null, 'due_date' => null, 'debit' => null, 'credit' => null, 'payment' => null, 'outstanding' => null, 'balance' => $opening];

        return new ReportTable(
            title: "Statement for {$contact->name}",
            period: $this->between($filters).' · '.($currency ? $currency : "All currencies in {$shown}"),
            columns: [
                $this->column('date', 'Date', 'date'),
                $this->column('type', 'Type'),
                $this->column('number', 'Number'),
                $this->column('due_date', 'Due Date', 'date'),
                $this->column('debit', 'Debit', 'money', $shown),
                $this->column('credit', 'Credit', 'money', $shown),
                $this->column('payment', 'Payment', 'money', $shown),
                $this->column('outstanding', 'Outstanding', 'money', $shown),
                $this->column('balance', 'Running Balance', 'money', $shown),
            ],
            rows: [$openingRow, ...$rows],
            totals: ['date' => 'Total', ...$this->totals($rows, ['debit', 'credit', 'payment']), 'balance' => $balance],
            summary: [
                ['label' => 'Opening Balance', 'value' => $opening, 'currency' => $shown],
                ['label' => 'Invoiced', 'value' => Decimal::sum(array_column(array_filter($rows, fn (array $row) => $row['invoice']), 'debit')), 'currency' => $shown],
                ['label' => 'Credits', 'value' => Decimal::sum(array_column($rows, 'credit')), 'currency' => $shown],
                ['label' => 'Payments', 'value' => Decimal::sum(array_column($rows, 'payment')), 'currency' => $shown],
                ['label' => 'Closing Balance', 'value' => $balance, 'currency' => $shown],
            ],
            notes: ['Debits are invoices and refunds paid out; credits are credit notes and tax the customer withheld. Drafts, cancelled and void documents and reversed payments are left out.'],
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
                    'debit'   => $type === DocumentType::Invoice ? $amount : '0.00',
                    'credit'  => $type === DocumentType::CreditNote ? $amount : '0.00',
                    'invoice' => $type === DocumentType::Invoice ? $document->id : null,
                    'link'    => "/sales/{$type->kind()}/{$document->id}",
                ]);
            }
        }

        foreach ($this->sales->moneyMoved($workspace, self::DAWN, $to, ['contact_id' => $contactId, 'document_currency' => $currency]) as $money) {
            $refund   = $money->kind === SalesPayment::REFUND;
            $amount   = $currency ? Decimal::round(Decimal::of($money->applied_amount)) : Decimal::round(Decimal::of($money->base_amount));
            $withheld = $currency ? Decimal::round(Decimal::of($money->withholding_amount)) : $this->figures->base($money->withholding_amount, $money->document?->exchange_rate ?? '1');

            $entries[] = $this->entry($money->payment_date, 2, $money->id, $refund ? 'Refund' : 'Payment', $refund ? $money->document?->number : $money->number, null, [
                'debit'   => $refund ? $amount : '0.00',
                'credit'  => $refund ? '0.00' : $withheld,
                'payment' => $refund ? '0.00' : $amount,
                'link'    => "/sales/payments/{$money->id}",
            ]);
        }

        usort($entries, fn (array $a, array $b) => [$a['date'], $a['order'], $a['id']] <=> [$b['date'], $b['order'], $b['id']]);

        return $entries;
    }

    private function entry(string $date, int $order, int $id, string $type, ?string $number, ?string $dueDate, array $amounts): array
    {
        return ['date' => $date, 'order' => $order, 'id' => $id, 'type' => $type, 'number' => $number, 'due_date' => $dueDate, 'debit' => '0.00', 'credit' => '0.00', 'payment' => '0.00', 'invoice' => null, ...$amounts];
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
