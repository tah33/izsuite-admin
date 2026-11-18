<?php

namespace App\Http\Requests\Admin;

use App\Support\ServiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The new incident form, with the service it starts with - from Service Health's Report link (Doc 15 Phase D3b). */
class NewIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service' => ['nullable', Rule::in(array_keys(ServiceStatus::NAMES))],
        ];
    }
}
