<?php

namespace App\Http\Requests\Api\Crm;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Marking an opportunity lost (Doc 17 section 17.16 - Phase D2a): the day -
 * no later than the business's today - one of Settings → CRM's lost reasons
 * (CrmOpportunityClosing checks it), the competitor, notes and a possible
 * follow-up day after it.
 */
class CrmOpportunityLoseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'closed_on'      => ['required', 'date_format:Y-m-d'],
            'lost_reason_id' => ['required', 'integer'],
            'competitor'     => ['nullable', 'string', 'max:100'],
            'notes'          => ['nullable', 'string', 'max:2000'],
            'follow_up_on'   => ['nullable', 'date_format:Y-m-d', 'after:closed_on'],
        ];
    }

    public function messages(): array
    {
        return [
            'closed_on.required'      => 'Give the day it was lost.',
            'lost_reason_id.required' => 'Choose why it was lost.',
            'follow_up_on.after'      => 'A follow-up is after the day it was lost.',
        ];
    }

    public function attributes(): array
    {
        return [
            'closed_on'    => 'lost date',
            'follow_up_on' => 'follow-up date',
        ];
    }
}
