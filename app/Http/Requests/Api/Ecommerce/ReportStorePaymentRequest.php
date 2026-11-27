<?php

namespace App\Http\Requests\Api\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A payment link's customer saying they paid (Doc 19 Phase F6b): the way
 * they paid and their reference. Whether the way is one the link offers, and
 * whether it asks for a reference, is the service's to check.
 */
class ReportStorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method_id' => ['required', 'integer'],
            'reference'         => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_method_id.required' => 'Choose the way you paid.',
            'reference.max'              => 'Keep the reference to 100 characters.',
        ];
    }
}
