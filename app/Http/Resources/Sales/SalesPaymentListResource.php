<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of Payments Received (Doc 08 section 2), or of the payments and
 * refunds on an invoice's or credit note's page: what the row shows, no more.
 * `amount` is in the account's currency. Expects account loaded - and, for
 * Payments Received, document and contact.
 */
class SalesPaymentListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'kind'               => $this->kind,
            'number'             => $this->number,
            'status'             => $this->status,
            'payment_date'       => $this->payment_date,
            'method'             => $this->method,
            'customer'           => $this->whenLoaded('contact', fn () => ['id' => $this->contact_id, 'name' => $this->contact?->name]),
            'invoice'            => $this->whenLoaded('document', fn () => ['id' => $this->sales_document_id, 'number' => $this->document?->number]),
            'account'            => ['id' => $this->payment_account_id, 'name' => $this->account?->name, 'currency' => $this->account?->currency],
            'amount'             => $this->amount,
            'applied_amount'     => $this->applied_amount,
            'withholding_amount' => $this->withholding_amount,
        ];
    }
}
