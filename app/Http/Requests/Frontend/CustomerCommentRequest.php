<?php

namespace App\Http\Requests\Frontend;

use Illuminate\Foundation\Http\FormRequest;

/** A customer's comment through a document's secure link (Doc 16 Phase G2), with their name if they give it. */
class CustomerCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:100'],
            'body' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return ['body.required' => 'Write your comment first.'];
    }
}
