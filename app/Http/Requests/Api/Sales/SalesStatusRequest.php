<?php

namespace App\Http\Requests\Api\Sales;

use Illuminate\Foundation\Http\FormRequest;

class SalesStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Why a quotation was rejected, or a document cancelled or voided - optional (Doc 14 section 14.5). */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
