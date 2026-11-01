<?php

namespace App\Http\Requests\Api\Expenses;

use Illuminate\Foundation\Http\FormRequest;

class RecordExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Recording a draft (Doc 10 section 10). A receipt that may have been
     * recorded before (section 11) is recorded again only once `confirmed`;
     * an exact duplicate needs the `reason` it is recorded anyway, which it
     * keeps.
     */
    public function rules(): array
    {
        return [
            'confirmed' => ['nullable', 'boolean'],
            'reason'    => ['nullable', 'string', 'max:500'],
        ];
    }
}
