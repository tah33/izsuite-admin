<?php

namespace App\Http\Requests\Admin;

use App\Models\Frontend\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A standalone app is a family of one: default family_key to the name's slug.
     */
    protected function prepareForValidation(): void
    {
        $familyKey = trim((string) $this->input('family_key'));

        $this->merge([
            'family_key' => Str::slug($familyKey !== '' ? $familyKey : (string) $this->input('name')),
            'edition'    => $this->input('edition') ?: null,
            'sort_order' => $this->input('sort_order') ?? 0,
        ]);
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
            'family_key'  => ['required', 'string', 'max:100', 'alpha_dash'],
            'edition'     => [
                'nullable',
                Rule::in(array_keys(Application::EDITIONS)),
                // One row per edition inside a family.
                Rule::unique('apps', 'edition')
                    ->where('family_key', $this->input('family_key'))
                    ->ignore($this->route('id')),
            ],
            'sort_order'  => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'edition.unique' => __('This family already has an app with that edition.'),
        ];
    }
}
