<?php

namespace App\Http\Requests\Api\Documents;

use App\Models\User\DocumentTemplate;
use App\Models\User\EmailSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveEmailSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Empty means the default, never "unknown". */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'sender_name' => trim((string) $this->input('sender_name', '')) ?: null,
            'reply_to'    => trim((string) $this->input('reply_to', '')) ?: null,
        ]);
    }

    /**
     * Settings > Email (Doc 11 sections 15 and 22): an optional sender name
     * and reply-to address, the language, and a subject and body for each
     * kind of email. A subject is one line; neither may be empty.
     */
    public function rules(): array
    {
        $rules = [
            'sender_name' => ['nullable', 'string', 'max:100', 'not_regex:/[\r\n<>"]/'],
            'reply_to'    => ['nullable', 'email', 'max:255'],
            'language'    => ['required', Rule::in(DocumentTemplate::LANGUAGES)],
            'templates'   => ['required', 'array:'.implode(',', array_keys(EmailSetting::DEFAULT_TEMPLATES))],
        ];

        foreach (array_keys(EmailSetting::DEFAULT_TEMPLATES) as $key) {
            $rules["templates.{$key}"]         = ['required', 'array:subject,body'];
            $rules["templates.{$key}.subject"] = ['required', 'string', 'max:200', 'not_regex:/[\r\n]/'];
            $rules["templates.{$key}.body"]    = ['required', 'string', 'max:5000'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'sender_name' => 'sender name',
            'reply_to'    => 'reply-to address',
        ];
    }

    public function messages(): array
    {
        return [
            'sender_name.not_regex' => 'A sender name is one line, without < > or quotes.',
            'templates.*.subject.*' => 'Each email needs a one-line subject of up to 200 characters.',
            'templates.*.body.*'    => 'Each email needs a message of up to 5,000 characters.',
        ];
    }
}
