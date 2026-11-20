<?php

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Settings → Point of Sale, closing sessions (Doc 18 Phases B2a and B2b): the
 * variance limit - or none - whether cash is counted by notes and coins, and
 * the notes and coins of each currency given; and (Phase F1) the cash
 * movement limit - or none. PosSettingService checks each
 * is one of the business's currencies.
 */
class SavePosCashCountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'variance_limit'           => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            // Phase F1: above it, a cash movement waits for a manager's approval.
            'cash_movement_limit'      => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'counts_denominations'     => ['required', 'boolean'],
            'denominations'            => ['nullable', 'array', 'max:20'],
            'denominations.*.currency' => ['required', 'string', 'size:3'],
            'denominations.*.values'   => ['present', 'array', 'max:30'],
            'denominations.*.values.*' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'denominations.*.values.*' => 'Give each note and coin as an amount above nothing, to the cent: 50, 0.25.',
            'denominations.*.values.max' => 'Give 30 notes and coins at most.',
        ];
    }
}
