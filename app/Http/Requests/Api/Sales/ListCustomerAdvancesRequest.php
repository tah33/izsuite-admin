<?php

namespace App\Http\Requests\Api\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Payments Received's Advances, and a customer's (Doc 16 Phase D1). */
class ListCustomerAdvancesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'     => ['nullable', 'string', 'max:100'],
            'status'     => ['nullable', Rule::in(['open', 'applied', 'reversed'])],
            'contact_id' => ['nullable', 'integer'],
            'date_from'  => ['nullable', 'date_format:Y-m-d'],
            'date_to'    => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
