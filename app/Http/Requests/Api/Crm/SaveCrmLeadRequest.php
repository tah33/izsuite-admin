<?php

namespace App\Http\Requests\Api\Crm;

use App\Models\Admin\Language;
use App\Models\User\CrmLead;
use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A lead's form (Doc 17 section 17.6 - Phase B1), the same for adding and
 * editing. Required: its name, a phone or an e-mail, and its source. The
 * owner is chosen only on adding - none for the queue - then by assigning.
 * Its source, team and location are checked against the business's by
 * CrmLeadService.
 */
class SaveCrmLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Codes are upper case, and a tag list arrives tidy: each trimmed, empty ones dropped, one given twice - in any case - kept once. */
    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['address_country', 'currency'] as $field) {
            if (is_string($this->input($field))) {
                $merge[$field] = strtoupper(trim($this->input($field)));
            }
        }

        if (is_array($this->input('tags'))) {
            $tags = [];

            foreach ($this->input('tags') as $tag) {
                $tag = is_string($tag) ? trim($tag) : $tag;

                if ($tag !== '' && $tag !== null) {
                    $tags[is_string($tag) ? mb_strtolower($tag) : serialize($tag)] ??= $tag;
                }
            }

            $merge['tags'] = array_values($tags);
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');

        return [
            'name'                   => ['required', 'string', 'max:255'],
            'business_name'          => ['nullable', 'string', 'max:255'],
            'job_title'              => ['nullable', 'string', 'max:100'],
            'email'                  => ['nullable', 'required_without:phone', 'email', 'max:255'],
            'phone'                  => ['nullable', 'required_without:email', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 ().\-]*$/'],
            'source_id'              => ['required', 'integer'],
            'source_details'         => ['nullable', 'string', 'max:255'],
            'owner_id'               => $this->isMethod('PUT') ? ['prohibited'] : ['sometimes', 'nullable', 'integer'],
            'sales_team_id'          => ['nullable', 'integer'],
            'location_id'            => ['nullable', 'integer'],
            'priority'               => ['nullable', Rule::in(CrmLead::PRIORITIES)],
            'estimated_value'        => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
            'currency'               => ['nullable', Rule::in($workspace?->currencyCodes() ?? [])],
            'expected_purchase_date' => ['nullable', 'date_format:Y-m-d'],
            'address_line1'          => ['nullable', 'string', 'max:255'],
            'address_line2'          => ['nullable', 'string', 'max:255'],
            'address_city'           => ['nullable', 'string', 'max:100'],
            'address_state'          => ['nullable', 'string', 'max:100'],
            'address_postal_code'    => ['nullable', 'string', 'max:20'],
            'address_country'        => ['nullable', Rule::in(Countries::codes())],
            'preferred_language'     => ['nullable', Rule::exists(Language::class, 'code')->where('is_active', true)],
            'tags'                   => ['nullable', 'array', 'max:20'],
            'tags.*'                 => ['string', 'max:50'],
            'notes'                  => ['nullable', 'string', 'max:5000'],
            // Phase I4: its custom fields' values by field id, checked by CustomFieldValues. Left out of a change, they stay.
            'custom_fields'          => ['sometimes', 'nullable', 'array', 'max:200'],
            'custom_fields.*'        => ['nullable'],
            // Its qualification (Phase B2a): the budget in the lead's currency.
            'customer_need'          => ['nullable', 'string', 'max:2000'],
            'budget'                 => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99', 'decimal:0,2'],
            'purchase_period'        => ['nullable', Rule::in(CrmLead::PURCHASE_PERIODS)],
            'decision_maker'         => ['nullable', 'string', 'max:255'],
            'business_size'          => ['nullable', 'string', 'max:100'],
            'next_action'            => ['nullable', 'string', 'max:255'],
            'qualification_notes'    => ['nullable', 'string', 'max:5000'],
            // What it is interested in: one of the business's items (LeadInterests checks it), or a name typed in. Left out, they stay as they are.
            'interested_items'            => ['sometimes', 'array', 'max:50'],
            'interested_items.*.item_id'  => ['nullable', 'integer'],
            'interested_items.*.name'     => ['nullable', 'required_without:interested_items.*.item_id', 'string', 'max:255'],
            'interested_items.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999999.999', 'decimal:0,3'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required_without' => 'Give a phone number or an e-mail address.',
            'phone.required_without' => 'Give a phone number or an e-mail address.',
            'source_id.required'     => 'Choose where the lead came from.',
            'owner_id.prohibited'    => 'A lead changes hands by assigning it.',
            'interested_items.*.name.required_without' => 'Choose an item or type its name.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'                   => 'lead name',
            'address_line1'          => 'address line 1',
            'address_line2'          => 'address line 2',
            'address_city'           => 'city',
            'address_state'          => 'state or region',
            'address_postal_code'    => 'postal code',
            'address_country'        => 'country',
            'expected_purchase_date' => 'expected purchase date',
            'tags.*'                 => 'tag',
            'customer_need'          => 'customer need',
            'budget'                 => 'estimated budget',
            'purchase_period'        => 'expected purchase period',
            'business_size'          => 'business size or type',
            'qualification_notes'    => 'qualification notes',
            'interested_items.*.name'     => 'item name',
            'interested_items.*.quantity' => 'quantity',
        ];
    }
}
