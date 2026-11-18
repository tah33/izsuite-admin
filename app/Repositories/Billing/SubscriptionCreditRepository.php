<?php

namespace App\Repositories\Billing;

use App\Models\Billing\SubscriptionCredit;
use App\Models\User\Workspace;

/** izSuite's credit to businesses (Doc 15 Phase C2b), as the ledger of its movements. */
class SubscriptionCreditRepository
{
    public function create(array $data): SubscriptionCredit
    {
        return SubscriptionCredit::create($data);
    }

    /** The business's credit in $currency: the sum of its movements - locked to the end of the transaction when $lock, while it is taken. */
    public function balance(Workspace $workspace, string $currency, bool $lock = false): string
    {
        return SubscriptionCredit::where('workspace_id', $workspace->id)
            ->where('currency', $currency)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->pluck('amount')
            ->reduce(fn (string $sum, $amount) => bcadd($sum, (string) $amount, 2), '0.00');
    }

    /** @return array<string, string> the business's credit by currency, where it has some */
    public function balances(Workspace $workspace): array
    {
        return SubscriptionCredit::where('workspace_id', $workspace->id)
            ->get(['currency', 'amount'])
            ->groupBy('currency')
            ->map(fn ($movements) => $movements->reduce(fn (string $sum, SubscriptionCredit $movement) => bcadd($sum, (string) $movement->amount, 2), '0.00'))
            ->filter(fn (string $sum) => bccomp($sum, '0', 2) > 0)
            ->all();
    }
}
