<?php

namespace App\Http\Resources\Purchases;

use App\Services\Shared\AllowedActions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A supplier payment's voucher, whole - for its page (Doc 06 section 13): its
 * number, date, the purchase it paid, who was paid, the account and how, the
 * amounts, what the purchase had left just after it, and - once reversed -
 * when, by whom and why. Amounts go out as strings to the cent; `amount` is
 * in the account's currency, `applied_amount` in the purchase's. Expects
 * purchase, contact, account, creator and reverser loaded.
 */
class SupplierPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'number'          => $this->number,
            'status'          => $this->status,
            'payment_date'    => $this->payment_date,
            'method'          => $this->method,
            'reference'       => $this->reference,
            'notes'           => $this->notes,
            'supplier'        => ['id' => $this->contact_id, 'name' => $this->contact?->name],
            'purchase'        => ['id' => $this->purchase->id, 'number' => $this->purchase->number, 'currency' => $this->purchase->currency],
            'account'         => ['id' => $this->account->id, 'name' => $this->account->name, 'currency' => $this->account->currency],
            'amount'          => $this->amount,
            'applied_amount'  => $this->applied_amount,
            'withholding'     => $this->withholding_tax_id || $this->withholding_name ? [
                'name'   => $this->withholding_name,
                'rate'   => $this->withholding_rate,
                'amount' => $this->withholding_amount,
            ] : null,
            'balance_after'   => $this->balance_after,
            'recorded_by'     => $this->creator ? trim("{$this->creator->first_name} {$this->creator->last_name}") : null,
            'reversed_at'     => $this->reversed_at?->toIso8601String(),
            'reversed_by'     => $this->reverser ? trim("{$this->reverser->first_name} {$this->reverser->last_name}") : null,
            'reversal_reason' => $this->reversal_reason,
            'actions'         => app(AllowedActions::class)->payment('purchases', $this->isCompleted() ? ['reverse'] : []),
        ];
    }
}
