<?php

namespace App\Http\Requests\Api\Ecommerce;

use App\Models\Admin\Language;
use App\Models\User\Storefront;
use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A storefront (Doc 19 Phase A1): its name and code, status, languages,
 * currencies and countries, the location it sells from, and its support
 * e-mail and phone. Whose names, codes, location and currencies they are is
 * checked by StorefrontService.
 */
class SaveStorefrontRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $language = Rule::exists(Language::class, 'code')->where('is_active', true);

        return [
            'name'             => ['required', 'string', 'max:100'],
            'code'             => ['required', 'string', 'min:3', 'max:40', 'regex:/^[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?$/'],
            'status'           => ['sometimes', Rule::in(Storefront::STATUSES)],
            'default_language' => ['required', 'string', $language],
            'languages'        => ['nullable', 'array', 'max:20'],
            'languages.*'      => ['string', $language],
            'currency'         => ['required', 'string', 'size:3'],
            'currencies'       => ['nullable', 'array', 'max:30'],
            'currencies.*'     => ['string', 'size:3'],
            'countries'        => ['required', 'array', 'min:1', 'max:300'],
            'countries.*'      => ['string', Rule::in(Countries::codes())],
            'location_id'      => ['required', 'integer'],
            // Phase D3: with full Sales, the price list it sells at.
            'price_list_id'    => ['sometimes', 'nullable', 'integer'],
            'support_email'    => ['nullable', 'email', 'max:255'],
            'support_phone'    => ['nullable', 'string', 'max:40'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.min'                  => 'Use 3 characters at least.',
            'code.regex'                => 'Use letters, digits and dashes, starting and ending with a letter or digit.',
            'default_language.required' => 'Choose its language.',
            'default_language.exists'   => 'Choose one of the available languages.',
            'languages.*.exists'        => 'Choose among the available languages.',
            'countries.required'        => 'Choose the countries it sells to.',
            'countries.min'             => 'Choose the countries it sells to.',
            'countries.*.in'            => 'Choose among the listed countries.',
            'location_id.required'      => 'Choose the location it sells from.',
        ];
    }
}
