<?php

namespace App\Http\Resources\Sales;

use App\Http\Resources\Concerns\HidesValues;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of Payments Received's Advances or a customer's (Doc 16 Phase D1):
 * what the row shows. Expects contact, account and document loaded.
 */
class CustomerAdvanceListResource extends JsonResource
{
    use HidesValues;

    private const VALUES = ['amount', 'balance'];

    public function toArray(Request $request): array
    {
        return $this->hideValues('sales', [
            'id'           => $this->id,
            'number'       => $this->number,
            'status'       => $this->status,
            'payment_date' => $this->payment_date,
            'customer'     => ['id' => $this->contact_id, 'name' => $this->contact?->name],
            'document'     => $this->document ? ['id' => $this->document->id, 'type' => $this->document->type->value, 'number' => $this->document->number] : null,
            'account'      => $this->account?->name,
            'currency'     => $this->currency,
            'amount'       => $this->amount,
            'balance'      => $this->balance,
        ], self::VALUES);
    }
}
