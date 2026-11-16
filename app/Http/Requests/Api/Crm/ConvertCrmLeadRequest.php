<?php

namespace App\Http\Requests\Api\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Converting a lead (Doc 17 Phase C1): the contact it becomes - none for a
 * new one made from it - and the opportunity: its name, an open stage, what
 * it may be worth before tax, in which currency, and when it may close.
 * CrmLeadConversion checks the contact and the stage against the business's.
 */
class ConvertCrmLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('opportunity.currency'))) {
            $this->merge(['opportunity' => [...(array) $this->input('opportunity'), 'currency' => strtoupper(trim($this->input('opportunity.currency')))]]);
        }
    }

    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');

        return [
            'contact_id'                      => ['present', 'nullable', 'integer'],
            'opportunity'                     => ['required', 'array'],
            'opportunity.name'                => ['required', 'string', 'max:255'],
            'opportunity.stage_id'            => ['required', 'integer'],
            'opportunity.expected_value'      => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
            'opportunity.currency'            => ['required', Rule::in($workspace?->currencyCodes() ?? [])],
            'opportunity.expected_close_date' => ['required', 'date_format:Y-m-d'],
        ];
    }

    public function attributes(): array
    {
        return [
            'opportunity.name'                => 'opportunity name',
            'opportunity.stage_id'            => 'stage',
            'opportunity.expected_value'      => 'expected value',
            'opportunity.currency'            => 'currency',
            'opportunity.expected_close_date' => 'expected closing date',
        ];
    }

    public function messages(): array
    {
        return [
            'contact_id.present' => 'Choose a contact, or a new one from the lead.',
        ];
    }
}
