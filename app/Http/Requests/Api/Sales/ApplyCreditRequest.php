<?php

namespace App\Http\Requests\Api\Sales;

use Illuminate\Foundation\Http\FormRequest;

/** Credit applied to an invoice (Doc 16 Phase D1): one advance or one credit note, and how much of it. */
class ApplyCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'advance_id'     => ['required_without:credit_note_id', 'nullable', 'integer'],
            'credit_note_id' => ['required_without:advance_id', 'nullable', 'integer'],
            'amount'         => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'advance_id.required_without'     => 'Choose the advance or credit note to apply.',
            'credit_note_id.required_without' => 'Choose the advance or credit note to apply.',
        ];
    }
}
