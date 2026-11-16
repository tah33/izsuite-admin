<?php

namespace App\Http\Requests\Api\Contacts;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Merging another contact into the one in the address (Doc 17 Phase C3):
 * which, as merged_id - read to preview the merge, sent to make it.
 */
class ContactMergeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'merged_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'merged_id.required' => 'Choose the contact to merge into this one.',
        ];
    }
}
