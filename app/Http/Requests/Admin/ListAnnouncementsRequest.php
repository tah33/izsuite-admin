<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Admin > Announcements' filters (Doc 15 Phase F1): by title, and by state. */
class ListAnnouncementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'state'  => ['nullable', Rule::in(array_keys(Announcement::STATES))],
        ];
    }
}
