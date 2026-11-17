<?php

namespace App\Http\Requests\Api\Crm;

use Illuminate\Foundation\Http\FormRequest;

class CrmOpportunityBoardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The Opportunities page's board (Doc 17 Phase D4): one pipeline's - the
     * first switched on, left out - searched and by owner, as the list is.
     */
    public function rules(): array
    {
        return [
            'pipeline_id' => ['nullable', 'integer'],
            'search'      => ['nullable', 'string', 'max:100'],
            'owner_id'    => ['nullable', 'regex:/^(me|[0-9]+)$/'],
        ];
    }
}
