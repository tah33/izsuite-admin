<?php

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;

class UnlockPosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Unlocking one's register (Doc 18 Phase I1): one's PIN - or, without one, one's account's password. */
    public function rules(): array
    {
        return [
            'pin'      => ['nullable', 'string', 'max:6'],
            'password' => ['nullable', 'string', 'max:255'],
        ];
    }
}
