<?php

namespace App\Http\Requests\Admin;

/** izSuite's credit given to a business, or taken back (Doc 15 Phase C2b): how much, and why. */
class SubscriptionCreditRequest extends SubscriptionReasonRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'amount.required' => 'Enter the amount of credit.',
            'amount.gt'       => 'Enter the amount of credit.',
            'amount.decimal'  => 'Give the amount with no more than two decimals.',
        ];
    }
}
