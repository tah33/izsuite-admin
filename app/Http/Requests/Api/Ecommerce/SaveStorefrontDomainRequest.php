<?php

namespace App\Http\Requests\Api\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;

/** A domain of the business's own for a storefront (Doc 19 Phase A3): a name such as shop.example.com. */
class SaveStorefrontDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'host' => ['required', 'string', 'max:253', 'regex:/^(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63}\.?$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'host.required' => 'Give the domain, such as shop.example.com.',
            'host.regex'    => 'Give a domain such as shop.example.com - without https:// or a path.',
        ];
    }
}
