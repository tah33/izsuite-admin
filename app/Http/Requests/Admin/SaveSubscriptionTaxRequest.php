<?php

namespace App\Http\Requests\Admin;

use App\Repositories\Billing\SubscriptionTaxRepository;
use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A subscription tax rule (Doc 15 section 16): its country, name and rate,
 * the day it starts - one per country and day - and whether a buyer's tax ID
 * makes it a reverse charge, with the note the invoice then prints.
 */
class SaveSubscriptionTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'country'        => strtoupper(trim((string) $this->input('country'))),
            'name'           => trim((string) $this->input('name')),
            'reverse_charge' => $this->boolean('reverse_charge'),
        ]);
    }

    public function rules(): array
    {
        return [
            'country'             => ['required', 'string', 'size:2', Rule::in(Countries::codes())],
            'name'                => ['required', 'string', 'max:50'],
            'rate'                => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:100'],
            'starts_on'           => ['required', 'date_format:Y-m-d'],
            'reverse_charge'      => ['boolean'],
            'reverse_charge_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** One rule per country and start date: a change to it is an edit. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['country', 'starts_on'])) {
                    return;
                }

                $id = $this->route('id');

                if (app(SubscriptionTaxRepository::class)->startsOnTaken($this->input('country'), $this->input('starts_on'), $id ? (int) $id : null)) {
                    $validator->errors()->add('starts_on', 'This country has a rule starting on that day already: edit that one instead.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return ['starts_on' => 'start date', 'reverse_charge_note' => 'note'];
    }
}
