<?php

namespace App\Http\Requests\Api\Support;

use App\Models\User\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The app's list of support cases (Doc 15 section 15.22): by status, a page at a time. */
class ListSupportCasesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'   => ['nullable', Rule::in(array_keys(Ticket::LABELS))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }
}
