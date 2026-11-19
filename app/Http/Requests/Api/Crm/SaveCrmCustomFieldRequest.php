<?php

namespace App\Http\Requests\Api\Crm;

use App\Models\User\CrmCustomField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A custom field for leads or opportunities (Doc 17 Phase I4): its name,
 * whose it is - chosen when it is added, never changed - its type, a
 * choice's options (one to fifty, each named once), and whether it is
 * required and switched on.
 */
class SaveCrmCustomFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // A choice's options as they are offered: trimmed, without blank ones.
        if (is_array($this->input('options'))) {
            $this->merge(['options' => array_values(array_filter(array_map(fn ($option) => is_string($option) ? trim($option) : $option, $this->input('options')), fn ($option) => $option !== '' && $option !== null))]);
        }
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:100'],
            'record'      => $this->isMethod('PUT') ? ['prohibited'] : ['required', Rule::in(CrmCustomField::RECORDS)],
            'type'        => ['required', Rule::in(CrmCustomField::TYPES)],
            'options'     => ['nullable', 'required_if:type,choice', 'prohibited_unless:type,choice', 'array', 'min:1', 'max:50'],
            'options.*'   => ['required', 'string', 'max:100', 'distinct'],
            'is_required' => ['sometimes', 'boolean'],
            'is_active'   => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'record.prohibited'          => 'A field stays with leads or opportunities, as it was added.',
            'options.required_if'        => 'Give the choice its options, one to a line.',
            'options.min'                => 'Give the choice its options, one to a line.',
            'options.prohibited_unless'  => 'Only a choice has options.',
            'options.*.distinct'         => 'Name each option once.',
        ];
    }
}
