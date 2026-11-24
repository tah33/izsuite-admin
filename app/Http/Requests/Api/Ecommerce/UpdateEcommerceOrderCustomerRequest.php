<?php

namespace App\Http\Requests\Api\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;

/** An online order's customer as given, corrected before payment (Doc 19 Phase F5a): name, e-mail, phone and company, as checkout takes them. */
class UpdateEcommerceOrderCustomerRequest extends FormRequest
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
            'company' => ['nullable', 'string', 'max:150'],
        ];
    }
}
