<?php

namespace App\Http\Requests\Api\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueSalesDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Issuing a draft (Doc 08 section 8). An invoice in another currency whose
     * own exchange rate differs from the business's rate for its date says
     * which to issue it at: its own ('keep') or that day's ('current').
     */
    public function rules(): array
    {
        return [
            'exchange_rate' => ['nullable', Rule::in(['keep', 'current'])],
        ];
    }
}
