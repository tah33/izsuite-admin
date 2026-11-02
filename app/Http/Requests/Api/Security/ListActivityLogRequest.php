<?php

namespace App\Http\Requests\Api\Security;

use Illuminate\Foundation\Http\FormRequest;

class ListActivityLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The Activity Log's filters (Settings > Security & Audit): words in what
     * was done, who did it, a date range, and only the entries that recorded
     * changes. Any may be left out.
     */
    public function rules(): array
    {
        return [
            'search'       => ['nullable', 'string', 'max:100'],
            'user_id'      => ['nullable', 'integer'],
            'changes_only' => ['sometimes', 'boolean'],
            'date_from'    => ['nullable', 'date_format:Y-m-d'],
            'date_to'      => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.date_format'  => 'Choose a date to start from.',
            'date_to.date_format'    => 'Choose a date to end on.',
            'date_to.after_or_equal' => 'The end date comes on or after the start date.',
        ];
    }
}
