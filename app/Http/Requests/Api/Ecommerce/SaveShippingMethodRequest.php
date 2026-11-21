<?php

namespace App\Http\Requests\Api\Ecommerce;

use App\Models\User\ShippingMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A zone's shipping method (Doc 19 Phase D1): its zone, name and kind; a
 * flat rate's or local delivery's price, or the bands of one by weight,
 * order value or quantity; its tax, delivery days, order values, weights and
 * categories; whether it is on, and its place. ShippingMethodRules checks
 * the rest.
 */
class SaveShippingMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $banded = implode(',', ShippingMethod::BANDED);
        $priced = implode(',', ShippingMethod::PRICED);

        return [
            'shipping_zone_id' => ['required', 'integer'],
            'name'             => ['required', 'string', 'max:100'],
            'kind'             => ['required', Rule::in(ShippingMethod::KINDS)],
            'price'            => ["required_if:kind,{$priced}", 'nullable', 'numeric', 'min:0', 'max:9999999999'],
            'rates'            => ["required_if:kind,{$banded}", 'nullable', 'array', 'max:'.ShippingMethod::MAX_RATES],
            'rates.*.from'     => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'rates.*.price'    => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'tax_rate_id'      => ['sometimes', 'nullable', 'integer'],
            'days_min'         => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'days_max'         => ['sometimes', 'nullable', 'integer', 'min:0', 'max:365'],
            'min_order'        => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999'],
            'max_order'        => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999'],
            'min_weight'       => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999'],
            'max_weight'       => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999'],
            'category_ids'     => ['sometimes', 'nullable', 'array', 'max:500'],
            'category_ids.*'   => ['integer'],
            'is_active'        => ['sometimes', 'boolean'],
            'position'         => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function messages(): array
    {
        return [
            'shipping_zone_id.required' => 'Choose its zone.',
            'name.required'             => 'Give the method a name.',
            'price.required_if'         => 'Give its price.',
            'rates.required_if'         => 'Give at least one rate.',
            'rates.max'                 => 'Give up to '.ShippingMethod::MAX_RATES.' rates.',
            'rates.*.from.required'     => 'Say where each rate starts.',
            'rates.*.price.required'    => 'Give each rate its price.',
            'days_max.max'              => 'Give up to 365 days.',
        ];
    }
}
