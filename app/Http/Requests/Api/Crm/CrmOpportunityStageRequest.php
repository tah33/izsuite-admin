<?php

namespace App\Http\Requests\Api\Crm;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Moving an opportunity to another stage (Doc 17 Phase D1): which, and -
 * for someone who may change probabilities - a probability other than the
 * stage's.
 */
class CrmOpportunityStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stage_id'    => ['required', 'integer'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'stage_id.required' => 'Choose the stage it moves to.',
        ];
    }
}
