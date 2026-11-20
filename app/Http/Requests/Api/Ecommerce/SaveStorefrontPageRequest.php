<?php

namespace App\Http\Requests\Api\Ecommerce;

use App\Models\User\StorefrontPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A storefront's page (Doc 19 Phase C5a): its title, its words in rich text,
 * published or a draft, whether the footer links to it, and its SEO. Whether
 * a page may be published without words is StorefrontPageService's to say.
 * From Phase C5b, a page of the business's own takes its link and its place
 * in the footer too; a standard page keeps its own.
 */
class SaveStorefrontPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'           => ['required', 'string', 'max:120'],
            'body'            => ['nullable', 'string', 'max:60000'],
            'status'          => ['required', Rule::in(StorefrontPage::STATUSES)],
            'in_footer'       => ['required', 'boolean'],
            'seo_title'       => ['nullable', 'string', 'max:200'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'slug'            => ['nullable', 'string', 'max:200', 'regex:/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$/'],
            'position'        => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Give the page a title.',
            'title.max'      => 'Keep the title to 120 characters.',
            'body.max'       => 'Keep the page to 60,000 characters.',
            'slug.regex'     => 'Use letters, digits and dashes, starting and ending with a letter or digit.',
        ];
    }
}
