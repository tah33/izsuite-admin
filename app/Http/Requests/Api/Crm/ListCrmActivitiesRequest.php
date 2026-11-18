<?php

namespace App\Http\Requests\Api\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The Activities page's search and filters (Doc 17 Phase E1): planned (when
 * none), today's, overdue, completed, cancelled or all; whom they are given
 * to ("me" for the user); a type.
 */
class ListCrmActivitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'      => ['nullable', 'string', 'max:100'],
            'status'      => ['nullable', Rule::in(['planned', 'today', 'overdue', 'completed', 'cancelled', 'all'])],
            'assigned_to' => ['nullable', 'regex:/^(me|[0-9]+)$/'],
            'type_id'     => ['nullable', 'integer'],
        ];
    }
}
