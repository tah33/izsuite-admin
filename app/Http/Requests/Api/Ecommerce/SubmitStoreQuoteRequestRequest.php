<?php

namespace App\Http\Requests\Api\Ecommerce;

use App\Models\User\EcommerceQuoteRequest;
use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A request for a quotation from a storefront (Doc 19 Phase H4a): the
 * customer's name, e-mail, phone and company; the delivery location; the
 * date wanted; a message; and the products asked about, with their options
 * and how many - up to 20.
 */
class SubmitStoreQuoteRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'               => ['required', 'string', 'max:150'],
            'email'              => ['required', 'email', 'max:255'],
            'phone'              => ['nullable', 'string', 'max:40'],
            'company'            => ['nullable', 'string', 'max:150'],
            'delivery'           => ['required', 'array'],
            'delivery.country'   => ['required', 'string', Rule::in(Countries::codes())],
            'delivery.city'      => ['nullable', 'string', 'max:100'],
            'delivery.address'   => ['nullable', 'string', 'max:255'],
            'required_on'        => ['nullable', 'date_format:Y-m-d'],
            'message'            => ['nullable', 'string', 'max:2000'],
            'items'              => ['required', 'array', 'min:1', 'max:'.EcommerceQuoteRequest::MAX_ITEMS],
            'items.*.product'    => ['required', 'string', 'max:200'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.quantity'   => ['required', 'numeric', 'min:1', 'max:999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'             => 'Give your name.',
            'email.required'            => 'Give an e-mail we can reply to.',
            'email.email'               => 'Give an e-mail we can reply to.',
            'delivery.country.required' => 'Choose the country it is delivered to.',
            'delivery.country.in'       => 'Choose the country it is delivered to.',
            'required_on.date_format'   => 'Give the date as a day.',
            'message.max'               => 'Keep the message to 2000 characters.',
            'items.required'            => 'Choose a product to ask about.',
            'items.max'                 => 'Ask about up to '.EcommerceQuoteRequest::MAX_ITEMS.' products at once.',
            'items.*.quantity.min'      => 'Ask for 1 at least.',
            'items.*.quantity.required' => 'Say how many.',
        ];
    }
}
