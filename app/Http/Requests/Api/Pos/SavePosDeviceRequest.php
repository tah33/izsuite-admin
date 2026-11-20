<?php

namespace App\Http\Requests\Api\Pos;

use App\Models\User\PosDevice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePosDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** A register's device (Doc 18 Phase I3): its kind, name, connection and where, and whether it is in use. */
    public function rules(): array
    {
        return [
            'type'       => ['required', Rule::in(array_keys(PosDevice::TYPES))],
            'name'       => ['required', 'string', 'max:100'],
            'connection' => ['required', Rule::in(PosDevice::CONNECTIONS)],
            'address'    => ['nullable', 'string', 'max:255'],
            'is_active'  => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name the device, as the register knows it.',
        ];
    }
}
