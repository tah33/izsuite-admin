<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'      => ['required', 'exists:users,id'],
            'workspace_id' => ['nullable', 'integer', 'exists:workspaces,id'],
            'subject'     => ['required', 'string', 'max:255'],
            'priority'    => ['required', 'in:low,medium,high'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'message'     => ['required', 'string'],
        ];
    }
}
