<?php

namespace App\Http\Requests\Api\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;

/** A shipment's carrier, tracking number and link, and notes, corrected (Doc 19 Phase G1). Empty clears one. */
class UpdateEcommerceFulfilmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'carrier'         => ['nullable', 'string', 'max:100'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'tracking_url'    => ['nullable', 'string', 'max:500', 'url:https,http'],
            'notes'           => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['tracking_url.url' => 'Give the tracking link as a web address, starting https://.'];
    }
}
