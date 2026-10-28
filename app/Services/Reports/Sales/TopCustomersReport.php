<?php

namespace App\Services\Reports\Sales;

use App\Enums\DocumentType;
use App\Models\User\SalesPayment;
use App\Models\User\Workspace;
use App\Services\Reports\Report;
use App\Services\Reports\ReportTable;
use App\Support\Decimal;

/**
 * Top Customers (Doc 07 section 8): who bought most in the period - net
 * sales, tax left out - what they paid in it, less refunds, and what they
 * still owed at its end, tax included. All in the base currency.
 */
class TopCustomersReport extends Report
{
    private const LIMIT = 100;

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base = $workspace->base_currency;
        $rows = [];

        foreach ([DocumentType::Invoice, DocumentType::CreditNote] as $type) {
            foreach ($this->sales->issued($workspace, $type, $filters['from'], $filters['to'], $filters) as $document) {
                $key = $this->ensure($rows, $document->contact_id, $this->figures->customerName($document));

                $rows[$key]['invoices']  += $type === DocumentType::Invoice ? 1 : 0;
                $rows[$key]['net_sales']  = $type === DocumentType::Invoice
                    ? $this->add($rows[$key]['net_sales'], Decimal::of($document->base_net_total))
                    : $this->subtract($rows[$key]['net_sales'], Decimal::of($document->base_net_total));
            }
        }

        foreach ($this->sales->moneyMoved($workspace, $filters['from'], $filters['to'], $filters) as $money) {
            $key = $this->ensure($rows, $money->contact_id, $money->contact?->name ?? 'Unknown customer');

            $rows[$key]['received'] = $money->kind === SalesPayment::PAYMENT
                ? $this->add($rows[$key]['received'], Decimal::of($money->base_amount))
                : $this->subtract($rows[$key]['received'], Decimal::of($money->base_amount));
        }

        foreach ($this->sales->invoicesAsOf($workspace, min($filters['to'], $this->period->today($workspace)), $filters) as $invoice) {
            if (isset($rows["c{$invoice->contact_id}"])) {
                $rows["c{$invoice->contact_id}"]['outstanding'] = $this->add($rows["c{$invoice->contact_id}"]['outstanding'], $this->figures->base($this->figures->owedAsOf($invoice), $invoice->exchange_rate));
            }
        }

        uasort($rows, fn (array $a, array $b) => bccomp($b['net_sales'], $a['net_sales'], 2) ?: strcmp($a['customer'], $b['customer']));

        $rows = array_slice(array_values($rows), 0, self::LIMIT);

        return new ReportTable(
            title: 'Top Customers',
            period: $this->between($filters),
            columns: [
                $this->column('customer', 'Customer'),
                $this->column('invoices', 'Invoices', 'number'),
                $this->column('net_sales', 'Net Sales', 'money', $base),
                $this->column('received', 'Amount Received', 'money', $base),
                $this->column('outstanding', 'Outstanding Balance', 'money', $base),
            ],
            rows: $rows,
            totals: ['customer' => 'Total', 'invoices' => array_sum(array_column($rows, 'invoices')), ...$this->totals($rows, ['net_sales', 'received', 'outstanding'])],
            notes: ["In {$base}. Net sales leave tax out; the outstanding balance includes it, as it stood at the end of the period."],
        );
    }

    /** The customer's row, started when it is not there yet - its key. */
    private function ensure(array &$rows, ?int $id, string $name): string
    {
        $rows["c{$id}"] ??= ['customer' => $name, 'invoices' => 0, 'net_sales' => '0.00', 'received' => '0.00', 'outstanding' => '0.00', 'link' => $id ? "/contacts/{$id}" : null];

        return "c{$id}";
    }
}
