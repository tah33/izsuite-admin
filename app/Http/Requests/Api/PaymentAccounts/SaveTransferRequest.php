<?php

namespace App\Http\Requests\Api\PaymentAccounts;

use Illuminate\Foundation\Http\FormRequest;

class SaveTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A transfer between two of the business's accounts (Doc 05 section 13):
     * the accounts - not the same one - the date, the amount sent and a
     * reference are required. amount_received is what arrived when the To
     * account uses another currency; AccountTransferService asks for it then,
     * and checks both accounts are the business's and active. `complete`
     * moves the money now rather than saving a draft.
     */
    public function rules(): array
    {
        return [
            'from_account_id' => ['required', 'integer'],
            'to_account_id'   => ['required', 'integer', 'different:from_account_id'],
            'transfer_date'   => ['required', 'date_format:Y-m-d'],
            'amount'          => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'amount_received' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'reference'       => ['required', 'string', 'max:100'],
            'notes'           => ['nullable', 'string', 'max:2000'],
            'complete'        => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'from_account_id' => 'From account',
            'to_account_id'   => 'To account',
            'transfer_date'   => 'date',
            'amount_received' => 'amount received',
        ];
    }

    public function messages(): array
    {
        return [
            'to_account_id.different' => 'Money is transferred to another account.',
            'decimal'                 => 'The :attribute can have at most two decimal places.',
            'gt'                      => 'The :attribute must be more than zero.',
        ];
    }
}
