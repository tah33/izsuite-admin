<?php

namespace App\Http\Requests\Api\BusinessSettings;

use App\Models\Admin\Currency;
use App\Models\Admin\Language;
use App\Models\User\BusinessSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBusinessSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * ISO 4217 codes are upper case by definition, and MySQL's case-insensitive
     * comparison would let "usd" past the exists rule and into the column,
     * where it would never match the "USD" the currency list offers.
     *
     * Language codes are not touched: their casing is meaningful ("pt-BR").
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('currency')) {
            $this->merge(['currency' => strtoupper((string) $this->input('currency'))]);
        }
    }

    /**
     * Every field the business_settings table marks NOT NULL is required here,
     * because the Settings > Business form writes the whole row in one go.
     *
     * Currency and language must be among the ones an admin has left active -
     * the same lists GET /currencies and GET /languages hand the form.
     *
     * The logo is deliberately raster-only, unlike the admin panel's image
     * uploads: those come from staff, these from any account, and an SVG can
     * carry script that would run on this domain when its URL is opened.
     */
    public function rules(): array
    {
        return [
            'business_name'              => ['required', 'string', 'max:255'],
            'start_date'                 => ['nullable', 'date_format:Y-m-d'],
            'currency'                   => ['required', 'string', Rule::exists(Currency::class, 'code')->where('is_active', true)],
            'language'                   => ['required', 'string', Rule::exists(Language::class, 'code')->where('is_active', true)],
            'logo'                       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'website'                    => ['nullable', 'url:http,https', 'max:255'],
            'business_contact_number'    => ['nullable', 'string', 'max:30'],
            'alternate_contact_number'   => ['nullable', 'string', 'max:30'],
            'country'                    => ['required', 'string', 'max:100'],
            'state'                      => ['required', 'string', 'max:100'],
            'city'                       => ['required', 'string', 'max:100'],
            'zip_code'                   => ['required', 'string', 'max:20'],
            'landmark'                   => ['required', 'string', 'max:255'],

            // all_with_bc, not the default group: the time zone dropdown is
            // built from the browser's own list, which still uses names like
            // Asia/Calcutta, Asia/Katmandu and Asia/Saigon that PHP only
            // accepts as backward-compatible aliases.
            'timezone'                   => ['required', 'string', 'timezone:all_with_bc'],

            'tax_1_name'                 => ['nullable', 'string', 'max:50'],
            'tax_1_no'                   => ['nullable', 'string', 'max:50'],
            'tax_2_name'                 => ['nullable', 'string', 'max:50'],
            'tax_2_no'                   => ['nullable', 'string', 'max:50'],

            'financial_year_start_month' => ['required', 'integer', 'between:1,12'],
            'stock_accounting_method'    => ['required', Rule::in(BusinessSetting::STOCK_ACCOUNTING_METHODS)],
        ];
    }

    public function attributes(): array
    {
        return [
            'timezone'   => 'time zone',
            'tax_1_no'   => 'tax 1 number',
            'tax_2_no'   => 'tax 2 number',
        ];
    }

    public function messages(): array
    {
        return [
            'currency.exists' => 'Choose one of the available currencies.',
            'language.exists' => 'Choose one of the available languages.',
            'logo.mimes'      => 'The logo must be a JPG, PNG or WebP image.',
            'logo.image'      => 'The logo must be a JPG, PNG or WebP image.',
        ];
    }
}
