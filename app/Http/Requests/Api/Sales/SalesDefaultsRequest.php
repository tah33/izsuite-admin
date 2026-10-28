<?php

namespace App\Http\Requests\Api\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SalesDefaultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper($this->input('currency'))]);
        }
    }

    /** What a new document's defaults follow: the customer chosen, its date, and a currency picked by hand. */
    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');

        return [
            'contact_id'    => ['nullable', 'integer'],
            'document_date' => ['nullable', 'date_format:Y-m-d'],
            'currency'      => ['nullable', 'string', Rule::in($workspace?->currencyCodes() ?? [])],
        ];
    }
}
