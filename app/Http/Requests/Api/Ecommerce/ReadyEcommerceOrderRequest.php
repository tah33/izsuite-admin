<?php

namespace App\Http\Requests\Api\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;

/** A pickup order made ready to collect (Doc 19 Phase G1): the day, and notes for the business alone. */
class ReadyEcommerceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ready_on' => ['required', 'date_format:Y-m-d'],
            'notes'    => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['ready_on' => 'day it was ready'];
    }
}
