<?php

namespace App\Http\Requests\Api\Crm;

use App\Models\User\CrmOption;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCrmOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * An entry of a CRM list, added or changed (Doc 17 Phase A1): its list -
     * given when it is added, its own after - a name used once in that list,
     * and whether it is offered. Left out, it is.
     */
    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');
        $id        = $this->route('id');

        // The list its name must be new to: the one given, or - when changed - its own.
        $sameList = fn (Builder $query) => $id
            ? $query->whereIn('kind', fn (Builder $own) => $own->select('kind')->from('crm_options')->where('id', $id))
            : $query->where('kind', $this->input('kind'));

        return [
            'kind'      => $id ? ['prohibited'] : ['required', Rule::in(array_keys(CrmOption::KINDS))],
            'name'      => ['required', 'string', 'max:100', Rule::unique('crm_options', 'name')->where('workspace_id', $workspace?->id)->where($sameList)->ignore($id)],
            'is_active' => ['sometimes', 'boolean'],
            // An activity type's (Doc 17 Phase E1): completing one needs an outcome.
            'needs_outcome' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique'     => 'This list has one of that name already.',
            'kind.prohibited' => 'An entry stays in its own list.',
        ];
    }
}
