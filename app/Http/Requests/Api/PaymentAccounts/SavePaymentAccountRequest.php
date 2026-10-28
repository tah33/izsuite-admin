<?php

namespace App\Http\Requests\Api\PaymentAccounts;

use App\Models\User\PaymentAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SavePaymentAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** ISO 4217 codes are upper case by definition. */
    protected function prepareForValidation(): void
    {
        if ($this->filled('currency')) {
            $this->merge(['currency' => strtoupper((string) $this->input('currency'))]);
        }
    }

    /**
     * The same for adding and for editing. An account uses one currency (Doc
     * 01 section 11): the business's base currency or one it has switched on
     * under Settings > Currencies.
     *
     * The opening balance may be negative - an overdrawn bank account - and is
     * to the cent; left empty, it is zero. One that is not zero needs the date
     * it was the balance on (Doc 05 section 5).
     */
    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');

        return [
            'type'                 => ['required', Rule::in(PaymentAccount::TYPES)],
            'name'                 => ['required', 'string', 'max:255'],
            'currency'             => ['required', 'string', Rule::in($workspace?->currencyCodes() ?? [])],
            'bank_name'            => ['nullable', 'string', 'max:255'],
            'account_number'       => ['nullable', 'string', 'max:50'],
            'opening_balance'      => ['nullable', 'numeric', 'decimal:0,2', 'between:-9999999999999.99,9999999999999.99'],
            'opening_balance_date' => ['nullable', 'date_format:Y-m-d'],
            'notes'                => ['nullable', 'string', 'max:2000'],
            'is_active'            => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $balance = $this->input('opening_balance');

                if (is_numeric($balance) && (float) $balance !== 0.0 && ! $this->filled('opening_balance_date') && ! $validator->errors()->has('opening_balance')) {
                    $validator->errors()->add('opening_balance_date', 'Enter the date the opening balance was the balance on.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'bank_name'            => 'bank or provider',
            'opening_balance_date' => 'opening balance date',
        ];
    }

    public function messages(): array
    {
        return [
            'type.in'     => 'Choose what kind of account this is.',
            'currency.in' => 'Use the base currency or one switched on under Settings > Currencies.',
            'decimal'     => 'The :attribute can have at most two decimal places.',
        ];
    }
}
