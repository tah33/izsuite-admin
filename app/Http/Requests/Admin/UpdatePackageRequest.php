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
     * The storage limit is in MB; left empty, the package has no limit. A
     * business already over a lower one keeps its files - it only cannot
     * upload more (Doc 13 section 13.14).
     */
    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:2000'],
            'storage_limit_mb' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'is_active'        => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'storage_limit_mb.integer' => 'Enter the storage limit as a whole number of MB, or leave it empty for no limit.',
            'storage_limit_mb.min'     => 'Enter the storage limit as a whole number of MB, or leave it empty for no limit.',
        ];
    }
}
