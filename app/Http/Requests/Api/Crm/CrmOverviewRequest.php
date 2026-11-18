<?php

namespace App\Http\Requests\Api\Crm;

use App\Services\Reports\ReportPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrmOverviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The CRM Overview's period and filters (Doc 17 section 17.4 - Phase
     * G1): a range, or two days; a location, sales team, owner, lead
     * source, pipeline, stage and currency, each left out for all. The ids
     * narrow what the user sees; one of another business finds nothing.
     */
    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');

        return [
            'range'         => ['nullable', Rule::in(ReportPeriod::RANGES)],
            'date_from'     => ['nullable', 'required_with:date_to', 'date_format:Y-m-d'],
            'date_to'       => ['nullable', 'required_with:date_from', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'location_id'   => ['nullable', 'integer'],
            'sales_team_id' => ['nullable', 'integer'],
            'owner_id'      => ['nullable', 'integer'],
            'source_id'     => ['nullable', 'integer'],
            'pipeline_id'   => ['nullable', 'integer'],
            'stage_id'      => ['nullable', 'integer'],
            'currency'      => ['nullable', Rule::in($workspace?->currencyCodes() ?? [])],
        ];
    }
}
