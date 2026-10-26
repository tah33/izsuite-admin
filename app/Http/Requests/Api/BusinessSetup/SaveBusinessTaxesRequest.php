<?php

namespace App\Http\Requests\Api\BusinessSetup;

use App\Models\User\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBusinessTaxesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** The three answers business setup gives (Doc 01 section 12). */
    public function rules(): array
    {
        return [
            'tax_mode' => ['required', Rule::in(Workspace::TAX_MODES)],
        ];
    }

    public function messages(): array
    {
        return [
            'tax_mode.required' => 'Choose whether the business uses taxes.',
            'tax_mode.in'       => 'Choose whether the business uses taxes.',
        ];
    }
}
