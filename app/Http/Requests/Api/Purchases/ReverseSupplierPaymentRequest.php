<?php

namespace App\Http\Requests\Api\Purchases;

use Illuminate\Foundation\Http\FormRequest;

class ReverseSupplierPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Reversing a supplier payment (Doc 09 section 12), with why if given. */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
