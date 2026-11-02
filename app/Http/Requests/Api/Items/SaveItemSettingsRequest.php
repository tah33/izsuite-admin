<?php

namespace App\Http\Requests\Api\Items;

use App\Models\User\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveItemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Settings > Items (Doc 11 section 18): whether brands are used - always
     * said - and, left as they are when left out, whether warranty fields
     * are, the business's own units of measure (each a name used once), the
     * unit new items start with, SKUs given automatically and their prefix,
     * and the barcode format.
     */
    public function rules(): array
    {
        return [
            'brands_enabled'   => ['required', 'boolean'],
            'warranty_enabled' => ['sometimes', 'boolean'],
            'default_unit'     => ['sometimes', 'nullable', 'string', 'max:20'],
            'units'            => ['sometimes', 'array', 'max:50'],
            'units.*'          => ['required', 'string', 'max:20', 'distinct:ignore_case'],
            'sku_auto'         => ['sometimes', 'boolean'],
            'sku_prefix'       => ['sometimes', 'nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9\-\/_.]*$/'],
            'barcode_format'   => ['sometimes', 'required', Rule::in(Workspace::BARCODE_FORMATS)],
        ];
    }

    public function messages(): array
    {
        return [
            'brands_enabled.required' => 'Choose whether the business uses brands.',
            'brands_enabled.boolean'  => 'Choose whether the business uses brands.',
            'units.max'               => 'Keep to 50 units of your own.',
            'units.*.required'        => 'Give each unit a name.',
            'units.*.max'             => 'A unit is up to 20 characters.',
            'units.*.distinct'        => 'Each unit is on the list once.',
            'sku_prefix.regex'        => 'A SKU prefix is letters, digits and - / _ . only.',
            'barcode_format.*'        => 'Choose a barcode format from the list.',
        ];
    }

    /** Names trimmed, so " Tray" and "Tray" are the same unit. */
    protected function prepareForValidation(): void
    {
        if (is_array($this->input('units'))) {
            $this->merge(['units' => array_map(fn ($unit) => is_string($unit) ? trim($unit) : $unit, $this->input('units'))]);
        }

        if (is_string($this->input('default_unit'))) {
            $this->merge(['default_unit' => trim($this->input('default_unit')) ?: null]);
        }
    }
}
