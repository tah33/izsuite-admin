<?php

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Closing a POS session (Doc 18 Phase B1): the cash counted in the drawer,
 * why it is not the cash expected - PosSessionService asks for it then - and
 * the closing notes. Phase B2a: why a supervisor closes someone else's
 * session, which PosSessionService asks for then. Phase B2b: when the
 * business counts by notes and coins, how many of each instead - their total
 * is the cash counted.
 */
class ClosePosSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'counted_cash'    => ['required_without:denominations', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'denominations'         => ['nullable', 'array', 'max:40'],
            'denominations.*.value' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'denominations.*.count' => ['required', 'integer', 'min:0', 'max:1000000'],
            'variance_reason' => ['nullable', 'string', 'max:500'],
            'notes'           => ['nullable', 'string', 'max:2000'],
            'closer_reason'   => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'counted_cash.required_without' => 'Enter the cash counted in the drawer.',
            'denominations.*.count'         => 'Count each note and coin as a whole number.',
        ];
    }
}
