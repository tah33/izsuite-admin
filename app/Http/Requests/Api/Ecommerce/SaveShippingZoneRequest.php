<?php

namespace App\Http\Requests\Api\Ecommerce;

use App\Models\User\ShippingZone;
use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A shipping zone (Doc 19 Phase D1): its name, its countries, and the
 * regions, cities and postal codes it is narrowed to - a postal code, or its
 * start followed by "*" - and its place. ShippingSettingService keeps each
 * name its own.
 */
class SaveShippingZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $most = ShippingZone::MAX_PLACES;

        return [
            'name'           => ['required', 'string', 'max:100'],
            'countries'      => ['required', 'array', 'min:1', 'max:300'],
            'countries.*'    => ['string', Rule::in(Countries::codes())],
            'regions'        => ['sometimes', 'nullable', 'array', "max:{$most}"],
            'regions.*'      => ['nullable', 'string', 'max:100'],
            'cities'         => ['sometimes', 'nullable', 'array', "max:{$most}"],
            'cities.*'       => ['nullable', 'string', 'max:100'],
            'postal_codes'   => ['sometimes', 'nullable', 'array', "max:{$most}"],
            'postal_codes.*' => ['nullable', 'string', 'max:20', 'regex:/^\s*[A-Za-z0-9][A-Za-z0-9 -]*\*?\s*$/'],
            'position'       => ['sometimes', 'nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function messages(): array
    {
        $most = ShippingZone::MAX_PLACES;

        return [
            'name.required'        => 'Give the zone a name.',
            'countries.required'   => 'Choose the countries it covers.',
            'countries.min'        => 'Choose the countries it covers.',
            'countries.*.in'       => 'Choose among the listed countries.',
            'regions.max'          => "Name up to {$most} regions.",
            'cities.max'           => "Name up to {$most} cities.",
            'postal_codes.max'     => "Name up to {$most} postal codes.",
            'postal_codes.*.regex' => 'Give each postal code in letters, digits, spaces and dashes - or its start followed by * (12*).',
        ];
    }
}
