<?php

namespace App\Http\Requests\Admin;

use App\Models\Billing\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id     = (int) $this->route('id');
        $method = PaymentMethod::query()->find($id);

        if (! $method) {
            return [];
        }

        if ($method->type === 'online') {
            return [
                'is_active'   => ['nullable'],
                'is_sandbox'  => ['nullable'],
                'credentials' => ['nullable', 'array'],
            ];
        }

        return [
            'name'         => ['required', 'string', 'max:100'],
            'description'  => ['nullable', 'string', 'max:500'],
            'instructions' => ['nullable', 'string'],
            'image'        => ['nullable', 'image', 'mimes:jpg,jpeg,png,svg,webp', 'max:2048'],
            'is_active'    => ['nullable'],
            'sort_order'   => ['nullable', 'integer', 'min:0'],
        ];
    }
}
