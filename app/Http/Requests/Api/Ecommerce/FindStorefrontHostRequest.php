<?php

namespace App\Http\Requests\Api\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;

/** The name a request reached izSuite at (Doc 19 Phase A3), asked about by the server. */
class FindStorefrontHostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'domain' => ['required', 'string', 'max:253'],
        ];
    }
}
