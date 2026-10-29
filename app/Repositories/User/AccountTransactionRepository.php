<?php

namespace App\Repositories\User;

use App\Models\User\AccountTransaction;
use App\Models\User\PaymentAccount;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * The payment accounts' history (Doc 05 sections 10 and 11). Rows are written
 * and changed only through what they record - a payment, refund or transfer -
 * and read per account.
 *
 * The running balance is the opening balance plus every completed row up to
 * and including this one, oldest first; it is worked out in the query
 * (`running_net`), so a backdated row takes its place and a filtered page
 * still shows the account's real balance.
 */
class AccountTransactionRepository
{
    /** The signed effect of a completed row: + in, - out; nothing for any other status. */
    private const NET = "CASE WHEN status = 'completed' THEN (CASE WHEN direction = 'in' THEN amount ELSE -amount END) ELSE 0 END";

    /** What a history row shows with it. */
    private const SHOWN_WITH = ['contact:id,name', 'document:id,type,number', 'location:id,name'];

    /** A page of an account's history, newest first, each row with its running net. */
    public function paginateFor(PaymentAccount $account, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->history($account, $filters)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /** All of an account's history that $filters let through, oldest first - for an export. */
    public function allFor(PaymentAccount $account, array $filters): Collection
    {
        return $this->history($account, $filters)->orderBy('transaction_date')->orderBy('id')->get();
    }

    /** The net of each account's completed history, by account id: what to add to its opening balance. */
    public function netByAccount(Workspace $workspace): array
    {
        return $workspace->accountTransactions()
            ->selectRaw('payment_account_id, SUM('.self::NET.') as net')
            ->groupBy('payment_account_id')
            ->pluck('net', 'payment_account_id')
            ->all();
    }

    /** The same, of what was dated on or before $date - the balances as they stood that day. */
    public function netByAccountUntil(Workspace $workspace, string $date): array
    {
        return $workspace->accountTransactions()
            ->selectRaw('payment_account_id, SUM('.self::NET.') as net')
            ->where('transaction_date', '<=', $date)
            ->groupBy('payment_account_id')
            ->pluck('net', 'payment_account_id')
            ->all();
    }

    /** The net of one account's completed history, as the database sums it. */
    public function netFor(PaymentAccount $account): mixed
    {
        return $account->transactions()->selectRaw('SUM('.self::NET.') as net')->value('net');
    }

    /** The net of one account's completed history dated before $date: what had moved by the morning of that day. */
    public function netBefore(PaymentAccount $account, string $date): mixed
    {
        return $account->transactions()->where('transaction_date', '<', $date)->selectRaw('SUM('.self::NET.') as net')->value('net');
    }

    /** The ids of the accounts among the business's that have any history at all. */
    public function accountsWithHistory(Workspace $workspace): array
    {
        return $workspace->accountTransactions()->distinct()->pluck('payment_account_id')->all();
    }

    public function existsFor(PaymentAccount $account): bool
    {
        return $account->transactions()->exists();
    }

    /** Whether money has moved through any of the business's accounts - which ties its base currency down. */
    public function existsForWorkspace(Workspace $workspace): bool
    {
        return $workspace->accountTransactions()->exists();
    }

    /** The date of the account's earliest row, any status. */
    public function firstDateFor(PaymentAccount $account): ?string
    {
        return $account->transactions()->min('transaction_date');
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): AccountTransaction
    {
        return $workspace->accountTransactions()->create($data);
    }

    /** Every row $source wrote - a payment's one, a transfer's two - changed alike. */
    public function updateFor(Model $source, array $data): void
    {
        AccountTransaction::query()->where('source_type', $source->getMorphClass())->where('source_id', $source->getKey())->update($data);
    }

    public function deleteFor(Model $source): void
    {
        AccountTransaction::query()->where('source_type', $source->getMorphClass())->where('source_id', $source->getKey())->delete();
    }

    /**
     * The account's rows with their running net, worked out over the whole
     * history before $filters narrow it - read through a subquery under the
     * table's own name, so the relations still load.
     */
    private function history(PaymentAccount $account, array $filters): Builder
    {
        $ledger = AccountTransaction::query()
            ->select('*')
            ->selectRaw('SUM('.self::NET.') OVER (ORDER BY transaction_date, id) as running_net')
            ->where('payment_account_id', $account->id);

        return AccountTransaction::query()
            ->fromSub($ledger, 'account_transactions')
            ->with(self::SHOWN_WITH)
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->where('transaction_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->where('transaction_date', '<=', $date));
    }
}
