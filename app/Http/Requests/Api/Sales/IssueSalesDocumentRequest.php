<?php

namespace App\Http\Requests\Api\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueSalesDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Issuing a draft (Doc 08 section 8). An invoice in another currency whose
     * own exchange rate differs from the business's rate for its date says
     * which to issue it at: its own ('keep') or that day's ('current').
     */
    public function rules(): array
    {
        return [
            'exchange_rate' => ['nullable', Rule::in(['keep', 'current'])],
            // A number typed rather than the next one (Doc 14 section 14.6) - DocumentNumbers::typed checks the role and that it is free
            'number'        => ['nullable', 'string', 'max:40', 'regex:#^[A-Za-z0-9][A-Za-z0-9 ._/-]*$#'],
        ];
    }

    public function messages(): array
    {
        return [
            'number.regex' => 'A number may have letters, digits, spaces and . _ / - only, starting with a letter or digit.',
            'number.max'   => 'A number can be at most 40 characters.',
        ];
    }
}
