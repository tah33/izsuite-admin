<?php

namespace App\Services\Reports\Purchases;

use App\Models\User\Workspace;
use App\Services\Reports\Report;
use App\Services\Reports\ReportTable;
use App\Support\Decimal;
use Illuminate\Support\Str;

/**
 * Payments Made (Doc 07 section 13): completed supplier payments by their
 * dates. Reversed ones never count, nor transfers between accounts. Tax
 * withheld is shown apart, while withholding applies: it never left an
 * account. Each in the account's currency; the totals in the base one. It may
 * be narrowed to one payment account. Expense payments join with their app.
 */
class PaymentsMadeReport extends Report
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base     = $workspace->base_currency;
        $withheld = $this->withholdingShown($workspace);
        $rows     = [];

        foreach ($this->purchases->paymentsMade($workspace, $filters['from'], $filters['to'], $filters) as $payment) {
            $rows[] = [
                'payment_date' => $payment->payment_date,
                'number'       => $payment->number,
                'type'         => 'Supplier Payment',
                'payee'        => $payment->contact?->name,
                'document'     => $payment->purchase?->number,
                'account'      => $payment->account?->name,
                'method'       => $payment->method ? Str::headline($payment->method) : null,
                'reference'    => $payment->reference,
                'currency'     => $payment->account?->currency,
                'amount'       => Decimal::round(Decimal::of($payment->amount)),
                'base_amount'  => Decimal::round(Decimal::of($payment->base_amount)),
                'withheld'     => $this->figures->base($payment->withholding_amount, $payment->purchase?->exchange_rate ?? '1'),
                'location'     => $payment->location?->name,
                'link'         => "/purchases/payments/{$payment->id}",
            ];
        }

        $paid    = Decimal::sum(array_column($rows, 'base_amount'));
        $taken   = Decimal::sum(array_column($rows, 'withheld'));
        $account = $filters['account'] ?? null;

        return new ReportTable(
            title: 'Payments Made',
            period: $this->between($filters).($account ? " · {$account->name}" : ''),
            columns: array_values(array_filter([
                $this->column('payment_date', 'Date', 'date'),
                $this->column('number', 'Voucher'),
                $this->column('type', 'Payment Type'),
                $this->column('payee', 'Supplier'),
                $this->column('document', 'Purchase'),
                $this->column('account', 'Payment Account'),
                $this->column('method', 'Method'),
                $this->column('reference', 'Reference'),
                $this->column('currency', 'Currency'),
                $this->column('amount', 'Amount Paid', 'money'),
                $this->column('base_amount', "Amount Paid ({$base})", 'money', $base),
                $withheld ? $this->column('withheld', "Tax Withheld ({$base})", 'money', $base) : null,
                $this->column('location', 'Location'),
            ])),
            rows: $rows,
            totals: ['payment_date' => 'Total', 'base_amount' => $paid, 'withheld' => $taken],
            summary: array_values(array_filter([
                ['label' => 'Paid', 'value' => $paid, 'currency' => $base],
                $withheld ? ['label' => 'Tax Withheld', 'value' => $taken, 'currency' => $base] : null,
            ])),
            notes: [$withheld
                ? 'Reversed payments are left out. Tax withheld from suppliers is owed to the tax office, not paid out of an account, so it is not counted as paid.'
                : 'Reversed payments are left out.'],
        );
    }
}
