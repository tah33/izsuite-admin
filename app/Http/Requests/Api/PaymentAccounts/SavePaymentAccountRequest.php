<?php

namespace App\Http\Requests\Api\PaymentAccounts;

use App\Models\User\Location;
use App\Models\User\PaymentAccount;
use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SavePaymentAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** ISO codes - currencies and countries - are upper case by definition. */
    protected function prepareForValidation(): void
    {
        foreach (['currency', 'settlement_currency', 'bank_country'] as $field) {
            if ($this->filled($field)) {
                $this->merge([$field => strtoupper(trim((string) $this->input($field)))]);
            }
        }
    }

    /**
     * The same for adding and for editing (Doc 05 sections 5 to 7). An account
     * uses one currency (Doc 01 section 11): the business's base currency or
     * one it has switched on under Settings > Currencies. Its name is its own
     * within the business; its location, if it has one, is one of the
     * business's.
     *
     * The opening balance may be negative - an overdrawn bank account - and is
     * to the cent; left empty, it is zero. One that is not zero needs the date
     * it was the balance on (section 5).
     *
     * Its type's details are all optional; the form sends those its type has.
     * Deactivating a default account names the one that takes over, where
     * another could (see PaymentAccountService).
     */
    public function rules(): array
    {
        $workspace  = $this->attributes->get('workspace');
        $currencies = $workspace?->currencyCodes() ?? [];

        return [
            'type'                     => ['required', Rule::in(PaymentAccount::TYPES)],
            'name'                     => ['required', 'string', 'max:255', Rule::unique(PaymentAccount::class, 'name')->where('workspace_id', $workspace?->id)->ignore($this->route('id'))],
            'currency'                 => ['required', 'string', Rule::in($currencies)],
            'location_id'              => ['nullable', 'integer', Rule::exists(Location::class, 'id')->where('workspace_id', $workspace?->id)],
            'bank_name'                => ['nullable', 'string', 'max:255'],
            'holder_name'              => ['nullable', 'string', 'max:255'],
            'account_number'           => ['nullable', 'string', 'max:50'],
            'branch'                   => ['nullable', 'string', 'max:255'],
            'swift_code'               => ['nullable', 'string', 'max:50'],
            'bank_country'             => ['nullable', Rule::in(Countries::codes())],
            'merchant_name'            => ['nullable', 'string', 'max:255'],
            'merchant_number'          => ['nullable', 'string', 'max:50'],
            'settlement_currency'      => ['nullable', 'string', Rule::in($currencies)],
            'opening_balance'          => ['nullable', 'numeric', 'decimal:0,2', 'between:-9999999999999.99,9999999999999.99'],
            'opening_balance_date'     => ['nullable', 'date_format:Y-m-d'],
            'payment_instructions'     => ['nullable', 'string', 'max:2000'],
            'show_on_documents'        => ['sometimes', 'boolean'],
            'allow_customer_payments'  => ['sometimes', 'boolean'],
            'allow_supplier_payments'  => ['sometimes', 'boolean'],
            'notes'                    => ['nullable', 'string', 'max:2000'],
            'is_active'                => ['sometimes', 'boolean'],
            'replacement_receiving_id' => ['nullable', 'integer'],
            'replacement_payment_id'   => ['nullable', 'integer'],
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
            'holder_name'          => 'account holder or registered name',
            'swift_code'           => 'SWIFT, BIC or routing code',
            'merchant_number'      => 'merchant or till number',
            'opening_balance_date' => 'opening balance date',
        ];
    }

    public function messages(): array
    {
        return [
            'type.in'                => 'Choose what kind of account this is.',
            'name.unique'            => 'Another payment account already has this name.',
            'currency.in'            => 'Use the base currency or one switched on under Settings > Currencies.',
            'settlement_currency.in' => 'Use the base currency or one switched on under Settings > Currencies.',
            'location_id.exists'     => 'Choose one of your locations.',
            'bank_country.in'        => 'Choose a country from the list.',
            'decimal'                => 'The :attribute can have at most two decimal places.',
        ];
    }
}
