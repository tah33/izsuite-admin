<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'icon'       => trim((string) $this->input('icon')) ?: null,
            'sort_order' => $this->input('sort_order') ?? 0,
        ]);
    }

    /**
     * icon is a lucide icon name - layout-grid, shopping-bag - as the Apps
     * Center draws it.
     */
    public function rules(): array
    {
        return [
            'name'       => ['required', 'string', 'max:255'],
            'icon'       => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active'  => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'icon.regex' => __('Use a lucide icon name, such as shopping-bag.'),
        ];
    }
}
