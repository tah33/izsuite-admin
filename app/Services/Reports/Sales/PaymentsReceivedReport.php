<?php

namespace App\Services\Reports\Sales;

use App\Models\User\SalesPayment;
use App\Models\User\Workspace;
use App\Services\Reports\Report;
use App\Services\Reports\ReportTable;
use App\Support\Decimal;
use Illuminate\Support\Str;

/**
 * Payments Received (Doc 07 section 10): completed customer payments by their
 * dates, and refunds - which take off what was received. Reversed ones never
 * count. Tax withheld is shown apart, while withholding applies: it never
 * reached an account. Each in the account's currency; the totals in the base
 * one. It may be narrowed to one payment account.
 */
class PaymentsReceivedReport extends Report
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base     = $workspace->base_currency;
        $withheld = $this->withholdingShown($workspace);
        $rows     = [];

        foreach ($this->sales->moneyMoved($workspace, $filters['from'], $filters['to'], $filters) as $money) {
            $refund   = $money->kind === SalesPayment::REFUND;
            $sign     = fn (string $amount) => $refund ? $this->negative($amount) : $amount;
            $document = $money->document;

            $rows[] = [
                'payment_date' => $money->payment_date,
                'number'       => $refund ? 'Refund' : $money->number,
                'customer'     => $money->contact?->name,
                'document'     => $document?->number,
                'account'      => $money->account?->name,
                'method'       => $money->method ? Str::headline($money->method) : null,
                'reference'    => $money->reference,
                'currency'     => $money->account?->currency,
                'amount'       => $sign(Decimal::round(Decimal::of($money->amount))),
                'base_amount'  => $sign(Decimal::round(Decimal::of($money->base_amount))),
                'withheld'     => $refund ? '0.00' : $this->figures->base($money->withholding_amount, $document?->exchange_rate ?? '1'),
                'location'     => $money->location?->name,
                'link'         => "/sales/payments/{$money->id}",
            ];
        }

        $received = Decimal::sum(array_filter(array_column($rows, 'base_amount'), fn (string $amount) => bccomp($amount, '0', 2) > 0));
        $refunded = Decimal::sum(array_filter(array_column($rows, 'base_amount'), fn (string $amount) => bccomp($amount, '0', 2) < 0));
        $taken    = Decimal::sum(array_column($rows, 'withheld'));
        $account  = $filters['account'] ?? null;

        return new ReportTable(
            title: 'Payments Received',
            period: $this->between($filters).($account ? " · {$account->name}" : ''),
            columns: array_values(array_filter([
                $this->column('payment_date', 'Date', 'date'),
                $this->column('number', 'Receipt'),
                $this->column('customer', 'Customer'),
                $this->column('document', 'Invoice or Credit Note'),
                $this->column('account', 'Payment Account'),
                $this->column('method', 'Method'),
                $this->column('reference', 'Reference'),
                $this->column('currency', 'Currency'),
                $this->column('amount', 'Amount', 'money'),
                $this->column('base_amount', "Amount ({$base})", 'money', $base),
                $withheld ? $this->column('withheld', "Tax Withheld ({$base})", 'money', $base) : null,
                $this->column('location', 'Location'),
            ])),
            rows: $rows,
            totals: ['payment_date' => 'Total', 'base_amount' => bcadd($received, $refunded, 2), 'withheld' => $taken],
            summary: array_values(array_filter([
                ['label' => 'Received', 'value' => $received, 'currency' => $base],
                ['label' => 'Refunded', 'value' => $refunded, 'currency' => $base],
                ['label' => 'Net Received', 'value' => bcadd($received, $refunded, 2), 'currency' => $base],
                $withheld ? ['label' => 'Tax Withheld', 'value' => $taken, 'currency' => $base] : null,
            ])),
            notes: [$withheld
                ? 'Refunds show as negative amounts. Tax withheld was paid to the tax office, not into an account, so it is not counted as received.'
                : 'Refunds show as negative amounts.'],
        );
    }
}
