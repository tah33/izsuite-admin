<?php

namespace App\Http\Requests\Api\Contacts;

use App\Models\User\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The search and the filters of the Contacts list (Doc 03 section 3), and
     * for_sale: only those a sale can be made to - active customers, and
     * contacts that are both.
     */
    public function rules(): array
    {
        return [
            'search'   => ['nullable', 'string', 'max:255'],
            'role'     => ['nullable', Rule::in(Contact::ROLES)],
            'type'     => ['nullable', Rule::in(Contact::TYPES)],
            'status'   => ['nullable', Rule::in(['active', 'inactive'])],
            'for_sale' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'in:10,15,25,50,100'],
            'page'     => ['nullable', 'integer', 'min:1'],
        ];
    }
}
