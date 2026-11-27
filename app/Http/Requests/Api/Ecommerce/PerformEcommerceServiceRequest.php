<?php

namespace App\Http\Requests\Api\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;

/** A service of an online order performed (Doc 19 Phase G3a): the day, and a note to the customer if wanted. */
class PerformEcommerceServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'performed_on' => ['required', 'date_format:Y-m-d'],
            'note'         => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['performed_on' => 'day'];
    }
}
