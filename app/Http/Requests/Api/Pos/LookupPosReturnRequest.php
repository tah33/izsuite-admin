<?php

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;

/** The sale a POS return starts from (Doc 18 Phase E1): its receipt number, as typed or scanned. */
class LookupPosReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receipt' => ['required', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'receipt.required' => 'Type or scan the receipt number.',
        ];
    }
}
