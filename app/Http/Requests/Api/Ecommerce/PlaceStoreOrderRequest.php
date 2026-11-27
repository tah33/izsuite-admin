<?php

namespace App\Http\Requests\Api\Ecommerce;

use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An order placed at a storefront's checkout (Doc 19 Phase D4): the
 * customer's details, the billing address - and a shipping address of its
 * own when it differs - the delivery or pickup and the payment chosen, the
 * payment's reference, a note, the terms accepted, marketing consent apart,
 * the total reviewed, and where the visit came from. StorefrontOrderPlacement
 * checks the rest against the store.
 */
class PlaceStoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ownShipping = fn () => $this->input('delivery') === 'shipping' && ! $this->boolean('shipping_same', true);

        return [
            'customer'              => ['required', 'array'],
            'customer.name'         => ['required', 'string', 'max:150'],
            'customer.email'        => ['required', 'email', 'max:255'],
            'customer.phone'        => ['nullable', 'string', 'max:40'],
            'customer.company'      => ['nullable', 'string', 'max:150'],
            ...$this->address('billing', fn () => true),
            'shipping_same'         => ['sometimes', 'boolean'],
            ...$this->address('shipping', $ownShipping),
            'delivery'              => ['nullable', Rule::in(['shipping', 'pickup'])],
            'shipping_method_id'    => ['nullable', 'integer'],
            'pickup_point_id'       => ['nullable', 'integer'],
            'payment_method_id'     => ['nullable', 'integer'],
            'payment_reference'     => ['nullable', 'string', 'max:100'],
            'note'                  => ['nullable', 'string', 'max:1000'],
            'accept_terms'          => ['accepted'],
            'marketing'             => ['sometimes', 'boolean'],
            // Phase H1: a discount code, checked again as the order is placed.
            'discount_code'         => ['nullable', 'string', 'max:40'],
            'expected_total'        => ['required', 'numeric'],
            'campaign'              => ['sometimes', 'nullable', 'array'],
            'campaign.source'       => ['nullable', 'string', 'max:100'],
            'campaign.medium'       => ['nullable', 'string', 'max:100'],
            'campaign.campaign'     => ['nullable', 'string', 'max:100'],
            'campaign.referrer'     => ['nullable', 'string', 'max:255'],
        ];
    }

    /** An address's fields under $side, needed when $needed says. */
    private function address(string $side, \Closure $needed): array
    {
        return [
            $side                   => [Rule::requiredIf($needed), 'nullable', 'array'],
            "{$side}.line1"         => [Rule::requiredIf($needed), 'nullable', 'string', 'max:200'],
            "{$side}.line2"         => ['nullable', 'string', 'max:200'],
            "{$side}.city"          => [Rule::requiredIf($needed), 'nullable', 'string', 'max:100'],
            "{$side}.region"        => ['nullable', 'string', 'max:100'],
            "{$side}.postal_code"   => ['nullable', 'string', 'max:20'],
            "{$side}.country"       => [Rule::requiredIf($needed), 'nullable', 'string', Rule::in(Countries::codes())],
        ];
    }

    public function messages(): array
    {
        return [
            'customer.name.required'  => 'Give your name.',
            'customer.email.required' => 'Give your e-mail address: your order is sent to it.',
            'customer.email.email'    => 'Give a valid e-mail address.',
            'billing.line1.required'  => 'Give your address.',
            'billing.city.required'   => 'Give your city.',
            'billing.country.required' => 'Choose your country.',
            'shipping.line1.required' => 'Give the address to deliver to.',
            'shipping.city.required'  => 'Give its city.',
            'shipping.country.required' => 'Choose its country.',
            'billing.country.in'      => 'Choose among the listed countries.',
            'shipping.country.in'     => 'Choose among the listed countries.',
            'accept_terms.accepted'   => 'Accept the terms and the privacy policy to place your order.',
            'expected_total.required' => 'Review your order before placing it.',
        ];
    }
}
