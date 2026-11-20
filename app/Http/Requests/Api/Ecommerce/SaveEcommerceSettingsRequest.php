<?php

namespace App\Http\Requests\Api\Ecommerce;

use App\Models\User\EcommerceSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Settings → eCommerce (Doc 19 Phase A1): guest checkout, whether a
 * customer's e-mail is verified first, prices shown with tax or without it,
 * and the prefix of the online orders' numbers - and, from Phase C6b, the
 * storefronts' cookie notice, kept as it is when not sent.
 */
class SaveEcommerceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guest_checkout'        => ['required', 'boolean'],
            'verify_customer_email' => ['required', 'boolean'],
            'price_display'         => ['required', Rule::in(EcommerceSetting::PRICE_DISPLAYS)],
            'order_prefix'          => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9\/-]+$/'],
            'cookie_notice'         => ['sometimes', 'boolean'],
            'cookie_notice_text'    => ['sometimes', 'nullable', 'string', 'max:300'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_prefix.required' => 'Give the prefix of its order numbers.',
            'order_prefix.regex'    => 'Use letters, digits, dashes and slashes.',
            'cookie_notice_text.max' => 'Keep the notice to 300 characters.',
        ];
    }
}
