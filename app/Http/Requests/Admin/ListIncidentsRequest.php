<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin\Incident;
use App\Support\ServiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Admin > Incidents' filters (Doc 15 Phase D3b): status (or not resolved), service, severity and words of the title. */
class ListIncidentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'   => ['nullable', Rule::in(['unresolved', ...array_keys(Incident::STATUSES)])],
            'service'  => ['nullable', Rule::in(array_keys(ServiceStatus::NAMES))],
            'severity' => ['nullable', Rule::in(array_keys(Incident::SEVERITIES))],
            'search'   => ['nullable', 'string', 'max:150'],
        ];
    }
}
