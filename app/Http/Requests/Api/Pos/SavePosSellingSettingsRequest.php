<?php

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;

/** Settings → Point of Sale → Selling (Doc 18 Phases C3 and D1): the discount limit, a percentage, the cash rounding step, and the hours a held order is kept - each empty for none. */
class SavePosSellingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'discount_limit' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:100'],
            'cash_rounding'  => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:1000'],
            'hold_hours'     => ['nullable', 'integer', 'min:1', 'max:720'],
            // Doc 18 Phase I1: the minutes without use after which a register locks.
            'lock_minutes'   => ['nullable', 'integer', 'min:1', 'max:240'],
        ];
    }

    public function messages(): array
    {
        return [
            'discount_limit.max' => 'A limit is a percentage, up to 100.',
            'cash_rounding.gt'   => 'Round to a step above 0 - or leave it empty for none.',
            'hold_hours.min'     => 'Keep held orders for an hour or more - or leave it empty to keep them until they are resumed or cancelled.',
            'hold_hours.max'     => 'Keep held orders for up to 720 hours (30 days).',
            // Doc 18 Phase I1.
            'lock_minutes.integer' => 'Lock a register after whole minutes.',
            'lock_minutes.min'     => 'Lock a register after a minute or more - or leave it empty for only when its cashier locks it.',
            'lock_minutes.max'     => 'Lock a register after up to 240 minutes (4 hours).',
        ];
    }
}
