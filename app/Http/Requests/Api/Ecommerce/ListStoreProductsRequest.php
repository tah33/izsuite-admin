<?php

namespace App\Http\Requests\Api\Ecommerce;

use App\Repositories\User\StorefrontCatalogRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A storefront's products as a customer asks for them (Doc 19 Phase C1):
 * searched, narrowed to a category (by its link) or a brand and to those in
 * stock, sorted, a page at a time - from Phase C2 within a collection, and
 * from C3 by price, options and attributes. Its filters' offer (C3) takes the
 * same.
 */
class ListStoreProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'   => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:200'],
            // Phase C2: a collection's page, by its link.
            'collection' => ['nullable', 'string', 'max:200'],
            'brand'    => ['nullable', 'integer'],
            'in_stock' => ['nullable', 'boolean'],
            // Phase C3: a price range as the store shows prices, and an option's or attribute's value by its name.
            'price_min'   => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'price_max'   => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'option'      => ['nullable', 'array', 'max:3'],
            'option.*'    => ['nullable', 'string', 'max:60'],
            'attribute'   => ['nullable', 'array', 'max:20'],
            'attribute.*' => ['nullable', 'string', 'max:120'],
            'sort'     => ['nullable', Rule::in(StorefrontCatalogRepository::SORTS)],
            'page'     => ['nullable', 'integer', 'min:1'],
        ];
    }
}
