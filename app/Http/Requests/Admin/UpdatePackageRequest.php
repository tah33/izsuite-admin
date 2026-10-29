<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** An unticked "Active" box sends nothing, so it is read as false. */
    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    /**
     * The storage limit is in MB; the others are counts. Left empty, a limit
     * is no limit. A business already over a lower one keeps what it has - it
     * only cannot add more (Doc 13 section 13.14).
     */
    public function rules(): array
    {
        $count = ['nullable', 'integer', 'min:1', 'max:100000'];

        return [
            'name'             => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:2000'],
            'storage_limit_mb' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'business_limit'   => $count,
            'user_limit'       => $count,
            'location_limit'   => $count,
            'is_active'        => ['boolean'],
        ];
    }

    public function messages(): array
    {
        $count    = 'Enter a whole number of at least 1, or leave it empty for no limit.';
        $messages = [
            'storage_limit_mb.integer' => 'Enter the storage limit as a whole number of MB, or leave it empty for no limit.',
            'storage_limit_mb.min'     => 'Enter the storage limit as a whole number of MB, or leave it empty for no limit.',
        ];

        foreach (['business_limit', 'user_limit', 'location_limit'] as $field) {
            foreach (['integer', 'min', 'max'] as $rule) {
                $messages["{$field}.{$rule}"] = $count;
            }
        }

        return $messages;
    }
}
