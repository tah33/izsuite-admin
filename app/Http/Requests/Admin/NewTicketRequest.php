<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** The new-case form, opened from a business's page for that business (Doc 15 section 15.22). */
class NewTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'workspace' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
