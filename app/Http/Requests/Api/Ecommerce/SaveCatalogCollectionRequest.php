<?php

namespace App\Http\Requests\Api\Ecommerce;

use App\Models\User\CatalogCollection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A collection (Doc 19 Phase B2): its name and link, kind - a brand's names
 * its brand, any other its products in their order - words, status, days,
 * SEO and place. Whose brand and products they are is
 * CatalogCollectionService's to check.
 */
class SaveCatalogCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:100'],
            'slug'            => ['nullable', 'string', 'max:190', 'regex:/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$/'],
            'kind'            => ['required', Rule::in(CatalogCollection::KINDS)],
            'item_brand_id'   => ['nullable', 'integer'],
            'product_ids'     => ['nullable', 'array', 'max:'.CatalogCollection::MAX_PRODUCTS],
            'product_ids.*'   => ['integer'],
            'description'     => ['nullable', 'string', 'max:20000'],
            'status'          => ['required', Rule::in(CatalogCollection::STATUSES)],
            'available_from'  => ['nullable', 'date_format:Y-m-d'],
            'available_until' => ['nullable', 'date_format:Y-m-d'],
            'seo_title'       => ['nullable', 'string', 'max:200'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'position'        => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Give the collection its name.',
            'slug.regex'    => 'Use letters, digits and single dashes - its link, such as summer-sale.',
        ];
    }
}
