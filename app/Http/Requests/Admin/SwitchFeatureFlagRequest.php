<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** A feature flag switched on or off at once (Doc 15 Phase E1). */
class SwitchFeatureFlagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'on' => ['required', 'boolean'],
        ];
    }
}
