<?php

namespace App\Http\Requests\Admin;

use App\Models\User\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Admin > Tickets' filters: status, words (subject, requester or business), and one business's cases (Doc 15 section 15.22). */
class ListTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'    => ['nullable', Rule::in(array_keys(Ticket::LABELS))],
            'search'    => ['nullable', 'string', 'max:255'],
            'workspace' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
