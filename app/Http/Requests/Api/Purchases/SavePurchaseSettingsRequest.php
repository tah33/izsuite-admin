<?php

namespace App\Http\Requests\Api\Purchases;

use App\Models\User\Contact;
use Illuminate\Foundation\Http\FormRequest;

class SavePurchaseSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Settings > Purchases, whole (Doc 11 section 16). Payment terms are days,
     * 0 being Due on Receipt, up to the same year a contact's can be.
     */
    public function rules(): array
    {
        return [
            'payment_terms'   => ['required', 'integer', 'min:0', 'max:'.Contact::MAX_PAYMENT_TERMS],
            'notes'           => ['nullable', 'string', 'max:5000'],
            'duplicate_check' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'duplicate_check' => 'duplicate supplier invoice check',
        ];
    }

    public function messages(): array
    {
        return [
            'payment_terms.*' => 'Payment terms are 0 to '.Contact::MAX_PAYMENT_TERMS.' days.',
        ];
    }
}
