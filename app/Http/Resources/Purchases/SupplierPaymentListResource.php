<?php

namespace App\Http\Resources\Purchases;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of Payments Made (Doc 09 section 4), or of a purchase's payment
 * history on its page: what the row shows, no more. `amount` is in the
 * account's currency, `applied_amount` and `withholding_amount` in the
 * purchase's. Expects account loaded - and, for Payments Made, purchase and
 * contact.
 */
class SupplierPaymentListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'number'             => $this->number,
            'status'             => $this->status,
            'payment_date'       => $this->payment_date,
            'method'             => $this->method,
            'reference'          => $this->reference,
            'supplier'           => $this->whenLoaded('contact', fn () => $this->contact?->name),
            'purchase'           => $this->whenLoaded('purchase', fn () => ['id' => $this->purchase_id, 'number' => $this->purchase?->number]),
            'account'            => ['name' => $this->account?->name, 'currency' => $this->account?->currency],
            'amount'             => $this->amount,
            'applied_amount'     => $this->applied_amount,
            'withholding_amount' => $this->withholding_amount,
        ];
    }
}
