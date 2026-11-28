<?php

namespace App\Http\Requests\Api\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A message from a storefront's contact page (Doc 19 Phase H4e): the
 * customer's name, e-mail and phone, and the message - a signed-in
 * customer's name and e-mail their account's.
 */
class SubmitStoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:150'],
            'email'   => ['required', 'email', 'max:255'],
            'phone'   => ['nullable', 'string', 'max:40'],
            'message' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'    => 'Give your name.',
            'email.required'   => 'Give an e-mail we can reply to.',
            'email.email'      => 'Give an e-mail we can reply to.',
            'message.required' => 'Write your message.',
            'message.max'      => 'Keep the message to 2000 characters.',
        ];
    }
}
