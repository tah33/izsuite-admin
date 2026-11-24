<?php

namespace App\Http\Requests\Api\Ecommerce;

use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An online order's addresses and delivery, changed before payment (Doc 19
 * Phase F5a) - as checkout takes them: the billing address; the shipping
 * one when it is shipped somewhere else; shipping or pickup, with the
 * method or point. With `quote` the call only asks what this would offer
 * (EcommerceOrderDeliveryEdit::quote()), and the addresses may be partial.
 */
class UpdateEcommerceOrderDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $saving      = fn () => ! $this->routeIs('api.ecommerce.orders.delivery.quote');
        $ownShipping = fn () => $saving() && $this->input('delivery') === 'shipping' && ! $this->boolean('shipping_same', true);

        return [
            ...$this->address('billing', $saving),
            'shipping_same'      => ['sometimes', 'boolean'],
            ...$this->address('shipping', $ownShipping),
            'delivery'           => ['nullable', Rule::in(['shipping', 'pickup'])],
            'shipping_method_id' => ['nullable', 'integer'],
            'pickup_point_id'    => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'billing.line1.required'   => 'Give the billing address.',
            'billing.city.required'    => 'Give its city.',
            'billing.country.required' => 'Choose its country.',
            'shipping.line1.required'  => 'Give the address to deliver to.',
            'shipping.city.required'   => 'Give its city.',
            'shipping.country.required' => 'Choose its country.',
            'billing.country.in'       => 'Choose among the listed countries.',
            'shipping.country.in'      => 'Choose among the listed countries.',
        ];
    }

    private function address(string $side, \Closure $needed): array
    {
        return [
            $side                 => [Rule::requiredIf($needed), 'nullable', 'array'],
            "{$side}.line1"       => [Rule::requiredIf($needed), 'nullable', 'string', 'max:200'],
            "{$side}.line2"       => ['nullable', 'string', 'max:200'],
            "{$side}.city"        => [Rule::requiredIf($needed), 'nullable', 'string', 'max:100'],
            "{$side}.region"      => ['nullable', 'string', 'max:100'],
            "{$side}.postal_code" => ['nullable', 'string', 'max:20'],
            "{$side}.country"     => [Rule::requiredIf($needed), 'nullable', 'string', Rule::in(Countries::codes())],
        ];
    }
}
