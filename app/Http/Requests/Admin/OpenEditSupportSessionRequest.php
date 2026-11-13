<?php

namespace App\Http\Requests\Admin;

/**
 * A support session with edit access (Doc 15 section 15.22): as a read-only
 * one, and how the customer agreed to it - which the session keeps.
 */
class OpenEditSupportSessionRequest extends OpenSupportSessionRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'customer_authorization' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'customer_authorization.required' => 'Say how the customer agreed to changes.',
            'customer_authorization.min'      => 'Say how the customer agreed to changes.',
        ];
    }
}
