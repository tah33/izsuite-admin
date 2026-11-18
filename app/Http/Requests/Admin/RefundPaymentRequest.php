<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Refunding a payment (Doc 15 sections 14 and 15.25): how much, why, the
 * admin's password again - CriticalActionService checks it and the reason -
 * and, for a transfer, the bank's reference of izSuite's transfer back.
 */
class RefundPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'             => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999'],
            'reason'             => ['required', 'string', 'min:5', 'max:500'],
            'confirm_password'   => ['required', 'string'],
            'transfer_reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required'           => 'Enter the amount to refund.',
            'amount.decimal'            => 'Give the amount with no more than two decimals.',
            'amount.gt'                 => 'Enter the amount to refund.',
            'reason.required'           => 'Give the reason. The audit log keeps it.',
            'reason.min'                => 'Give the reason. The audit log keeps it.',
            'confirm_password.required' => 'Enter your password to confirm the refund.',
        ];
    }
}
