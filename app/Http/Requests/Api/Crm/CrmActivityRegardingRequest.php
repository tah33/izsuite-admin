<?php

namespace App\Http\Requests\Api\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** What an activity planned on the Activities page can be for (Doc 17 Phase E1): leads, opportunities or contacts, searched. */
class CrmActivityRegardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kind'   => ['required', Rule::in(['lead', 'opportunity', 'contact'])],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }
}
