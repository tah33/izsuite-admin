<?php

namespace App\Http\Requests\Api\Crm;

use Illuminate\Foundation\Http\FormRequest;

/**
 * What a lead's step reads (Doc 17 Phase B1): the new owner, to assign it;
 * the reason it is unqualified (required) or archived (optional).
 */
class CrmLeadStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'owner_id' => [$this->routeIs('api.crm.leads.assign') ? 'required' : 'prohibited', 'integer'],
            'reason'   => [$this->routeIs('api.crm.leads.unqualify') ? 'required' : 'nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'owner_id.required' => 'Choose who the lead goes to.',
            'reason.required'   => 'Say why the lead is unqualified.',
        ];
    }
}
