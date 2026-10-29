<?php

namespace App\Services\Reports\Accounts;

use App\Models\User\AccountTransaction;
use App\Models\User\PaymentAccount;
use App\Models\User\Workspace;
use App\Repositories\User\AccountTransactionRepository;
use App\Repositories\User\SalesReportRepository;
use App\Services\Reports\Report;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\ReportTable;
use App\Services\Reports\SalesFigures;
use App\Support\Decimal;
use Illuminate\Support\Str;

/**
 * Payment Account Transactions (Doc 07 section 18): one account's money in
 * and out over a period, in its own currency - the balance brought forward,
 * its opening balance on its day, then each payment, refund and transfer with
 * the balance running, as its page shows it (Doc 05 section 11). A reversed
 * row shows, but counts toward neither the totals nor the balance. A recorded
 * balance, not a bank-confirmed or reconciled one; kept in date order.
 *
 * Expects $filters['account'].
 */
class AccountTransactionsReport extends Report
{
    public function __construct(
        SalesReportRepository $sales,
        SalesFigures $figures,
        ReportPeriod $period,
        protected AccountTransactionRepository $transactions,
    ) {
        parent::__construct($sales, $figures, $period);
    }

    public function build(Workspace $workspace, array $filters): ReportTable
    {
        /** @var PaymentAccount $account */
        $account  = $filters['account'];
        $currency = $account->currency;
        $opening  = Decimal::of($account->opening_balance);
        $openedOn = $account->opening_balance_date;
        $inside   = $openedOn && $openedOn >= $filters['from'] && $openedOn <= $filters['to'] && ! Decimal::isZero($opening);
        $forward  = Decimal::round(bcadd($openedOn && $openedOn < $filters['from'] ? $opening : '0', Decimal::of($this->transactions->netBefore($account, $filters['from'])), 10));
        $balance  = $inside ? Decimal::round(bcadd($forward, $opening, 10)) : $forward;
        $moneyIn  = $inside && bccomp($opening, '0', 2) > 0 ? Decimal::round($opening) : '0.00';
        $moneyOut = $inside && bccomp($opening, '0', 2) < 0 ? Decimal::round(bcsub('0', $opening, 10)) : '0.00';

        $rows = [$this->line($filters['from'], 'Balance Brought Forward', $forward)];

        if ($inside) {
            $rows[] = [...$this->line($openedOn, 'Opening Balance', $balance), 'money_in' => $moneyIn === '0.00' ? null : $moneyIn, 'money_out' => $moneyOut === '0.00' ? null : $moneyOut, 'status' => 'Completed'];
        }

        foreach ($this->transactions->allFor($account, ['date_from' => $filters['from'], 'date_to' => $filters['to']]) as $row) {
            $in        = $row->direction === AccountTransaction::IN;
            $completed = $row->status === AccountTransaction::COMPLETED;

            if ($completed) {
                $balance  = Decimal::round(bcadd($opening, Decimal::of($row->running_net), 10));
                $moneyIn  = $in ? $this->add($moneyIn, (string) $row->amount) : $moneyIn;
                $moneyOut = $in ? $moneyOut : $this->add($moneyOut, (string) $row->amount);
            }

            $rows[] = [
                'date'      => $row->transaction_date,
                'type'      => AccountTransaction::LABELS[$row->type] ?? Str::headline($row->type),
                'reference' => $row->reference,
                'contact'   => $row->contact?->name,
                'document'  => $row->document?->number,
                'money_in'  => $in ? (string) $row->amount : null,
                'money_out' => $in ? null : (string) $row->amount,
                'balance'   => $balance,
                'status'    => Str::headline($row->status),
                'location'  => $row->location?->name,
                // A payment or refund opens its receipt; a transfer, the account's page, where its dialog is.
                'link'      => str_starts_with($row->type, 'customer_') ? "/sales/payments/{$row->source_id}" : "/payment-accounts/{$account->id}",
            ];
        }

        return new ReportTable(
            title: 'Payment Account Transactions',
            period: $this->between($filters)." · {$account->name} ({$currency})",
            columns: [
                $this->column('date', 'Date', 'date'),
                $this->column('type', 'Type'),
                $this->column('reference', 'Reference'),
                $this->column('contact', 'Contact'),
                $this->column('document', 'Source Document'),
                $this->column('money_in', 'Money In', 'money', $currency),
                $this->column('money_out', 'Money Out', 'money', $currency),
                $this->column('balance', 'Running Balance', 'money', $currency),
                $this->column('status', 'Status'),
                $this->column('location', 'Location'),
            ],
            rows: $rows,
            totals: ['date' => 'Total', 'money_in' => $moneyIn, 'money_out' => $moneyOut, 'balance' => $balance],
            summary: [
                ['label' => 'Brought Forward', 'value' => $forward, 'currency' => $currency],
                ['label' => 'Money In', 'value' => $moneyIn, 'currency' => $currency],
                ['label' => 'Money Out', 'value' => $moneyOut, 'currency' => $currency],
                ['label' => 'Closing Balance', 'value' => $balance, 'currency' => $currency],
            ],
            notes: [
                "In {$currency}, the account's own currency. Reversed rows show, but count toward neither the totals nor the balance.",
                'A recorded balance - not one the bank has confirmed or reconciled.',
            ],
            sortable: false,
        );
    }

    /** A row with only a date, what it is and the balance: the one brought forward, or the opening balance. */
    private function line(string $date, string $type, string $balance): array
    {
        return [
            'date' => $date, 'type' => $type, 'reference' => null, 'contact' => null, 'document' => null,
            'money_in' => null, 'money_out' => null, 'balance' => $balance, 'status' => null, 'location' => null,
        ];
    }
}
