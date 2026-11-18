<?php

namespace App\Http\Requests\Api\Support;

use Illuminate\Foundation\Http\FormRequest;

/** A new support case from the app (Doc 15 section 15.22): what it is about, and the first message. */
class StoreSupportCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'subject.required' => 'Say in a few words what it is about.',
            'message.required' => 'Write what happened, or what you need.',
        ];
    }
}
