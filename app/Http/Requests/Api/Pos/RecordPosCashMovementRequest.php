<?php

namespace App\Http\Requests\Api\Pos;

use App\Models\User\PosCashMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A cash movement of the user's own session's drawer (Doc 18 Phase F1): its
 * kind - and, for a float adjustment, which way - the account it moves to or
 * from - or, for a paid out (Phase F2), its payee and expense category - the
 * amount and why. PosCashMovementService checks the account and category.
 */
class RecordPosCashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'       => ['required', Rule::in(PosCashMovement::TYPES)],
            'direction'  => ['nullable', 'required_if:type,'.PosCashMovement::FLOAT_ADJUSTMENT, Rule::in([PosCashMovement::IN, PosCashMovement::OUT])],
            'account_id' => ['nullable', 'required_unless:type,'.PosCashMovement::PAID_OUT, 'integer'],
            // Phase F2: a paid out's payee and expense category.
            'payee'               => ['nullable', 'required_if:type,'.PosCashMovement::PAID_OUT, 'string', 'max:255'],
            'expense_category_id' => ['nullable', 'required_if:type,'.PosCashMovement::PAID_OUT, 'integer'],
            'amount'     => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'reason'     => ['required', 'string', 'max:500'],
            // Doc 18 Phase I2: a manager's approval at the register, above the limit.
            'approvals'      => ['nullable', 'array'],
            'approvals.cash' => ['nullable', 'string', 'max:80'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required'         => 'Choose what it is: cash in or out, a safe drop, a bank deposit, a float adjustment or a paid out.',
            'type.in'               => 'Choose what it is: cash in or out, a safe drop, a bank deposit, a float adjustment or a paid out.',
            'direction.required_if' => 'Say which way the float changes: into the drawer, or out of it.',
            'account_id.required_unless'   => 'Choose the account the cash moves to or from.',
            'payee.required_if'            => 'Say who it is paid to.',
            'expense_category_id.required_if' => 'Choose the expense category.',
            'amount.gt'             => 'Give an amount above nothing.',
            'reason.required'       => 'Say why the cash moves.',
        ];
    }
}
