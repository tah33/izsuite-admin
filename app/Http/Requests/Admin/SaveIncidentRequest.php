<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin\Incident;
use App\Support\ServiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An incident's details (Doc 15 Phase D3b): its title, the service it is
 * about (or none), severity, summary and when it started - and, when it is
 * opened, the admin responsible.
 */
class SaveIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'          => ['required', 'string', 'max:150'],
            'service'        => ['nullable', Rule::in(array_keys(ServiceStatus::NAMES))],
            'severity'       => ['required', Rule::in(array_keys(Incident::SEVERITIES))],
            'summary'        => ['nullable', 'string', 'max:5000'],
            'started_at'     => ['required', 'date', 'before_or_equal:now'],
            // Who is responsible changes by a note once the incident is open.
            'responsible_id' => [$this->route('id') ? 'prohibited' : 'nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'started_at.before_or_equal' => 'An incident cannot start in the future.',
        ];
    }
}
