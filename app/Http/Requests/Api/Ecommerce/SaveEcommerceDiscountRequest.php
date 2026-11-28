<?php

namespace App\Http\Requests\Api\Ecommerce;

use App\Models\User\EcommerceDiscount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A discount code added or changed (Doc 19 Phase H1): its name and code, what
 * it takes off - a percentage up to 100, an amount, or free shipping - the
 * whole order or chosen products, the order's minimum, its days, and how many
 * times it may be used in all and by one customer (empty for no limit). From
 * Phase H2b chosen categories too, and who it is for.
 */
class SaveEcommerceDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $kind = $this->input('kind');

        return [
            'name'               => ['required', 'string', 'max:100'],
            // Phase H2a: given automatically - without a code - or with one.
            'automatic'          => ['sometimes', 'boolean'],
            'code'               => ['exclude_if:automatic,true', 'required', 'string', 'max:40', 'regex:/^\s*[A-Za-z0-9-]+\s*$/'],
            'kind'               => ['required', Rule::in(EcommerceDiscount::KINDS)],
            'value'              => $kind === EcommerceDiscount::FREE_SHIPPING
                ? ['nullable']
                : ['required', 'numeric', 'decimal:0,2', 'gt:0', $kind === EcommerceDiscount::PERCENTAGE ? 'max:100' : 'max:9999999999.99'],
            'applies_to'         => ['required', Rule::in(EcommerceDiscount::APPLIES_TO)],
            'product_ids'        => ['exclude_unless:applies_to,'.EcommerceDiscount::PRODUCTS, 'required', 'array', 'min:1', 'max:200'],
            'product_ids.*'      => ['integer'],
            // Phase H2b: chosen categories; and who it is for - everyone, those signed in, or chosen contacts.
            'category_ids'       => ['exclude_unless:applies_to,'.EcommerceDiscount::CATEGORIES, 'required', 'array', 'min:1', 'max:100'],
            'category_ids.*'     => ['integer'],
            'customers'          => ['sometimes', Rule::in(EcommerceDiscount::CUSTOMERS)],
            'contact_ids'        => ['exclude_unless:customers,'.EcommerceDiscount::CONTACTS, 'required', 'array', 'min:1', 'max:500'],
            'contact_ids.*'      => ['integer'],
            'minimum'            => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'starts_on'          => ['nullable', 'date_format:Y-m-d'],
            'ends_on'            => ['nullable', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'usage_limit'        => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            // Phase H2a: taken in order of priority, 1 first; and whether it goes with other discounts.
            'priority'           => ['sometimes', 'integer', 'min:1', 'max:99'],
            'combines'           => ['sometimes', 'boolean'],
            'is_active'          => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required'        => 'Give its code: what customers type at checkout.',
            'code.regex'           => 'Use letters, digits and dashes.',
            'priority.min'         => 'Give a priority from 1 to 99: 1 is taken first.',
            'priority.max'         => 'Give a priority from 1 to 99: 1 is taken first.',
            'value.required'       => 'Say what it takes off.',
            'value.gt'             => 'Take off more than nothing.',
            'value.max'            => $this->input('kind') === EcommerceDiscount::PERCENTAGE ? 'Take off 100% at most.' : 'Take off less.',
            'product_ids.required' => 'Choose the products it is for.',
            'product_ids.min'      => 'Choose the products it is for.',
            'category_ids.required' => 'Choose the categories it is for.',
            'category_ids.min'      => 'Choose the categories it is for.',
            'contact_ids.required'  => 'Choose the customers it is for.',
            'contact_ids.min'       => 'Choose the customers it is for.',
            'ends_on.after_or_equal' => 'End it on its first day or after.',
        ];
    }

    public function attributes(): array
    {
        return ['per_customer_limit' => 'uses by one customer', 'usage_limit' => 'uses in all', 'starts_on' => 'first day', 'ends_on' => 'last day'];
    }
}
