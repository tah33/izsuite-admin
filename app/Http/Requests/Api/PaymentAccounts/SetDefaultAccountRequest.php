<?php

namespace App\Http\Requests\Api\PaymentAccounts;

use App\Models\User\PaymentAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetDefaultAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Which default it becomes (Doc 05 section 8): for receiving customer money, or for paying suppliers and expenses. */
    public function rules(): array
    {
        return [
            'for' => ['required', Rule::in(array_keys(PaymentAccount::DEFAULTS))],
        ];
    }

    public function messages(): array
    {
        return [
            'for.in' => 'Choose receiving or payment.',
        ];
    }
}
