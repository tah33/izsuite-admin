<?php

namespace App\Services\Api\PaymentAccounts;

use App\Models\User\PaymentAccount;
use App\Models\User\Workspace;
use App\Repositories\User\PaymentAccountRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class PaymentAccountService
{
    public function __construct(
        protected PaymentAccountRepository $paymentAccountRepository,
    ) {}

    public function list(Workspace $workspace): Collection
    {
        return $this->paymentAccountRepository->getFor($workspace);
    }

    public function create(Workspace $workspace, array $data): PaymentAccount
    {
        $account = $this->paymentAccountRepository->create($workspace, $this->withOpeningBalance($data));

        // Each write is logged here, by name, so LogApiWriteActivity does not
        // add its own vaguer "POST api/v1/payment-accounts" entry for the same request.
        ActivityLogService::record('created', "Added payment account {$this->quoted($account)}", $account, ['source' => 'api']);

        return $account;
    }

    public function update(Workspace $workspace, int $id, array $data): PaymentAccount
    {
        $account = $this->findOwned($workspace, $id);

        $this->paymentAccountRepository->update($account, $this->withOpeningBalance($data));

        ActivityLogService::record('updated', "Updated payment account {$this->quoted($account)}", $account, [
            'source' => 'api',
            'fields' => array_keys(array_diff_key($account->getChanges(), ['updated_at' => true])),
        ]);

        return $account;
    }

    /**
     * Nothing has moved money through an account yet. When payments do, one
     * that has been used must be made inactive instead of deleted.
     */
    public function delete(Workspace $workspace, int $id): void
    {
        $account = $this->findOwned($workspace, $id);

        $this->paymentAccountRepository->delete($account);

        ActivityLogService::record('deleted', "Deleted payment account {$this->quoted($account)}", $account, ['source' => 'api']);
    }

    /** An opening balance left empty is zero, not "unknown". Left out, it is left alone. */
    private function withOpeningBalance(array $data): array
    {
        if (array_key_exists('opening_balance', $data) && $data['opening_balance'] === null) {
            $data['opening_balance'] = 0;
        }

        return $data;
    }

    /**
     * An account of another business - the owner's own other one, or someone
     * else's - answers exactly like one that does not exist.
     */
    private function findOwned(Workspace $workspace, int $id): PaymentAccount
    {
        return $this->paymentAccountRepository->findFor($workspace, $id)
            ?? abort(404, 'Payment account not found.');
    }

    /** The name, in quotes and cut down, so the log entry fits its 255-character column. */
    private function quoted(PaymentAccount $account): string
    {
        return '"'.Str::limit($account->name, 100).'"';
    }
}
