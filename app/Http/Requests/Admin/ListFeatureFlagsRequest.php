<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin\FeatureFlag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Admin > Feature Flags' filters (Doc 15 Phase E1): words of the name or key, and on or off. */
class ListFeatureFlagsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in([FeatureFlag::ON, FeatureFlag::OFF])],
        ];
    }
}
