<?php

namespace App\Http\Requests\Api\Crm;

use App\Models\User\CrmEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * An e-mail from a lead, an opportunity or a contact (Doc 17 Phase F3a): from
 * exactly one of them, to one address with up to five copied, with its
 * subject and message, and up to five of the record's sales documents and
 * five of its files.
 */
class SendCrmEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lead_id'        => ['nullable', 'integer'],
            'opportunity_id' => ['nullable', 'integer'],
            'contact_id'     => ['nullable', 'integer'],
            'to'             => ['required', 'email', 'max:255'],
            'cc'             => ['nullable', 'array', 'max:5'],
            'cc.*'           => ['email', 'max:255', 'distinct'],
            'subject'        => ['required', 'string', 'max:200', 'not_regex:/[\r\n]/'],
            'body'           => ['required', 'string', 'max:10000'],
            'documents'      => ['nullable', 'array', 'max:'.CrmEmail::MAX_ATTACHED],
            'documents.*'    => ['integer', 'distinct'],
            'files'          => ['nullable', 'array', 'max:'.CrmEmail::MAX_ATTACHED],
            'files.*'        => ['integer', 'distinct'],
        ];
    }

    /** From exactly one record. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $from = array_filter(array_map(fn (string $column) => $this->input($column), CrmEmail::FOR));

                if (count($from) !== 1) {
                    $validator->errors()->add('regarding', 'Send it from one lead, opportunity or contact.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'to'   => 'e-mail address',
            'cc.*' => 'copy address',
        ];
    }

    public function messages(): array
    {
        return [
            'to.required'        => 'Give the address it goes to.',
            'subject.required'   => 'Give it a subject.',
            'subject.not_regex'  => 'Keep the subject to one line.',
            'body.required'      => 'Write the message.',
            'documents.max'      => 'Attach up to five documents.',
            'files.max'          => 'Attach up to five files.',
        ];
    }
}
