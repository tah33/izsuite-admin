<?php

namespace App\Http\Requests\Api\Items;

use Illuminate\Foundation\Http\FormRequest;

class SaveItemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'brands_enabled' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'brands_enabled.required' => 'Choose whether the business uses brands.',
            'brands_enabled.boolean'  => 'Choose whether the business uses brands.',
        ];
    }
}
