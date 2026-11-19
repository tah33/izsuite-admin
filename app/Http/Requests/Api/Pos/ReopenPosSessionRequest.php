<?php

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;

/** Reopening a counted POS session (Doc 18 Phase B2a): why it opens again, which its Z report keeps. */
class ReopenPosSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Say why the session opens again.',
        ];
    }
}
