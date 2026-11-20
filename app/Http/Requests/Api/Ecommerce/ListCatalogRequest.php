<?php

namespace App\Http\Requests\Api\Ecommerce;

use App\Models\User\CatalogProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The Catalog page (Doc 19 Phase B1): a search, an online status - "offline" for items not in it - and a category. */
class ListCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'      => ['nullable', 'string', 'max:100'],
            'status'      => ['nullable', Rule::in(['offline', ...CatalogProduct::STATUSES])],
            'category_id' => ['nullable', 'integer'],
            'page'        => ['nullable', 'integer', 'min:1'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
