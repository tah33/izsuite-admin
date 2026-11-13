<?php

namespace App\Http\Requests\Admin;

use App\Models\User\SupportSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Admin > Support Sessions' filters (Doc 15 section 15.22): live or ended, the access, and a business or an admin by name. */
class ListSupportSessionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'state'  => ['nullable', Rule::in(['live', 'ended'])],
            'access' => ['nullable', Rule::in([SupportSession::READ_ONLY, SupportSession::EDIT])],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
