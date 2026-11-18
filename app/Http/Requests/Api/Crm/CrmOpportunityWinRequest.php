<?php

namespace App\Http\Requests\Api\Crm;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Marking an opportunity won (Doc 17 section 17.16 - Phase D2a): the day -
 * no later than the business's today, which CrmOpportunityClosing checks -
 * its final value before tax, or its expected one, and a reason or none.
 */
class CrmOpportunityWinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'closed_on' => ['required', 'date_format:Y-m-d'],
            'value'     => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
            'reason'    => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'closed_on.required' => 'Give the day it was won.',
        ];
    }

    public function attributes(): array
    {
        return [
            'closed_on' => 'won date',
            'value'     => 'final value',
            'reason'    => 'won reason',
        ];
    }
}
