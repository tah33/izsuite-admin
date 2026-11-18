<?php

namespace App\Http\Requests\Api\Crm;

use App\Models\User\CrmOpportunity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The Opportunities page's search and filters (Doc 17 Phase D1): a status -
 * open when none, all, or the archived ones (Phase D2a); the open ones that
 * are stale (Phase D3a), have no next activity, or close soon without recent
 * activity (Phase G3b) - a stage, and an owner ("me" for the user).
 */
class ListCrmOpportunitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'   => ['nullable', 'string', 'max:100'],
            'status'   => ['nullable', Rule::in(['all', 'archived', ...CrmOpportunity::FOLLOW_UP_STATUSES, ...CrmOpportunity::STATUSES])],
            'stage_id' => ['nullable', 'integer'],
            // Phase D3b: one pipeline's.
            'pipeline_id' => ['nullable', 'integer'],
            'owner_id' => ['nullable', 'regex:/^(me|[0-9]+)$/'],
        ];
    }
}
