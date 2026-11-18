<?php

namespace App\Http\Requests\Api\Crm;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reopening a won or lost opportunity (Doc 17 Phase D2a): the open stage it
 * goes back to - none for the last one it was at.
 */
class CrmOpportunityReopenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'stage_id' => ['nullable', 'integer'],
        ];
    }
}
