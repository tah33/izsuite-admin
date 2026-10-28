<?php

namespace App\Http\Requests\Api\Sales;

use App\Models\User\Contact;
use Illuminate\Foundation\Http\FormRequest;

class SaveSalesSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Settings > Sales, whole (Doc 11 section 15). Payment terms are days, 0
     * being Due on Receipt, up to the same year a contact's can be; a quotation
     * or proforma invoice is valid for a day to a year.
     */
    public function rules(): array
    {
        return [
            'payment_terms'      => ['required', 'integer', 'min:0', 'max:'.Contact::MAX_PAYMENT_TERMS],
            'quotation_validity' => ['required', 'integer', 'min:1', 'max:365'],
            'proforma_validity'  => ['required', 'integer', 'min:1', 'max:365'],
            'line_discounts'     => ['required', 'boolean'],
            'document_discounts' => ['required', 'boolean'],
            'notes'              => ['nullable', 'string', 'max:5000'],
            'terms'              => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'quotation_validity' => 'quotation validity',
            'proforma_validity'  => 'proforma invoice validity',
            'terms'              => 'terms and conditions',
        ];
    }

    public function messages(): array
    {
        return [
            'payment_terms.*'      => 'Payment terms are 0 to '.Contact::MAX_PAYMENT_TERMS.' days.',
            'quotation_validity.*' => 'A quotation is valid for 1 to 365 days.',
            'proforma_validity.*'  => 'A proforma invoice is valid for 1 to 365 days.',
        ];
    }
}
