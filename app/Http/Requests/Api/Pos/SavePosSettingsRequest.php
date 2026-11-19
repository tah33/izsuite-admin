<?php

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Settings → Point of Sale (Doc 18 Phase A1): the walk-in customer - checked
 * to be one of the business's active customers by PosSettingService - or
 * none, and the receipt's return policy and footer. Closing sessions'
 * settings are saved apart (SavePosCashCountRequest).
 */
class SavePosSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'walk_in_contact_id' => ['nullable', 'integer'],
            'return_policy'      => ['nullable', 'string', 'max:2000'],
            'receipt_footer'     => ['nullable', 'string', 'max:255'],
        ];
    }
}
