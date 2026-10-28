<?php

namespace App\Http\Requests\Api\BusinessSetup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** ISO 4217 codes are upper case by definition. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper(trim($this->input('currency')))]);
        }
    }

    /**
     * A rate for one of the business's other currencies - not the base, which
     * is always 1 - in base currency per unit, above zero, to eight decimal
     * places, from a day on.
     */
    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');

        return [
            'currency'       => ['required', 'string', Rule::in($workspace?->additional_currencies ?? [])],
            'rate'           => ['required', 'numeric', 'decimal:0,8', 'gt:0', 'max:9999999999'],
            'effective_date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function attributes(): array
    {
        return [
            'effective_date' => 'date',
        ];
    }

    public function messages(): array
    {
        return [
            'currency.in'  => 'Choose one of your other currencies. Switch it on under Settings > Currencies first.',
            'rate.gt'      => 'A rate is above zero.',
            'rate.decimal' => 'A rate can have at most eight decimal places.',
        ];
    }
}
