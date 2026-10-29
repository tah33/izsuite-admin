<?php

namespace App\Http\Requests\Api\Contacts;

use App\Enums\TaxTreatment;
use App\Models\Admin\Language;
use App\Models\User\Contact;
use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Codes are upper case by definition, and a tag list arrives tidy: each tag
     * trimmed, empty ones dropped, and a tag given twice - in any case - kept
     * once.
     */
    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['billing_country', 'shipping_country', 'tax_country', 'sales_currency', 'purchase_currency'] as $field) {
            if (is_string($this->input($field))) {
                $merge[$field] = strtoupper(trim($this->input($field)));
            }
        }

        if (is_array($this->input('tags'))) {
            $tags = [];

            foreach ($this->input('tags') as $tag) {
                $tag = is_string($tag) ? trim($tag) : $tag;

                if ($tag === '' || $tag === null) {
                    continue;
                }

                $tags[is_string($tag) ? mb_strtolower($tag) : serialize($tag)] ??= $tag;
            }

            $merge['tags'] = array_values($tags);
        }

        $this->merge($merge);
    }

    /**
     * The same for adding and for editing (Doc 03). Only the type, the role
     * and the name are required.
     *
     * An optional field left out of an update is left as it was. That is how
     * a form keeps what it hides: the tax fields while taxes are off, and the
     * customer or supplier defaults of a role the contact does not have.
     *
     * A default currency is the base currency or one switched on under
     * Settings > Currencies; none means the base currency. Payment terms are a
     * number of days, 0 being "due on receipt".
     *
     * allow_duplicates saves a contact that looks like one the business
     * already has - after the warning about it has been shown.
     */
    public function rules(): array
    {
        $workspace  = $this->attributes->get('workspace');
        $currencies = $workspace?->currencyCodes() ?? [];
        $countries  = Rule::in(Countries::codes());
        $phone      = ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 ().\-]*$/'];
        $terms      = ['nullable', 'integer', 'min:0', 'max:'.Contact::MAX_PAYMENT_TERMS];

        $address = fn (string $prefix): array => [
            "{$prefix}_line1"       => ['nullable', 'string', 'max:255'],
            "{$prefix}_line2"       => ['nullable', 'string', 'max:255'],
            "{$prefix}_city"        => ['nullable', 'string', 'max:100'],
            "{$prefix}_state"       => ['nullable', 'string', 'max:100'],
            "{$prefix}_postal_code" => ['nullable', 'string', 'max:20'],
            "{$prefix}_country"     => ['nullable', $countries],
        ];

        return [
            'contact_type'             => ['required', Rule::in(Contact::TYPES)],
            'role'                     => ['required', Rule::in(Contact::ROLES)],
            'name'                     => ['required', 'string', 'max:255'],
            'trading_name'             => ['nullable', 'string', 'max:255'],
            'contact_person'           => ['nullable', 'string', 'max:255'],
            'job_title'                => ['nullable', 'string', 'max:100'],
            'phone'                    => $phone,
            'alt_phone'                => $phone,
            'email'                    => ['nullable', 'email', 'max:255'],
            'website'                  => ['nullable', 'url:http,https', 'max:255'],
            'preferred_language'       => ['nullable', Rule::exists(Language::class, 'code')->where('is_active', true)],
            'tags'                     => ['nullable', 'array', 'max:20'],
            'tags.*'                   => ['string', 'max:50'],
            ...$address('billing'),
            'shipping_same_as_billing' => ['sometimes', 'boolean'],
            ...$address('shipping'),
            'sales_currency'           => ['nullable', Rule::in($currencies)],
            'customer_payment_terms'   => $terms,
            'purchase_currency'        => ['nullable', Rule::in($currencies)],
            'supplier_payment_terms'   => $terms,
            'tax_number'               => ['nullable', 'string', 'max:50'],
            'tax_country'              => ['nullable', $countries],
            'tax_treatment'            => ['nullable', Rule::enum(TaxTreatment::class)],
            'tax_exemption_number'     => ['nullable', 'string', 'max:50'],
            'notes'                    => ['nullable', 'string', 'max:5000'],
            'is_active'                => ['sometimes', 'boolean'],
            'allow_duplicates'         => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'contact_type'           => 'contact type',
            'name'                   => 'contact name',
            'alt_phone'              => 'alternative phone',
            'billing_line1'          => 'address line 1',
            'billing_line2'          => 'address line 2',
            'billing_city'           => 'city',
            'billing_state'          => 'state or region',
            'billing_postal_code'    => 'postal code',
            'billing_country'        => 'country',
            'shipping_line1'         => 'address line 1',
            'shipping_line2'         => 'address line 2',
            'shipping_city'          => 'city',
            'shipping_state'         => 'state or region',
            'shipping_postal_code'   => 'postal code',
            'shipping_country'       => 'country',
            'customer_payment_terms' => 'payment terms',
            'supplier_payment_terms' => 'payment terms',
            'tax_number'             => 'tax ID',
            'tags.*'                 => 'tag',
        ];
    }

    public function messages(): array
    {
        $currency = 'Use the base currency or one switched on under Settings > Currencies.';
        $country  = 'Choose a country from the list.';

        return [
            'contact_type.in'             => 'Choose business or individual.',
            'role.in'                     => 'Choose customer, supplier or both.',
            'phone.regex'                 => 'Use digits, spaces and + ( ) - . only.',
            'alt_phone.regex'             => 'Use digits, spaces and + ( ) - . only.',
            'website.url'                 => 'Enter a web address starting with http:// or https://.',
            'preferred_language.exists'   => 'Choose one of the available languages.',
            'tags.max'                    => 'A contact can have up to 20 tags.',
            'sales_currency.in'           => $currency,
            'purchase_currency.in'        => $currency,
            'billing_country.in'          => $country,
            'shipping_country.in'         => $country,
            'tax_country.in'              => $country,
            'customer_payment_terms.max'  => 'Payment terms can be at most :max days.',
            'supplier_payment_terms.max'  => 'Payment terms can be at most :max days.',
        ];
    }
}
