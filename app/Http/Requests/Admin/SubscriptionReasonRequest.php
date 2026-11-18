<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A manual change to a business's subscription (Doc 15 section 15.13) and
 * why: every such change needs a reason, which the audit log keeps and the
 * owner is told.
 */
class SubscriptionReasonRequest extends FormRequest
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
            'reason.required' => 'Give the reason. The owner is told it, and the audit log keeps it.',
            'reason.min'      => 'Give the reason in a few words. The owner is told it, and the audit log keeps it.',
        ];
    }
}
