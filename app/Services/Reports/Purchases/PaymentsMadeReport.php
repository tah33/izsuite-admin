<?php

namespace App\Services\Reports\Purchases;

use App\Models\User\Expense;
use App\Models\User\ExpensePayment;
use App\Models\User\Purchase;
use App\Models\User\SupplierPayment;
use App\Models\User\Workspace;
use App\Services\Reports\Report;
use App\Services\Reports\ReportTable;
use App\Services\User\Apps\WorkspaceApps;
use App\Support\Decimal;
use Illuminate\Support\Str;

/**
 * Payments Made (Doc 07 section 13): completed supplier and expense payments
 * by their dates, each with what it paid - a purchase or an expense - and to
 * whom. Reversed ones never count, nor transfers between accounts. Tax
 * withheld is shown apart, while withholding applies: it never left an
 * account. Each in the account's currency; the totals in the base one. It may
 * be narrowed to one payment account, or to one supplier or payee contact.
 * The columns are named for the apps the business has used.
 */
class PaymentsMadeReport extends Report
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base     = $workspace->base_currency;
        $withheld = $this->withholdingShown($workspace);
        $apps     = WorkspaceApps::for($workspace);
        $bought   = $apps->hasHistory('purchases') || ! $apps->hasHistory('expenses');
        $spent    = $apps->hasHistory('expenses');
        $rows     = [];

        foreach ($this->purchases->paymentsMade($workspace, $filters['from'], $filters['to'], $filters) as $payment) {
            $rows[] = $this->row($payment, 'Supplier Payment', $payment->contact?->name, $payment->purchase, "/purchases/payments/{$payment->id}");
        }

        foreach ($this->expenses->paymentsMade($workspace, $filters['from'], $filters['to'], $filters) as $payment) {
            $rows[] = $this->row($payment, 'Expense Payment', $payment->expense ? $this->figures->payeeName($payment->expense) : null, $payment->expense, "/expenses/payments/{$payment->id}");
        }

        usort($rows, fn (array $a, array $b) => [$a['payment_date'], $a['number']] <=> [$b['payment_date'], $b['number']]);

        $paid    = Decimal::sum(array_column($rows, 'base_amount'));
        $taken   = Decimal::sum(array_column($rows, 'withheld'));
        $account = $filters['account'] ?? null;
        $whom    = match (true) {
            $bought && $spent => 'suppliers and payees',
            $spent            => 'payees',
            default           => 'suppliers',
        };

        return new ReportTable(
            title: 'Payments Made',
            period: $this->between($filters).($account ? " · {$account->name}" : ''),
            columns: array_values(array_filter([
                $this->column('payment_date', 'Date', 'date'),
                $this->column('number', 'Voucher'),
                $this->column('type', 'Payment Type'),
                $this->column('payee', $bought && $spent ? 'Supplier or Payee' : ($spent ? 'Payee' : 'Supplier')),
                $this->column('document', $bought && $spent ? 'Purchase or Expense' : ($spent ? 'Expense' : 'Purchase')),
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
                ? "Reversed payments are left out. Tax withheld from {$whom} is owed to the tax office, not paid out of an account, so it is not counted as paid."
                : 'Reversed payments are left out.'],
        );
    }

    /** A payment's row: what it paid - a purchase or an expense, $paid - and whom, in its account's currency and the base one. */
    private function row(SupplierPayment|ExpensePayment $payment, string $type, ?string $payee, Purchase|Expense|null $paid, string $link): array
    {
        return [
            'payment_date' => $payment->payment_date,
            'number'       => $payment->number,
            'type'         => $type,
            'payee'        => $payee,
            'document'     => $paid?->number,
            'account'      => $payment->account?->name,
            'method'       => $payment->method ? Str::headline($payment->method) : null,
            'reference'    => $payment->reference,
            'currency'     => $payment->account?->currency,
            'amount'       => Decimal::round(Decimal::of($payment->amount)),
            'base_amount'  => Decimal::round(Decimal::of($payment->base_amount)),
            'withheld'     => $this->figures->base($payment->withholding_amount, $paid?->exchange_rate ?? '1'),
            'location'     => $payment->location?->name,
            'link'         => $link,
        ];
    }
}
