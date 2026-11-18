<?php

namespace App\Http\Requests\Api\Crm;

use App\Models\User\CrmEmailTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A CRM e-mail template (Doc 17 Phase F3b): its name - the business's only one by it - a one-line subject and a message. */
class SaveCrmEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');

        return [
            'name'    => ['required', 'string', 'max:100', Rule::unique(CrmEmailTemplate::class, 'name')->where('workspace_id', $workspace?->id)->ignore($this->route('id'))],
            'subject' => ['required', 'string', 'max:200', 'not_regex:/[\r\n]/'],
            'body'    => ['required', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'Name the template.',
            'name.unique'       => 'The business has a template by that name already.',
            'subject.required'  => 'Give it a subject.',
            'subject.not_regex' => 'Keep the subject to one line.',
            'body.required'     => 'Write the message.',
        ];
    }
}
