<?php

namespace App\Http\Requests\Api\Sales;

use Illuminate\Foundation\Http\FormRequest;

/** A sales team (Doc 16 Phase A, Settings → Sales): its name, whether it is in use, and its members by user ID. SalesTeamService checks the name is the business's only one and the members are its active ones. */
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
        ];
    }
}
