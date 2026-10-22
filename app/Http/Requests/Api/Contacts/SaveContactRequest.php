<?php

namespace App\Http\Requests\Api\Contacts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The same for adding and for editing, at the lengths the contacts columns
     * hold. Only the name and what the contact is - a customer, a vendor, or
     * both - are required.
     *
     * An optional field left out of an update is left as it was. That is how a
     * form with taxes switched off keeps a contact's tax number: it hides the
     * field and does not send it.
     *
     * workspace_id is not accepted: a contact always belongs to the business
     * the request is for.
     */
    public function rules(): array
    {
        return [
            'is_customer'  => ['required', 'boolean'],
            'is_vendor'    => ['required', 'boolean'],
            'name'         => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email'        => ['nullable', 'email', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'address'      => ['nullable', 'string', 'max:255'],
            'city'         => ['nullable', 'string', 'max:100'],
            'state'        => ['nullable', 'string', 'max:100'],
            'country'      => ['nullable', 'string', 'max:100'],
            'postal_code'  => ['nullable', 'string', 'max:20'],
            'tax_number'   => ['nullable', 'string', 'max:50'],
            'notes'        => ['nullable', 'string', 'max:2000'],
            'is_active'    => ['sometimes', 'boolean'],
        ];
    }

    /** A contact is a customer, a vendor, or both - never neither. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['is_customer', 'is_vendor'])) {
                    return;
                }

                if (! $this->boolean('is_customer') && ! $this->boolean('is_vendor')) {
                    $validator->errors()->add('is_customer', 'Choose customer, vendor, or both.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'is_customer' => 'customer',
            'is_vendor'   => 'vendor',
        ];
    }
}
