<?php

namespace App\Http\Requests\Api\Sales;

use Illuminate\Foundation\Http\FormRequest;

class SendDocumentEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A document email as its form sends it (Doc 06 section 15): one address
     * to send to, up to five copied, a one-line subject and the message - its
     * placeholders are filled in as it goes. `reminder` makes it a payment
     * reminder for an invoice that still owes.
     */
    public function rules(): array
    {
        return [
            'to'       => ['required', 'email', 'max:255'],
            'cc'       => ['nullable', 'array', 'max:5'],
            'cc.*'     => ['email', 'max:255', 'distinct'],
            'subject'  => ['required', 'string', 'max:200', 'not_regex:/[\r\n]/'],
            'body'     => ['required', 'string', 'max:10000'],
            'reminder' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'to'   => 'email address',
            'cc.*' => 'copy address',
        ];
    }

    public function messages(): array
    {
        return [
            'cc.max'            => 'Copy up to five addresses.',
            'subject.not_regex' => 'A subject is one line.',
        ];
    }
}
