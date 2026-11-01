<?php

namespace App\Http\Requests\Api\Expenses;

use Illuminate\Foundation\Http\FormRequest;

class VoidExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Voiding a recorded expense (Doc 10 section 10), with why if given. */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
