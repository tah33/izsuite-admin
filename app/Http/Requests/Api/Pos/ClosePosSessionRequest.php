<?php

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Closing a POS session (Doc 18 Phase B1): the cash counted in the drawer,
 * why it is not the cash expected - PosSessionService asks for it then - and
 * the closing notes.
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
            'counted_cash'    => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'variance_reason' => ['nullable', 'string', 'max:500'],
            'notes'           => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'counted_cash.required' => 'Enter the cash counted in the drawer.',
        ];
    }
}
