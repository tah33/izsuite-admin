<?php

namespace App\Http\Requests\Api\Taxes;

use App\Enums\TaxTreatment;
use App\Models\User\TaxRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTaxRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A code is upper case, so "vat15" and "VAT15" are one code - on MySQL and
     * SQLite alike. Only a standard rate has a percentage of its own: the
     * others are 0%, whatever was typed.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => mb_strtoupper(trim($this->input('code')))]);
        }

        $treatment = TaxTreatment::tryFrom((string) $this->input('treatment'));

        if ($treatment && ! $treatment->charges()) {
            $this->merge(['rate' => '0']);
        }
    }

    /**
     * The same for adding and for editing (Doc 11 section 10). The name, code,
     * rate, treatment, what it applies to and the date it takes effect are
     * required; the code is used once in the business, and a rate being edited
     * may keep its own. A standard rate is above 0% and at most 100%, to four
     * decimal places.
     */
    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');
        $charges   = TaxTreatment::tryFrom((string) $this->input('treatment'))?->charges() ?? true;

        return [
            'name'           => ['required', 'string', 'max:100'],
            'code'           => [
                'required', 'string', 'max:20', 'regex:/^[A-Z0-9][A-Z0-9._\/-]*$/',
                Rule::unique(TaxRate::class, 'code')->where('workspace_id', $workspace?->id)->ignore($this->route('id')),
            ],
            'treatment'      => ['required', Rule::enum(TaxTreatment::class)],
            'rate'           => $charges
                ? ['required', 'numeric', 'decimal:0,4', 'gt:0', 'max:100']
                : ['required', 'numeric'],
            'applies_to'     => ['required', 'array', 'min:1'],
            'applies_to.*'   => ['string', 'distinct', Rule::in(TaxRate::SIDES)],
            'recoverability' => ['nullable', Rule::in(TaxRate::RECOVERABILITY)],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
            'description'    => ['nullable', 'string', 'max:1000'],
            'is_active'      => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'applies_to'     => 'applies to',
            'effective_from' => 'effective from date',
            'effective_to'   => 'effective to date',
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex'                  => 'A code uses letters, numbers and . _ / - only, such as VAT15.',
            'code.unique'                 => 'Another tax rate already has this code.',
            'treatment.*'                 => 'Choose how this tax treats what it is applied to.',
            'rate.gt'                     => 'A standard rate is above 0%. For 0%, choose Zero-Rated, Exempt, Out of Scope or No Tax.',
            'rate.max'                    => 'A rate is at most 100%.',
            'rate.decimal'                => 'A rate can have at most four decimal places.',
            'applies_to.required'         => 'Choose what this tax applies to.',
            'applies_to.min'              => 'Choose what this tax applies to.',
            'applies_to.*.in'             => 'Choose sales, purchases or expenses.',
            'recoverability.in'           => 'Choose whether this tax can be claimed back.',
            'effective_to.after_or_equal' => 'The end date cannot come before the start date.',
        ];
    }
}
