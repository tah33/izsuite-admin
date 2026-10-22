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

    public function rules(): array
    {
        return [
            'search'   => ['nullable', 'string', 'max:255'],
            'type'     => ['nullable', Rule::in([Contact::TYPE_CUSTOMER, Contact::TYPE_VENDOR])],
            'status'   => ['nullable', Rule::in(['active', 'inactive'])],
            'per_page' => ['nullable', 'integer', 'in:10,15,25,50,100'],
            'page'     => ['nullable', 'integer', 'min:1'],
        ];
    }
}
