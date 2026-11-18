<?php

namespace App\Http\Requests\Api\Sales;

use App\Models\User\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A customer's credit control (Doc 16 Phase G1): a limit in the base
 * currency (empty for none), whether they buy on credit at all, what going
 * over does, and a note shown when a sale is made for them.
 */
class SaveCustomerCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'credit_limit'   => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
            'credit_sales'   => ['required', 'boolean'],
            'credit_action'  => ['required', Rule::in([Contact::CREDIT_WARN, Contact::CREDIT_BLOCK])],
            'credit_warning' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'credit_limit.min'     => 'A credit limit cannot be below zero.',
            'credit_limit.decimal' => 'A credit limit has at most two decimal places.',
            'credit_action.in'     => 'Choose whether going over the limit warns or blocks.',
        ];
    }
}
