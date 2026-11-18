<?php

namespace App\Http\Requests\Api\Crm;

/**
 * The Opportunities page's calendar (Doc 17 Phase D4): a month - the
 * business's this month, left out - with the list's filters.
 */
class CrmOpportunityCalendarRequest extends ListCrmOpportunitiesRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'month' => ['nullable', 'date_format:Y-m'],
        ];
    }
}
