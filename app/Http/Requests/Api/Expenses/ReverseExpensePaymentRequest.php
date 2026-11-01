<?php

namespace App\Http\Requests\Api\Expenses;

use Illuminate\Foundation\Http\FormRequest;

class ReverseExpensePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Reversing an expense payment (Doc 10 section 12), with why if given. */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
