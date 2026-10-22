<?php

namespace App\Http\Requests\Api\BusinessSetup;

use App\Models\Admin\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBusinessCurrenciesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * ISO 4217 codes are upper case by definition, and MySQL's case-insensitive
     * comparison would let "usd" past the exists rule and into the column,
     * where it would never match the "USD" the currency list offers.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('base_currency')) {
            $this->merge(['base_currency' => strtoupper((string) $this->input('base_currency'))]);
        }

        if (is_array($this->input('additional_currencies'))) {
            $this->merge(['additional_currencies' => array_map(
                fn ($code) => is_string($code) ? strtoupper($code) : $code,
                $this->input('additional_currencies'),
            )]);
        }
    }

    /**
     * Each code must be a currency an admin has left active - the list GET
     * /currencies hands the form. The additional ones are the business's
     * other currencies, so the base one is not among them.
     */
    public function rules(): array
    {
        $active = Rule::exists(Currency::class, 'code')->where('is_active', true);

        return [
            'base_currency'           => ['required', 'string', $active],
            'additional_currencies'   => ['nullable', 'array'],
            'additional_currencies.*' => ['string', 'distinct', 'different:base_currency', $active],
        ];
    }

    public function attributes(): array
    {
        return [
            'base_currency'           => 'base currency',
            'additional_currencies'   => 'other currencies',
            'additional_currencies.*' => 'other currency',
        ];
    }

    public function messages(): array
    {
        return [
            'base_currency.exists'              => 'Choose one of the available currencies.',
            'additional_currencies.*.exists'    => 'Choose only from the available currencies.',
            'additional_currencies.*.distinct'  => 'A currency can be chosen only once.',
            'additional_currencies.*.different' => 'The base currency is already the business\'s own; it is not another one.',
        ];
    }
}
