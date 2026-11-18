<?php

namespace App\Http\Requests\Api\Crm;

use App\Models\User\CrmLead;
use App\Models\User\CrmOpportunity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * An opportunity's form (Doc 17 section 17.12 - Phase D1), the same for
 * adding and editing. Required: its name, contact, expected value before
 * tax, currency and expected closing date - and on adding, its stage. After
 * that the stage changes by moving it. Its contact, stage, owner, source,
 * team and location are checked against the business's by
 * CrmOpportunityService; a probability other than the stage's needs Change
 * Probability.
 */
class SaveCrmOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** The currency is upper case, and the tags and competitors arrive tidy: trimmed, empty ones dropped, each - in any case - once. */
    protected function prepareForValidation(): void
    {
        $merge = [];

        if (is_string($this->input('currency'))) {
            $merge['currency'] = strtoupper(trim($this->input('currency')));
        }

        foreach (['tags', 'competitors'] as $field) {
            if (is_array($this->input($field))) {
                $kept = [];

                foreach ($this->input($field) as $value) {
                    $value = is_string($value) ? trim($value) : $value;

                    if ($value !== '' && $value !== null) {
                        $kept[is_string($value) ? mb_strtolower($value) : serialize($value)] ??= $value;
                    }
                }

                $merge[$field] = array_values($kept);
            }
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');

        return [
            'name'                => ['required', 'string', 'max:255'],
            'contact_id'          => ['required', 'integer'],
            'contact_person'      => ['nullable', 'string', 'max:100'],
            'stage_id'            => $this->isMethod('PUT') ? ['prohibited'] : ['required', 'integer'],
            // Phase D3b: the pipeline it starts in - the first that fits, left out.
            'pipeline_id'         => $this->isMethod('PUT') ? ['prohibited'] : ['nullable', 'integer'],
            'probability'         => ['nullable', 'integer', 'min:0', 'max:100'],
            'owner_id'            => ['sometimes', 'nullable', 'integer'],
            'sales_team_id'       => ['nullable', 'integer'],
            'location_id'         => ['nullable', 'integer'],
            'source_id'           => ['nullable', 'integer'],
            'priority'            => ['nullable', Rule::in(CrmLead::PRIORITIES)],
            'expected_value'      => ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
            'currency'            => ['required', Rule::in($workspace?->currencyCodes() ?? [])],
            'expected_close_date' => ['required', 'date_format:Y-m-d'],
            'requirements'        => ['nullable', 'string', 'max:5000'],
            'next_action'         => ['nullable', 'string', 'max:255'],
            'competitors'         => ['nullable', 'array', 'max:'.CrmOpportunity::MAX_COMPETITORS],
            'competitors.*'       => ['string', 'max:100'],
            'tags'                => ['nullable', 'array', 'max:20'],
            'tags.*'              => ['string', 'max:50'],
            'notes'               => ['nullable', 'string', 'max:5000'],
            // What it is for: one of the business's items (LeadInterests checks it), or a name typed in. Left out, they stay as they are.
            'interested_items'            => ['sometimes', 'array', 'max:50'],
            'interested_items.*.item_id'  => ['nullable', 'integer'],
            'interested_items.*.name'     => ['nullable', 'required_without:interested_items.*.item_id', 'string', 'max:255'],
            'interested_items.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999999.999', 'decimal:0,3'],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_id.required'  => 'Choose the contact it is for.',
            'stage_id.required'    => 'Choose the stage it starts at.',
            'stage_id.prohibited'  => 'An opportunity changes stage by moving it.',
            'competitors.max'      => 'Name up to '.CrmOpportunity::MAX_COMPETITORS.' competitors.',
            'interested_items.*.name.required_without' => 'Choose an item or type its name.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'                => 'opportunity name',
            'contact_person'      => 'person to deal with',
            'expected_value'      => 'expected value',
            'expected_close_date' => 'expected closing date',
            'requirements'        => 'customer requirements',
            'next_action'         => 'next action',
            'competitors.*'       => 'competitor',
            'tags.*'              => 'tag',
        ];
    }
}
