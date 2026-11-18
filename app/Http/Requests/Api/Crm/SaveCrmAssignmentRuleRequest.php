<?php

namespace App\Http\Requests\Api\Crm;

use App\Models\User\CrmAssignmentRule;
use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A lead assignment rule (Doc 17 Phase I1): its name, whether it is on, what
 * a lead must have - a lead source, location, sales team, country and
 * interested item, each left out for any - and whom it gives leads to: a
 * person, or a sales team's members by turns or by workload. The ids are
 * checked to be the business's own by CrmAssignmentRuleService.
 */
class SaveCrmAssignmentRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:100'],
            'is_active'     => ['sometimes', 'boolean'],
            'source_id'     => ['nullable', 'integer'],
            'location_id'   => ['nullable', 'integer'],
            'sales_team_id' => ['nullable', 'integer'],
            'country'       => ['nullable', Rule::in(Countries::codes())],
            'item_id'       => ['nullable', 'integer'],
            'user_id'       => ['nullable', 'required_without:team_id', 'prohibits:team_id', 'integer'],
            'team_id'       => ['nullable', 'required_without:user_id', 'integer'],
            'method'        => ['nullable', 'required_with:team_id', Rule::in(CrmAssignmentRule::METHODS)],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required_without' => 'Choose whom the rule gives leads to: a person or a sales team.',
            'team_id.required_without' => 'Choose whom the rule gives leads to: a person or a sales team.',
            'user_id.prohibits'        => 'A rule gives leads to a person or to a sales team, not both.',
            'method.required_with'     => 'Choose how the team shares the leads.',
        ];
    }
}
