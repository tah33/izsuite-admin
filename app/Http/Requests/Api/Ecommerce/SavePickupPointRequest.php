<?php

namespace App\Http\Requests\Api\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;

/** A location as a pickup point (Doc 19 Phase D1): switched on or off, its instructions, and when an order is ready there. */
class SavePickupPointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_active'    => ['required', 'boolean'],
            'instructions' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'ready_within' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'instructions.max' => 'Keep the instructions to 1,000 characters.',
            'ready_within.max' => 'Keep it to 100 characters.',
        ];
    }
}
