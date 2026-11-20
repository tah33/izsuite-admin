<?php

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A POS sale to complete (Doc 18 Phases C2 and C3): its customer, its lines
 * - each an item, its quantity, a note, and a price typed over the item's
 * (with why) or a discount - the order's discount, and why the discounts go
 * beyond the limit; the order's note, the total the cashier took the payment
 * for, and the payments: each a method of the register's, its amount, what
 * was handed over and its reference.
 */
class CompletePosOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact_id'           => ['required', 'integer'],
            ...QuotePosCartRequest::cart(),
            'lines.*.note'         => ['nullable', 'string', 'max:500'],
            'discount_reason'      => ['nullable', 'string', 'max:500'],
            'note'                 => ['nullable', 'string', 'max:1000'],
            'total'                => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            // None for a sale of nothing to pay.
            'payments'             => ['present', 'array', 'max:10'],
            'payments.*.method_id' => ['required', 'integer'],
            'payments.*.amount'    => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'payments.*.tendered'  => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
            // Phase D3a: what the payments leave stays on the customer's account - and why it may go over their credit limit.
            'on_account'           => ['sometimes', 'boolean'],
            'credit_reason'        => ['nullable', 'string', 'max:500'],
            // Phase D3b: how much of the customer's own credit pays it.
            'credit'               => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            // Phase E3: the exchange's return whose credit pays it first.
            'exchange_return_id'   => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            ...QuotePosCartRequest::cartMessages(),
            'contact_id.required'  => 'Choose the customer: a sale is made to one. Settings → Point of Sale can name a walk-in customer.',
            'payments.max'         => 'A sale takes up to 10 payments.',
            'payments.*.amount.gt' => 'A payment pays more than 0.',
        ];
    }
}
