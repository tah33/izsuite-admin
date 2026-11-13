<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A support action on a user's account (Doc 15 section 15.7) - a reset link,
 * signing out everywhere, locking or unlocking - and why, which the audit log
 * keeps.
 */
class ClientAccountActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Give the reason. The audit log keeps it.',
            'reason.min'      => 'Give the reason in a few words. The audit log keeps it.',
        ];
    }
}
