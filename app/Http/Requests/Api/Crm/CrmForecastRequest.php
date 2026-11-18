<?php

namespace App\Http\Requests\Api\Crm;

use App\Services\Api\Crm\CrmForecastService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrmForecastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The Forecast's grouping and filters (Doc 17 section 17.14 - Phase G2):
     * by month to start, or by owner, sales team or stage; and the
     * Overview's filters, but the period - each left out for all.
     */
    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');

        return [
            'group_by'      => ['nullable', Rule::in(CrmForecastService::GROUPS)],
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
