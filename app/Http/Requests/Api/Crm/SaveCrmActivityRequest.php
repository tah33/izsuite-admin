<?php

namespace App\Http\Requests\Api\Crm;

use App\Models\User\CrmActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCrmActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A CRM activity as its form saves it (Doc 17 Phase E1): its type and
     * subject, what it is for - exactly one of a lead, an opportunity or a
     * contact, when it is planned, then never changed - whom it is given to
     * (the user, left out), its due day and time in the business's time
     * zone, and its optional duration, priority, place, call direction and
     * description.
     */
    public function rules(): array
    {
        $adding = $this->isMethod('POST');

        return [
            'lead_id'          => $adding ? ['nullable', 'integer'] : ['prohibited'],
            'opportunity_id'   => $adding ? ['nullable', 'integer'] : ['prohibited'],
            'contact_id'       => $adding ? ['nullable', 'integer'] : ['prohibited'],
            'type_id'          => ['required', 'integer'],
            'subject'          => ['required', 'string', 'max:255'],
            'assigned_to'      => ['nullable', 'integer'],
            'due_on'           => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before:2100-01-01'],
            'due_time'         => ['required', 'date_format:H:i'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'priority'         => ['nullable', Rule::in(CrmActivity::PRIORITIES)],
            'place'            => ['nullable', 'string', 'max:255'],
            'call_direction'   => ['nullable', Rule::in(CrmActivity::DIRECTIONS)],
            'description'      => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** Planned for exactly one record. */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->isMethod('POST') && count(array_filter(array_values(CrmActivity::FOR), fn (string $column) => filled($this->input($column)))) !== 1) {
                $validator->errors()->add('regarding', 'Choose what the activity is for: a lead, an opportunity or a contact.');
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'type_id'          => 'type',
            'due_on'           => 'due date',
            'due_time'         => 'due time',
            'duration_minutes' => 'duration',
            'assigned_to'      => 'assignee',
        ];
    }
}
