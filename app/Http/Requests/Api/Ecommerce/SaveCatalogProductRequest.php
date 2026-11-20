<?php

namespace App\Http\Requests\Api\Ecommerce;

use App\Models\User\CatalogProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An item's online listing (Doc 19 Phase B1): its title and link,
 * descriptions and keywords, status, visibility and dates, sales method,
 * availability and weight, video links, SEO title and description, and the
 * storefronts it is on. How they fit together is CatalogService's to check.
 */
class SaveCatalogProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $amount = ['nullable', 'numeric', 'min:0', 'max:99999999'];

        return [
            'title'             => ['required', 'string', 'max:200'],
            'slug'              => ['nullable', 'string', 'max:190', 'regex:/^[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*$/'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'description'       => ['nullable', 'string', 'max:20000'],
            'keywords'          => ['nullable', 'string', 'max:500'],
            'status'            => ['required', Rule::in(CatalogProduct::STATUSES)],
            'visibility'        => ['required', Rule::in(CatalogProduct::VISIBILITIES)],
            'available_from'    => ['nullable', 'date_format:Y-m-d'],
            'available_until'   => ['nullable', 'date_format:Y-m-d'],
            'sales_method'      => ['required', Rule::in(CatalogProduct::SALES_METHODS)],
            'availability'      => ['required', Rule::in(CatalogProduct::AVAILABILITIES)],
            'stock_quantity'    => $amount,
            'weight'            => $amount,
            'weight_unit'       => ['nullable', Rule::in(CatalogProduct::WEIGHT_UNITS)],
            'video_urls'        => ['nullable', 'array', 'max:'.CatalogProduct::MAX_VIDEOS],
            'video_urls.*'      => ['string', 'max:2048', 'url:https'],
            // Phase B2: Material: Cotton - for the storefront's filters.
            'custom_attributes'         => ['nullable', 'array', 'max:'.CatalogProduct::MAX_ATTRIBUTES],
            'custom_attributes.*.name'  => ['required', 'string', 'max:60'],
            'custom_attributes.*.value' => ['required', 'string', 'max:120'],
            'seo_title'         => ['nullable', 'string', 'max:200'],
            'seo_description'   => ['nullable', 'string', 'max:320'],
            'all_storefronts'   => ['required', 'boolean'],
            'storefront_ids'    => ['nullable', 'array', 'max:100'],
            'storefront_ids.*'  => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'   => 'Give it its online title.',
            'slug.regex'       => 'Use letters, digits and single dashes - its link, such as ceramic-mug.',
            'video_urls.max'   => 'Keep up to '.CatalogProduct::MAX_VIDEOS.' video links.',
            'video_urls.*.url' => 'Give the video\'s https:// link.',
            'custom_attributes.max'              => 'Keep up to '.CatalogProduct::MAX_ATTRIBUTES.' attributes.',
            'custom_attributes.*.name.required'  => 'Give the attribute its name.',
            'custom_attributes.*.value.required' => 'Give the attribute its value.',
        ];
    }
}
