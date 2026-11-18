<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** A privacy request completed - what was done - or rejected - why - and whether the person asking is told (Doc 15 Phase G1). */
class ResolvePrivacyRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'outcome' => ['required', 'string', 'min:5', 'max:2000'],
            'notify'  => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'outcome.required' => 'Say what was done, or why not.',
            'outcome.min'      => 'Say what was done, or why not.',
        ];
    }
}
