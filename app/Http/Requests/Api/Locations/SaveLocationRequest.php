<?php

namespace App\Http\Requests\Api\Locations;

use App\Models\User\Location;
use App\Support\Countries;
use App\Support\LocationCodes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** A code is kept in capitals, however it was typed: "dhk" is DHK. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    /**
     * The same for adding and for editing (Doc 12 section 17): a name, a code
     * unique within the business, a country and a time zone are required;
     * the address, phone, e-mail and the lines its documents add to their
     * header are not. Whether it is active, or the Primary, is changed by
     * actions of their own.
     *
     * Surrounding whitespace is trimmed before this runs, so a name of nothing
     * but spaces is empty here and fails `required`.
     */
    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');

        return [
            'name'            => ['required', 'string', 'max:255'],
            'code'            => [
                'required', 'string', 'max:'.LocationCodes::MAX, 'regex:'.LocationCodes::PATTERN,
                Rule::unique(Location::class, 'code')->where('workspace_id', $workspace?->id)->ignore($this->route('id')),
            ],
            'phone'           => ['nullable', 'string', 'max:30'],
            'email'           => ['nullable', 'email', 'max:255'],
            'address_line_1'  => ['nullable', 'string', 'max:255'],
            'address_line_2'  => ['nullable', 'string', 'max:255'],
            'city'            => ['nullable', 'string', 'max:100'],
            'state'           => ['nullable', 'string', 'max:100'],
            'postal_code'     => ['nullable', 'string', 'max:20'],
            'country'         => ['required', 'string', Rule::in(Countries::codes())],

            // all_with_bc: the dropdown is the browser's own list, which still
            // uses names PHP only keeps as aliases (Asia/Calcutta).
            'timezone'        => ['required', 'string', 'timezone:all_with_bc'],
            'document_header' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.unique' => 'Another of your locations has this code.',
            'code.regex'  => 'Use letters, digits and dashes only.',
            'country.in'  => 'Choose the country from the list.',
        ];
    }
}
