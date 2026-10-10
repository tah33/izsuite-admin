<?php

namespace App\Http\Requests\Admin;

use App\Models\Frontend\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price'       => ['nullable', 'numeric', 'min:0'],
            'image'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,svg,webp', 'max:2048'],
            'category'    => ['nullable', 'string', 'max:255'],
            'status'      => ['required', Rule::in(array_keys(Application::STATUSES))],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }
}
