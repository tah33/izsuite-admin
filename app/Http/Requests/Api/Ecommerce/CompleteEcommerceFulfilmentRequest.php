<?php

namespace App\Http\Requests\Api\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;

/** The day a shipment was delivered, or a pickup collected (Doc 19 Phase G1). */
class CompleteEcommerceFulfilmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'completed_on' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function attributes(): array
    {
        return ['completed_on' => 'day'];
    }
}
