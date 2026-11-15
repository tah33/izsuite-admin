<?php

namespace App\Http\Requests\Frontend;

use Illuminate\Foundation\Http\FormRequest;

/** A customer's answer to a quotation through its secure link (Doc 16 Phase G2): their name, and - declining - why. */
class AnswerQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'   => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'your name', 'reason' => 'why'];
    }
}
