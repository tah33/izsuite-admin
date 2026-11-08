<?php

namespace App\Http\Requests\Api\Billing;

use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The billing details of a business's izSuite subscription (Doc 13 section
 * 11): billing name, e-mail, address and country, and its tax ID where it
 * has one.
 */
class UpdateBillingDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['country' => strtoupper(trim((string) $this->input('country')))]);
    }

    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:150'],
            'email'   => ['required', 'email', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
            'country' => ['required', 'string', 'size:2', Rule::in(Countries::codes())],
            'tax_id'  => ['nullable', 'string', 'max:50'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name'    => 'billing name',
            'email'   => 'billing e-mail',
            'address' => 'billing address',
            'tax_id'  => 'tax ID',
        ];
    }
}
