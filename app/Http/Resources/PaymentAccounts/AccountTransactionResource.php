<?php

namespace App\Http\Resources\PaymentAccounts;

use App\Models\User\AccountTransfer;
use App\Models\User\SupplierPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of a payment account's history (Doc 05 section 11): its date, type,
 * reference, contact, source document - an invoice or credit note, or the
 * purchase a supplier payment paid - the amount in or out, its status and
 * location, and the running balance PaymentAccountHistory worked out.
 * `source` says what to open for it: a payment or refund's receipt, a
 * supplier payment's voucher, or a transfer. Amounts go out as strings, in
 * the account's currency.
 */
class AccountTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'transaction_date' => $this->transaction_date,
            'type'             => $this->type,
            'direction'        => $this->direction,
            'amount'           => $this->amount,
            'status'           => $this->status,
            'reference'        => $this->reference,
            'contact'          => $this->contact ? ['id' => $this->contact->id, 'name' => $this->contact->name] : null,
            'document'         => match (true) {
                (bool) $this->document => ['id' => $this->document->id, 'kind' => $this->document->type->kind(), 'number' => $this->document->number],
                (bool) $this->purchase => ['id' => $this->purchase->id, 'kind' => 'purchases', 'number' => $this->purchase->number],
                default                => null,
            },
            'location'         => $this->location?->name,
            'source'           => ['type' => match ($this->source_type) {
                (new AccountTransfer)->getMorphClass() => 'transfer',
                (new SupplierPayment)->getMorphClass() => 'supplier_payment',
                default                                => 'payment',
            }, 'id' => $this->source_id],
            'running_balance'  => $this->running_balance,
        ];
    }
}
