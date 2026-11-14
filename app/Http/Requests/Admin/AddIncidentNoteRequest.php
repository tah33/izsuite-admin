<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin\Incident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A note on an incident's timeline (Doc 15 Phase D3b): what happened, the status it moves to, and who is responsible from now (empty: nobody). */
class AddIncidentNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body'           => ['required', 'string', 'max:5000'],
            'status'         => ['required', Rule::in(array_keys(Incident::STATUSES))],
            'responsible_id' => ['present', 'nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Write what happened or what was done.',
        ];
    }
}
