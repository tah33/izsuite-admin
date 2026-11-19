<?php

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Opening a POS session (Doc 18 Phase B1): the register, the opening cash
 * float in its currency (nothing, at least) and the opening notes.
 * PosSessionService checks the register.
 */
class OpenPosSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pos_register_id' => ['required', 'integer'],
            'opening_float'   => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'opening_notes'   => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'pos_register_id.required' => 'Choose the register.',
            'opening_float.required'   => 'Enter the cash in the drawer: 0 for none.',
        ];
    }
}
