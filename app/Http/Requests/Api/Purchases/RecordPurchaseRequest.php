<?php

namespace App\Http\Requests\Api\Purchases;

use Illuminate\Foundation\Http\FormRequest;

class RecordPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Recording a draft (Doc 09 section 10). A supplier invoice recorded
     * before (section 11) is recorded again only once `confirmed`; an exact
     * duplicate needs the `reason` it is recorded anyway, which it keeps.
     */
    public function rules(): array
    {
        return [
            'confirmed' => ['nullable', 'boolean'],
            'reason'    => ['nullable', 'string', 'max:500'],
            // A number typed rather than the next one (Doc 14 section 14.6) - DocumentNumbers::typed checks the role and that it is free
            'number'    => ['nullable', 'string', 'max:40', 'regex:#^[A-Za-z0-9][A-Za-z0-9 ._/-]*$#'],
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
