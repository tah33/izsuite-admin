<?php

namespace App\Http\Requests\Api\Taxes;

use App\Models\User\WithholdingTax;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveWithholdingTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** A code is upper case, so "wht5" and "WHT5" are one code - on MySQL and SQLite alike. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => mb_strtoupper(trim($this->input('code')))]);
        }
    }

    /**
     * The same for adding and for editing (Doc 11 section 12). The name, code,
     * rate, what it applies to and the date it takes effect are required; the
     * code is used once in the business, and a rate being edited may keep its
     * own. A rate is above 0% and at most 100%, to four decimal places.
     */
    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');

        return [
            'name'           => ['required', 'string', 'max:100'],
            'code'           => [
                'required', 'string', 'max:20', 'regex:/^[A-Z0-9][A-Z0-9._\/-]*$/',
                Rule::unique(WithholdingTax::class, 'code')->where('workspace_id', $workspace?->id)->ignore($this->route('id')),
            ],
            'rate'           => ['required', 'numeric', 'decimal:0,4', 'gt:0', 'max:100'],
            'applies_to'     => ['required', 'array', 'min:1'],
            'applies_to.*'   => ['string', 'distinct', Rule::in(WithholdingTax::SIDES)],
            'effective_from' => ['required', 'date_format:Y-m-d'],
            'effective_to'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:effective_from'],
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
            'code.regex'                  => 'A code uses letters, numbers and . _ / - only, such as WHT5.',
            'code.unique'                 => 'Another withholding tax already has this code.',
            'rate.gt'                     => 'A withholding rate is above 0%.',
            'rate.max'                    => 'A rate is at most 100%.',
            'rate.decimal'                => 'A rate can have at most four decimal places.',
            'applies_to.required'         => 'Choose which payments it is withheld from.',
            'applies_to.min'              => 'Choose which payments it is withheld from.',
            'applies_to.*.in'             => 'Choose customer, supplier or expense payments.',
            'effective_to.after_or_equal' => 'The end date cannot come before the start date.',
        ];
    }
}
