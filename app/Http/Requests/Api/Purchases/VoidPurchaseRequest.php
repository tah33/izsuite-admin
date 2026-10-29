<?php

namespace App\Http\Requests\Api\Purchases;

use Illuminate\Foundation\Http\FormRequest;

class VoidPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Voiding a recorded purchase (Doc 09 section 10), with why if given. */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
