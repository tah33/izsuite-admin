<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin\FeatureFlag;
use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A feature flag (Doc 15 section 15.12 - Phase E1): its key - fixed once
 * made - name, description, switch, audience (businesses by ID, countries,
 * plans, the share of them), days and the admin responsible.
 */
class SaveFeatureFlagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('key')) {
            $this->merge(['key' => strtolower(trim((string) $this->input('key')))]);
        }
    }

    public function rules(): array
    {
        $editing = $this->route('id') !== null;

        return [
            'key'            => $editing ? ['prohibited'] : ['required', 'string', 'regex:/^[a-z][a-z0-9_.-]{2,59}$/', Rule::unique('feature_flags', 'key')],
            'name'           => ['required', 'string', 'max:100'],
            'description'    => ['nullable', 'string', 'max:2000'],
            'status'         => ['required', Rule::in([FeatureFlag::ON, FeatureFlag::OFF])],
            'businesses'     => ['nullable', 'string', 'max:5000'],
            'countries'      => ['nullable', 'array'],
            'countries.*'    => [Rule::in(Countries::codes())],
            'packages'       => ['nullable', 'array'],
            'packages.*'     => [Rule::exists('packages', 'key')],
            'percentage'     => ['required', 'integer', 'min:0', 'max:100'],
            'starts_at'      => ['nullable', 'date'],
            'ends_at'        => ['nullable', 'date', 'after:starts_at'],
            'responsible_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.regex'       => 'Start with a letter; then 3 to 60 small letters, numbers, dots, dashes or underscores.',
            'key.unique'      => 'Another flag has this key.',
            'key.prohibited'  => 'A flag keeps the key it was made with: the code asks by it.',
            'ends_at.after'   => 'The end comes after the start.',
        ];
    }
}
