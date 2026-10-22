<?php

namespace App\Http\Requests\Api\Items;

use App\Models\User\Item;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'   => ['nullable', 'string', 'max:255'],
            'type'     => ['nullable', Rule::in(Item::TYPES)],
            'status'   => ['nullable', Rule::in(['active', 'inactive'])],
            'per_page' => ['nullable', 'integer', 'in:10,15,25,50,100'],
            'page'     => ['nullable', 'integer', 'min:1'],
        ];
    }
}
