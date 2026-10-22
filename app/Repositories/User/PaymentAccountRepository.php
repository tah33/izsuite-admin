<?php

namespace App\Repositories\User;

use App\Models\User\PaymentAccount;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Collection;

/**
 * Payment accounts belong to one business (workspace). Every lookup is scoped
 * to it, so another business's account - the same owner's or anyone else's -
 * is simply not found.
 */
class PaymentAccountRepository
{
    /** All of them, alphabetical: a business has a handful, and the list shows every one. */
    public function getFor(Workspace $workspace): Collection
    {
        return $workspace->paymentAccounts()->orderBy('name')->orderBy('id')->get();
    }

    public function findFor(Workspace $workspace, int $id): ?PaymentAccount
    {
        return $workspace->paymentAccounts()->find($id);
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): PaymentAccount
    {
        return $workspace->paymentAccounts()->create($data);
    }

    public function update(PaymentAccount $account, array $data): PaymentAccount
    {
        $account->update($data);

        return $account;
    }

    public function delete(PaymentAccount $account): void
    {
        $account->delete();
    }
}
