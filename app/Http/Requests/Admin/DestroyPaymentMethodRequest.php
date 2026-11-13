<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Deleting a payment method: the admin's password again and a reason, which
 * a method that takes real payments needs (Doc 15 section 15.25).
 */
class DestroyPaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirm_password' => ['nullable', 'string', 'max:255'],
            'reason'           => ['nullable', 'string', 'max:500'],
        ];
    }
}
