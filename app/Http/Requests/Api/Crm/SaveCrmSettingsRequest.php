<?php

namespace App\Http\Requests\Api\Crm;

use App\Models\User\CrmSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCrmSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Settings > CRM (Doc 17 Phase A1): how the business uses CRM - leads and opportunities, or opportunities only. */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(array_keys(CrmSetting::MODES))],
        ];
    }
}
