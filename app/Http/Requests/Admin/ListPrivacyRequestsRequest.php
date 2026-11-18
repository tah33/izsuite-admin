<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin\PrivacyRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Admin > Privacy Requests' filters (Doc 15 Phase G1): the person asking or the PR- number, the type, and the status - "open" for those still to answer. */
class ListPrivacyRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'type'   => ['nullable', Rule::in(array_keys(PrivacyRequest::TYPES))],
            'status' => ['nullable', Rule::in(['open', ...array_keys(PrivacyRequest::STATUSES)])],
        ];
    }
}
