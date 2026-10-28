<?php

namespace App\Http\Resources\Sales;

use App\Models\User\SalesPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A customer payment's receipt, or a refund, whole - for its page (Doc 06
 * section 13): its number, date, the document it settled, who paid, the
 * account and how, the amounts, what the document had left just after it,
 * and - once reversed - when, by whom and why. Amounts go out as strings to
 * the cent; `amount` is in the account's currency, `applied_amount` in the
 * document's. Expects document, contact, account, creator, reverser and
 * emails.sender loaded.
 */
class SalesPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $document = $this->document;

        return [
            'id'             => $this->id,
            'kind'           => $this->kind,
            'number'         => $this->number,
            'status'         => $this->status,
            'payment_date'   => $this->payment_date,
            'method'         => $this->method,
            'reference'      => $this->reference,
            'notes'          => $this->notes,
            'customer'       => ['id' => $this->contact_id, 'name' => $this->contact?->name],
            'document'       => ['id' => $document->id, 'kind' => $document->type->kind(), 'label' => $document->type->label(), 'number' => $document->number, 'currency' => $document->currency],
            'account'        => ['id' => $this->account->id, 'name' => $this->account->name, 'currency' => $this->account->currency],
            'amount'         => $this->amount,
            'applied_amount' => $this->applied_amount,
            'exchange_rate'  => $this->exchange_rate,
            'withholding'    => $this->withholding_tax_id || $this->withholding_name ? [
                'name'   => $this->withholding_name,
                'code'   => $this->withholding_code,
                'rate'   => $this->withholding_rate,
                'amount' => $this->withholding_amount,
            ] : null,
            'balance_after'   => $this->balance_after,
            'recorded_by'     => $this->creator ? trim("{$this->creator->first_name} {$this->creator->last_name}") : null,
            'reversed_at'     => $this->reversed_at?->toIso8601String(),
            'reversed_by'     => $this->reverser ? trim("{$this->reverser->first_name} {$this->reverser->last_name}") : null,
            'reversal_reason' => $this->reversal_reason,
            'actions'         => $this->isCompleted() ? ['reverse'] : [],
            // A payment's receipt is emailed while the payment stands; a refund has none.
            'delivery'        => $this->kind === SalesPayment::PAYMENT && $this->isCompleted() ? ['email'] : [],
            'emails'          => DocumentEmailResource::collection($this->whenLoaded('emails')),
        ];
    }
}
