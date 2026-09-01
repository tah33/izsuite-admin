<?php

namespace App\Http\Requests\Api\Apps;

use Illuminate\Foundation\Http\FormRequest;

class ListAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'   => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'status'   => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer', 'in:10,15,25,50,100'],
            'page'     => ['nullable', 'integer', 'min:1'],
        ];
    }
}
