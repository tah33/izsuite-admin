<?php

namespace App\Http\Requests\Api\Crm;

use App\Models\User\CrmLead;
use App\Models\User\CrmSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCrmSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Settings > CRM: how the business uses CRM - leads and opportunities, or
     * opportunities only (Phase A1) - and what a lead needs filled in before
     * it is qualified (Phase B2a). Either is saved on its own; one left out
     * stays as it was.
     */
    public function rules(): array
    {
        return [
            'mode'                     => ['sometimes', 'required', Rule::in(array_keys(CrmSetting::MODES))],
            'qualification_required'   => ['sometimes', 'present', 'array'],
            'qualification_required.*' => ['string', 'distinct', Rule::in(array_keys(CrmLead::QUALIFYING))],
        ];
    }
}
