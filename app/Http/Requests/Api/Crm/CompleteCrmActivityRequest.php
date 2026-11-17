<?php

namespace App\Http\Requests\Api\Crm;

use Illuminate\Foundation\Http\FormRequest;

class CompleteCrmActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Completing an activity (Doc 17 Phase E1): its outcome - which its type
     * may need - and perhaps a follow-up for the same record, planned
     * straight after: its type, subject, due day and time, and whom it is
     * given to (the user, left out).
     */
    public function rules(): array
    {
        return [
            'outcome'               => ['nullable', 'string', 'max:5000'],
            'follow_up'             => ['nullable', 'array'],
            'follow_up.type_id'     => ['required_with:follow_up', 'integer'],
            'follow_up.subject'     => ['required_with:follow_up', 'string', 'max:255'],
            'follow_up.due_on'      => ['required_with:follow_up', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before:2100-01-01'],
            'follow_up.due_time'    => ['required_with:follow_up', 'date_format:H:i'],
            'follow_up.assigned_to' => ['nullable', 'integer'],
        ];
    }

    public function attributes(): array
    {
        return [
            'follow_up.type_id'     => "follow-up's type",
            'follow_up.subject'     => "follow-up's subject",
            'follow_up.due_on'      => "follow-up's due date",
            'follow_up.due_time'    => "follow-up's due time",
            'follow_up.assigned_to' => "follow-up's assignee",
        ];
    }
}
