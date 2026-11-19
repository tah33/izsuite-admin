<?php

namespace App\Http\Requests\Api\Pos;

use App\Models\User\PosRegister;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A POS register (Doc 18 Phase A2): its name and code, location, currency,
 * price list, cash account, receipt number prefix, the payment methods it
 * takes - one at least - its cashiers and its status. What each id names is
 * checked by PosRegisterService.
 */
class SavePosRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                 => ['required', 'string', 'max:100'],
            'code'                 => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/'],
            'location_id'          => ['required', 'integer'],
            'currency'             => ['required', 'string', 'size:3'],
            'price_list_id'        => ['nullable', 'integer'],
            'cash_account_id'      => ['nullable', 'integer'],
            'receipt_prefix'       => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9\/-]*$/'],
            'payment_method_ids'   => ['required', 'array', 'min:1', 'max:30'],
            'payment_method_ids.*' => ['integer'],
            'cashier_ids'          => ['nullable', 'array', 'max:200'],
            'cashier_ids.*'        => ['integer'],
            'status'               => ['sometimes', Rule::in(PosRegister::STATUSES)],
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex'                  => 'Use letters, digits and dashes.',
            'receipt_prefix.regex'        => 'Use letters, digits, dashes and slashes.',
            'payment_method_ids.required' => 'Choose the payment methods it takes.',
            'payment_method_ids.min'      => 'Choose the payment methods it takes.',
        ];
    }
}
