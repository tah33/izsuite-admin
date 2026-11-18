<?php

namespace App\Http\Requests\Admin;

use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOfflinePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:100'],
            'description'  => ['nullable', 'string', 'max:500'],
            'instructions' => ['nullable', 'string'],
            'image'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,svg,webp', 'max:2048'],
            'is_active'    => ['nullable'],
            'sort_order'   => ['nullable', 'integer', 'min:0'],
            // Switched on, it takes real payments: the password again and a reason (Doc 15 section 15.25).
            'confirm_password' => ['nullable', 'string', 'max:255'],
            'reason'           => ['nullable', 'string', 'max:500'],
            // The billing countries it is offered in (Doc 15 Phase F3b): none, every country.
            'countries'        => ['nullable', 'array'],
            'countries.*'      => [Rule::in(Countries::codes())],
        ];
    }
}
