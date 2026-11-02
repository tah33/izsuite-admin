<?php

namespace App\Http\Requests\Api\BusinessSetup;

use App\Models\User\Contact;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePaymentTermRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A payment term, added or changed (Doc 11 section 14): a name and a
     * number of days, each used once in the business, and whether it is
     * offered to new transactions. Left out, it is.
     */
    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');

        return [
            'name'      => ['required', 'string', 'max:50', Rule::unique('payment_terms', 'name')->where('workspace_id', $workspace?->id)->ignore($this->route('id'))],
            'days'      => ['required', 'integer', 'min:0', 'max:'.Contact::MAX_PAYMENT_TERMS, Rule::unique('payment_terms', 'days')->where('workspace_id', $workspace?->id)->ignore($this->route('id'))],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique'  => 'You already have a term of that name.',
            'days.unique'  => 'You already have a term of that many days.',
            'days.integer' => 'Payment terms are 0 to '.Contact::MAX_PAYMENT_TERMS.' days.',
            'days.min'     => 'Payment terms are 0 to '.Contact::MAX_PAYMENT_TERMS.' days.',
            'days.max'     => 'Payment terms are 0 to '.Contact::MAX_PAYMENT_TERMS.' days.',
        ];
    }
}
