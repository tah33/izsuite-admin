<?php

namespace App\Http\Requests\Admin;

use App\Support\BusinessStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A business's new status (Doc 15 section 15.6): every change has a reason,
 * shown to the owner; what reactivates it and internal notes are optional; a
 * scheduled closure has its day; and whether to tell the owner.
 */
class ChangeBusinessStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'       => ['required', Rule::in(BusinessStatus::SETTABLE)],
            'reason'       => ['required', 'string', 'min:5', 'max:1000'],
            'reactivation' => ['nullable', 'string', 'max:1000'],
            'notes'        => ['nullable', 'string', 'max:2000'],
            'closes_on'    => ['nullable', 'required_if:status,'.BusinessStatus::CLOSURE_SCHEDULED, 'date_format:Y-m-d', 'after_or_equal:today'],
            'notify'       => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required'       => 'Give the reason. The owner sees it.',
            'closes_on.required_if' => 'Choose the day the business closes.',
            'closes_on.after_or_equal' => 'Choose today or a later day.',
        ];
    }
}
