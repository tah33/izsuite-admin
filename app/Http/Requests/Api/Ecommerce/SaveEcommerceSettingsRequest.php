<?php

namespace App\Http\Requests\Api\Ecommerce;

use App\Models\User\EcommerceSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Settings → eCommerce (Doc 19 Phase A1): guest checkout, whether a
 * customer's e-mail is verified first, prices shown with tax or without it,
 * and the prefix of the online orders' numbers - and, from Phase C6b, the
 * storefronts' cookie notice, kept as it is when not sent. Phase G3b adds the
 * downloads' days and count, each empty for no limit, and their account.
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
            // Phase D3: how many days a cart is kept without activity.
            'cart_days'             => ['sometimes', 'integer', 'min:1', 'max:365'],
            // Phase H3a: how many hours a cart whose customer is known goes unused before it is Abandoned.
            'abandoned_hours'       => ['sometimes', 'integer', 'min:1', 'max:720'],
            // Phase H4b: whether, while CRM is on, a request for a quotation also becomes a lead.
            'quote_requests_to_leads' => ['sometimes', 'boolean'],
            // Phase D4: whether checkout asks for a phone number, and for a note.
            'checkout_phone'        => ['sometimes', Rule::in(EcommerceSetting::PHONE_FIELDS)],
            'checkout_notes'        => ['sometimes', 'boolean'],
            // Phase F4: Review Orders Above, in the base currency - empty for none - and whether such orders are held as placed.
            'review_above'          => ['sometimes', 'nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'hold_for_review'       => ['sometimes', 'boolean'],
            // Phase F6a: payment links.
            'payment_link_days'     => ['sometimes', 'integer', 'min:1', 'max:90'],
            'partial_payments'      => ['sometimes', 'boolean'],
            // Phase G3b: downloads - their days and count, each empty for no limit, and whether an account is needed.
            'download_days'          => ['sometimes', 'nullable', 'integer', 'min:1', 'max:3650'],
            'download_limit'         => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000'],
            'downloads_need_account' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_prefix.required' => 'Give the prefix of its order numbers.',
            'order_prefix.regex'    => 'Use letters, digits, dashes and slashes.',
            'cookie_notice_text.max' => 'Keep the notice to 300 characters.',
            'cart_days.min'          => 'Keep carts for a day at least.',
            'cart_days.max'          => 'Keep carts for up to 365 days.',
            'abandoned_hours.min'    => 'Give an hour at least.',
            'abandoned_hours.max'    => 'Give up to 720 hours (30 days).',
            'download_days.min'      => 'Let downloads work for a day at least - or leave it empty for no limit.',
            'download_days.max'      => 'Let downloads work for up to 3650 days - or leave it empty for no limit.',
            'download_limit.min'     => 'Allow each file once at least - or leave it empty for no limit.',
            'download_limit.max'     => 'Allow each file up to 1000 times - or leave it empty for no limit.',
        ];
    }
}
