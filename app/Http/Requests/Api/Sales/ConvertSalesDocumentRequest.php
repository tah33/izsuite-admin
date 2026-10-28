<?php

namespace App\Http\Requests\Api\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvertSalesDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * What it becomes - a proforma or an invoice (Doc 08 section 11) - and,
     * for one converted before, that it is meant to be converted again.
     */
    public function rules(): array
    {
        return [
            'to'    => ['required', Rule::in(['proforma', 'invoice'])],
            'again' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'to.*' => 'Choose whether it becomes a proforma invoice or an invoice.',
        ];
    }
}
