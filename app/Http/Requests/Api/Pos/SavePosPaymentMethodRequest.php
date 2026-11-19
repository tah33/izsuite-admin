<?php

namespace App\Http\Requests\Api\Pos;

use App\Models\User\PosPaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A POS payment method (Doc 18 Phase A1): its name, type, the payment account
 * it takes money into - checked by PosSettingService - whether it needs a
 * reference and gives change, and whether it is on.
 */
class SavePosPaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'               => ['required', 'string', 'max:100'],
            'type'               => ['required', Rule::in(PosPaymentMethod::TYPES)],
            'payment_account_id' => ['required', 'integer'],
            'needs_reference'    => ['sometimes', 'boolean'],
            'gives_change'       => ['sometimes', 'boolean'],
            'is_active'          => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_account_id.required' => 'Choose the payment account it takes money into.',
        ];
    }
}
