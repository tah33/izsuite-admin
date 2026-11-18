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
     * opportunities only (Phase A1) - what a lead needs filled in before it
     * is qualified (Phase B2a), and whether confirming a sales order made
     * from an opportunity wins it (Phase F2). Each is saved on its own; one
     * left out stays as it was.
     */
    public function rules(): array
    {
        return [
            'mode'                     => ['sometimes', 'required', Rule::in(array_keys(CrmSetting::MODES))],
            'qualification_required'   => ['sometimes', 'present', 'array'],
            'qualification_required.*' => ['string', 'distinct', Rule::in(array_keys(CrmLead::QUALIFYING))],
            'win_on_order'             => ['sometimes', 'required', 'boolean'],
            // Phase G1: the closing-soon period, in days.
            'closing_soon_days'        => ['sometimes', 'required', 'integer', 'min:1', 'max:'.CrmSetting::MAX_CLOSING_SOON_DAYS],
            // Phase G3b: the follow-up warnings' periods, in days.
            'lead_response_days'       => ['sometimes', 'required', 'integer', 'min:1', 'max:'.CrmSetting::MAX_FOLLOW_UP_DAYS],
            'recent_activity_days'     => ['sometimes', 'required', 'integer', 'min:1', 'max:'.CrmSetting::MAX_FOLLOW_UP_DAYS],
        ];
    }
}
