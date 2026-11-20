<?php

namespace App\Http\Requests\Api\Ecommerce;

use App\Models\User\CatalogProduct;
use App\Models\User\CatalogProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A listing's options and variants (Doc 19 Phase B3): up to three options,
 * each a name and its values, and the items sold as its variants - each with
 * a value of every option, its status, availability and weight. No options:
 * no variants. How they fit together is CatalogVariantService's to check.
 */
class SaveCatalogVariantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $amount = ['nullable', 'numeric', 'min:0', 'max:99999999'];

        return [
            'options'                    => ['present', 'array', 'max:'.CatalogProduct::MAX_OPTIONS],
            'options.*.name'             => ['required', 'string', 'max:40'],
            'options.*.values'           => ['required', 'array', 'min:1', 'max:'.CatalogProduct::MAX_OPTION_VALUES],
            'options.*.values.*'         => ['required', 'string', 'max:60'],
            'variants'                   => ['present', 'array', 'max:'.CatalogProduct::MAX_VARIANTS],
            'variants.*.item_id'         => ['required', 'integer', 'distinct'],
            'variants.*.option_values'   => ['required', 'array', 'max:'.CatalogProduct::MAX_OPTIONS],
            'variants.*.option_values.*' => ['required', 'string', 'max:60'],
            'variants.*.status'          => ['required', Rule::in(CatalogProductVariant::STATUSES)],
            'variants.*.availability'    => ['required', Rule::in(CatalogProduct::AVAILABILITIES)],
            'variants.*.stock_quantity'  => $amount,
            'variants.*.weight'          => $amount,
        ];
    }

    public function messages(): array
    {
        return [
            'options.max'                         => 'Keep up to '.CatalogProduct::MAX_OPTIONS.' options.',
            'options.*.name.required'             => 'Give the option its name.',
            'options.*.values.required'           => 'Give the option its values.',
            'options.*.values.max'                => 'Keep up to '.CatalogProduct::MAX_OPTION_VALUES.' values.',
            'variants.max'                        => 'Keep up to '.CatalogProduct::MAX_VARIANTS.' variants.',
            'variants.*.item_id.required'         => 'Choose its item.',
            'variants.*.item_id.distinct'         => 'Each item is one variant.',
            'variants.*.option_values.required'   => 'Choose a value of each option.',
            'variants.*.option_values.*.required' => 'Choose a value of each option.',
        ];
    }
}
