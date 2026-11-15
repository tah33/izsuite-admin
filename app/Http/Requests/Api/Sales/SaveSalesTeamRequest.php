<?php

namespace App\Http\Requests\Api\Sales;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A sales team (Doc 16 Phase A, Settings → Sales and - Doc 17 Phase A2 -
 * Settings → CRM): its name, whether it is in use, its members by user ID
 * and its manager. Left out, the manager stays. SalesTeamService checks the
 * name is the business's only one, and the members and manager are its
 * active people.
 */
class SaveSalesTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:100'],
            'is_active'    => ['sometimes', 'boolean'],
            'member_ids'   => ['present', 'array', 'max:200'],
            'member_ids.*' => ['integer'],
            'manager_id'   => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
