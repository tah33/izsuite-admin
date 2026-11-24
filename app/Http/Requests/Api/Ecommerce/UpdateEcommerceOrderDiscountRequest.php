<?php

namespace App\Http\Requests\Api\Ecommerce;

use App\Models\User\EcommerceOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An online order's discount, before payment (Doc 19 Phase F5b): a
 * percentage of its products, an amount, or none - with why, for its
 * timeline and never its customer. An amount no more than the products come
 * to is the service's to check.
 */
class UpdateEcommerceOrderDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'   => ['nullable', Rule::in([EcommerceOrder::PERCENT, EcommerceOrder::AMOUNT])],
            'value'  => ['required_with:type', 'nullable', 'numeric', 'decimal:0,2', 'gt:0', $this->input('type') === EcommerceOrder::PERCENT ? 'max:100' : 'max:9999999999'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'value.required_with' => 'Give the discount: a percentage, or an amount.',
            'value.gt'            => 'Give a discount above nothing, or choose No Discount.',
            'value.max'           => $this->input('type') === EcommerceOrder::PERCENT ? 'A percentage is 100 at most.' : 'Give a smaller amount.',
            'reason.required'     => 'Say why: it stays in the order\'s timeline, not in anything the customer sees.',
        ];
    }
}
