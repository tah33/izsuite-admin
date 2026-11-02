<?php

namespace App\Http\Requests\Api\BusinessSetup;

use App\Models\User\BusinessSetting;
use App\Models\User\TaxRate;
use App\Models\User\Workspace;
use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBusinessTaxesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The three answers business setup gives (Doc 01 section 12), and the tax
     * options (Doc 11 sections 9 and 11). The options may be left out - the
     * choice alone is a whole save. A default tax is an active rate of this
     * business that applies to its side; null is none.
     */
    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');
        $rateFor   = fn (string $side) => Rule::exists(TaxRate::class, 'id')
            ->where('workspace_id', $workspace?->id)
            ->where("for_{$side}", true)
            ->where('is_active', true);

        return [
            'tax_mode'                => ['required', Rule::in(Workspace::TAX_MODES)],
            'price_tax_mode'          => ['sometimes', 'required', Rule::in(Workspace::PRICE_TAX_MODES)],
            'tax_rounding'            => ['sometimes', 'required', Rule::in(Workspace::TAX_ROUNDINGS)],
            'default_sales_tax_id'    => ['sometimes', 'nullable', 'integer', $rateFor(TaxRate::SALES)],
            'default_purchase_tax_id' => ['sometimes', 'nullable', 'integer', $rateFor(TaxRate::PURCHASES)],
            'default_expense_tax_id'  => ['sometimes', 'nullable', 'integer', $rateFor(TaxRate::EXPENSES)],
            'withholding_tax'         => ['sometimes', 'boolean'],

            // Its tax registration (Doc 11 section 9): registered or not, the
            // country it is registered in, and up to two numbers - a GST and a
            // state VAT, say. Kept with the business's own settings.
            'tax_registration_status' => ['sometimes', 'nullable', Rule::in(BusinessSetting::TAX_REGISTRATION_STATUSES)],
            'tax_country'             => ['sometimes', 'nullable', 'string', Rule::in(Countries::codes())],
            'tax_1_name'              => ['sometimes', 'nullable', 'string', 'max:50'],
            'tax_1_no'                => ['sometimes', 'nullable', 'string', 'max:50'],
            'tax_2_name'              => ['sometimes', 'nullable', 'string', 'max:50'],
            'tax_2_no'                => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'tax_mode.required'              => 'Choose whether the business uses taxes.',
            'tax_mode.in'                    => 'Choose whether the business uses taxes.',
            'price_tax_mode.*'               => 'Choose whether your prices include tax.',
            'tax_rounding.*'                 => 'Choose how tax is rounded.',
            'default_sales_tax_id.exists'    => 'Choose one of your active sales taxes.',
            'default_purchase_tax_id.exists' => 'Choose one of your active purchase taxes.',
            'default_expense_tax_id.exists'  => 'Choose one of your active expense taxes.',
            'tax_registration_status.in'     => 'Choose whether the business is registered for tax.',
            'tax_country.in'                 => 'Choose a country from the list.',
        ];
    }
}
