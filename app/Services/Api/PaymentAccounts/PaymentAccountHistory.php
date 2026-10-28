<?php

namespace App\Services\Api\PaymentAccounts;

use App\Models\User\AccountTransaction;
use App\Models\User\PaymentAccount;
use App\Models\User\Workspace;
use App\Repositories\User\AccountTransactionRepository;
use App\Support\Csv;
use App\Support\Decimal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A payment account's history (Doc 05 section 11): each money in or out with
 * its running balance - the opening balance plus every completed row up to
 * and including it - newest first on the page, and as a CSV file oldest
 * first (section 16, Export Transactions).
 *
 * Rows are only read here. A payment, refund or transfer is changed or
 * reversed from where it was made (section 11).
 */
class PaymentAccountHistory
{
    /** What each type is called in an export. */
    private const TYPES = [
        'customer_payment' => 'Customer Payment',
        'customer_refund'  => 'Customer Refund',
        'transfer_in'      => 'Transfer In',
        'transfer_out'     => 'Transfer Out',
    ];

    public function __construct(
        protected PaymentAccountService $paymentAccountService,
        protected AccountTransactionRepository $accountTransactionRepository,
    ) {}

    public function page(Workspace $workspace, int $accountId, array $filters, int $perPage): LengthAwarePaginator
    {
        $account = $this->paymentAccountService->find($workspace, $accountId);
        $page    = $this->accountTransactionRepository->paginateFor($account, $filters, $perPage);

        $page->getCollection()->each(fn (AccountTransaction $row) => $row->setAttribute('running_balance', $this->running($account, $row)));

        return $page;
    }

    public function export(Workspace $workspace, int $accountId, array $filters): StreamedResponse
    {
        $account = $this->paymentAccountService->find($workspace, $accountId);
        $rows    = $this->accountTransactionRepository->allFor($account, $filters);

        return Csv::download(
            Str::slug($account->name).'-transactions.csv',
            ['Date', 'Type', 'Reference', 'Contact', 'Source Document', 'Money In', 'Money Out', 'Currency', 'Running Balance', 'Status', 'Location'],
            $rows->map(fn (AccountTransaction $row) => [
                $row->transaction_date,
                self::TYPES[$row->type] ?? $row->type,
                $row->reference,
                $row->contact?->name,
                $row->document?->number,
                $row->direction === AccountTransaction::IN ? $row->amount : null,
                $row->direction === AccountTransaction::OUT ? $row->amount : null,
                $account->currency,
                $this->running($account, $row),
                Str::headline($row->status),
                $row->location?->name,
            ]),
        );
    }

    /** The account's balance just after $row: its opening balance plus the completed history up to it. */
    private function running(PaymentAccount $account, AccountTransaction $row): string
    {
        return Decimal::round(bcadd((string) $account->opening_balance, Decimal::of($row->running_net), 10));
    }
}
