<?php

namespace App\Repositories\User;

use App\Models\User\AccountTransfer;
use App\Models\User\Workspace;

/**
 * Transfers between a business's payment accounts. Every lookup is scoped to
 * the business, so another business's transfer is simply not found.
 */
class AccountTransferRepository
{
    /** What a transfer's dialog shows with it. */
    private const SHOWN_WITH = ['fromAccount:id,name,currency', 'toAccount:id,name,currency'];

    public function findFor(Workspace $workspace, int $id): ?AccountTransfer
    {
        return $workspace->accountTransfers()->with(self::SHOWN_WITH)->find($id);
    }

    public function loadShown(AccountTransfer $transfer): AccountTransfer
    {
        return $transfer->load(self::SHOWN_WITH);
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): AccountTransfer
    {
        return $workspace->accountTransfers()->create($data);
    }

    public function update(AccountTransfer $transfer, array $data): AccountTransfer
    {
        $transfer->update($data);

        return $transfer;
    }

    public function delete(AccountTransfer $transfer): void
    {
        $transfer->delete();
    }
}
