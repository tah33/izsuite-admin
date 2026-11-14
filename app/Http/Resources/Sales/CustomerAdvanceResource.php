<?php

namespace App\Http\Resources\Sales;

use App\Http\Resources\Concerns\HidesValues;
use App\Models\User\CustomerAdvance;
use App\Services\Shared\AllowedActions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A customer advance's page (Doc 16 Phase D1): who paid, into which account,
 * what for, how much and what is left to apply, where it went, and what can
 * be done with it now. `account_amount` is in the account's currency.
 */
class CustomerAdvanceResource extends JsonResource
{
    use HidesValues;

    private const VALUES = ['amount', 'account_amount', 'exchange_rate', 'allocated_amount', 'balance'];

    public function toArray(Request $request): array
    {
        $open = $this->status === CustomerAdvance::COMPLETED;

        return $this->hideValues('sales', [
            'id'               => $this->id,
            'number'           => $this->number,
            'status'           => $this->status,
            'actions'          => app(AllowedActions::class)->advance(array_values(array_filter([
                $open && bccomp((string) $this->balance, '0', 2) > 0 ? 'apply' : null,
                $open && bccomp((string) $this->allocated_amount, '0', 2) === 0 ? 'reverse' : null,
            ]))),
            'customer'         => ['id' => $this->contact_id, 'name' => $this->contact?->name],
            'document'         => $this->document ? ['id' => $this->document->id, 'type' => $this->document->type->value, 'number' => $this->document->number] : null,
            'payment'          => $this->payment ? ['id' => $this->payment->id, 'number' => $this->payment->number] : null,
            'account'          => ['id' => $this->payment_account_id, 'name' => $this->account?->name, 'currency' => $this->account?->currency],
            'location'         => $this->location?->name,
            'payment_date'     => $this->payment_date,
            'method'           => $this->method,
            'reference'        => $this->reference,
            'notes'            => $this->notes,
            'currency'         => $this->currency,
            'amount'           => $this->amount,
            'account_amount'   => $this->account_amount,
            'exchange_rate'    => $this->exchange_rate,
            'allocated_amount' => $this->allocated_amount,
            'balance'          => $this->balance,
            'allocations'      => SalesAllocationResource::collection($this->allocations),
            'reversed_at'      => $this->reversed_at?->toIso8601String(),
            'reversed_by'      => $this->reverser?->name,
            'reversal_reason'  => $this->reversal_reason,
            'made_by'          => $this->creator?->name,
        ], self::VALUES);
    }
}
