<?php

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;

class TestPosDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** A device's test (Doc 18 Phase I3): for a scanner, what it read. */
    public function rules(): array
    {
        return [
            'scanned' => ['nullable', 'string', 'max:100'],
        ];
    }
}
