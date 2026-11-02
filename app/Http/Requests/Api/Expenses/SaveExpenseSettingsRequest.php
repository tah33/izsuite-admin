<?php

namespace App\Http\Requests\Api\Expenses;

use App\Models\User\Contact;
use App\Models\User\TaxRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveExpenseSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Settings > Expenses, whole (Doc 11 section 17). Payment terms are days,
     * 0 being Due on Receipt, up to the same year a contact's can be. The
     * default expense tax - while taxes are on - is an active expense rate of
     * this business, or null for none; left out, it stays as it is.
     */
    public function rules(): array
    {
        return [
            'payment_terms'   => ['required', 'integer', 'min:0', 'max:'.Contact::MAX_PAYMENT_TERMS],
            'notes'           => ['nullable', 'string', 'max:5000'],
            'duplicate_check' => ['required', 'boolean'],

            'default_expense_tax_id' => ['sometimes', 'nullable', 'integer', Rule::exists(TaxRate::class, 'id')
                ->where('workspace_id', $this->attributes->get('workspace')?->id)
                ->where('for_expenses', true)
                ->where('is_active', true)],
        ];
    }

    public function attributes(): array
    {
        return [
            'duplicate_check' => 'duplicate expense check',
        ];
    }

    public function messages(): array
    {
        return [
            'payment_terms.*'               => 'Payment terms are 0 to '.Contact::MAX_PAYMENT_TERMS.' days.',
            'default_expense_tax_id.exists' => 'Choose one of your active expense taxes.',
        ];
    }
}
