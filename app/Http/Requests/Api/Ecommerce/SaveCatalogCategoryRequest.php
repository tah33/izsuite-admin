<?php

namespace App\Http\Requests\Api\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;

/** An item category's online side (Doc 19 Phase B2): its link, words, SEO, whether the menu lists it, and where. */
class SaveCatalogCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slug'            => ['nullable', 'string', 'max:190', 'regex:/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$/'],
            'description'     => ['nullable', 'string', 'max:20000'],
            'seo_title'       => ['nullable', 'string', 'max:200'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'in_menu'         => ['required', 'boolean'],
            'position'        => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'Use letters, digits and single dashes - its link, such as kitchen.',
        ];
    }
}
