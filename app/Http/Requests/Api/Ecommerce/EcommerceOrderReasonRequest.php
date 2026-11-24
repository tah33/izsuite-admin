<?php

namespace App\Http\Requests\Api\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Why an online order is put on hold, released or cancelled (Doc 19 section
 * 19.24 - Phase F4): for its timeline, and never its customer.
 */
class EcommerceOrderReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return ['reason.required' => 'Say why: it stays in the order\'s timeline, not in anything the customer sees.'];
    }
}
