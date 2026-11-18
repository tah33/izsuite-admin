<?php

namespace App\Http\Requests\Api\Crm;

use App\Models\User\Location;
use App\Models\User\SalesTeam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A pipeline added in Settings > CRM (Doc 17 Phase D3b): its name - unique in
 * the business - and, if not for everyone, the sales teams and locations it
 * is for. It starts with the starter stages, changed after.
 */
class CreateCrmPipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');

        return [
            'name'           => ['required', 'string', 'max:100', Rule::unique('crm_pipelines', 'name')->where('workspace_id', $workspace?->id)],
            'team_ids'       => ['nullable', 'array', 'max:100'],
            'team_ids.*'     => ['integer', 'distinct', Rule::exists(SalesTeam::class, 'id')->where('workspace_id', $workspace?->id)],
            'location_ids'   => ['nullable', 'array', 'max:200'],
            'location_ids.*' => ['integer', 'distinct', Rule::exists(Location::class, 'id')->where('workspace_id', $workspace?->id)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'The business has a pipeline of this name already.',
        ];
    }
}
