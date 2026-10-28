<?php

namespace App\Http\Resources\PaymentAccounts;

use App\Models\User\AccountTransfer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A transfer between two payment accounts, for its dialog: the accounts, the
 * date, what was sent and what arrived - each in its account's currency -
 * the reference and notes, why it was voided, and what it can do now.
 * Expects fromAccount and toAccount loaded.
 */
class AccountTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'status'          => $this->status,
            'transfer_date'   => $this->transfer_date,
            'from'            => ['id' => $this->fromAccount->id, 'name' => $this->fromAccount->name, 'currency' => $this->fromAccount->currency],
            'to'              => ['id' => $this->toAccount->id, 'name' => $this->toAccount->name, 'currency' => $this->toAccount->currency],
            'amount'          => $this->amount,
            'amount_received' => $this->amount_received,
            'exchange_rate'   => $this->exchange_rate,
            'reference'       => $this->reference,
            'notes'           => $this->notes,
            'void_reason'     => $this->void_reason,
            'actions'         => match ($this->status) {
                AccountTransfer::DRAFT     => ['edit', 'complete', 'delete'],
                AccountTransfer::COMPLETED => ['void'],
                default                    => [],
            },
        ];
    }
}
