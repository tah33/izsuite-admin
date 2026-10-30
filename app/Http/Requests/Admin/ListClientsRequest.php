<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** The Clients list's filters. Rows per page is read by requested_per_page(). */
class ListClientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'  => ['nullable', 'string', 'max:255'],
            'package' => ['nullable', 'string', 'max:50'],
        ];
    }
}
