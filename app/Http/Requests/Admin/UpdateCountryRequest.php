<?php

namespace App\Http\Requests\Admin;

use App\Models\User\DocumentTemplate;
use App\Models\User\Workspace;
use App\Support\AddressFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A country's form (Doc 15 section 15.19 - Phase F3a): its name and calling
 * code, and the defaults a new business there takes - a currency by its ISO
 * code (one izSuite has not switched on is kept, and offered once it is), a
 * time zone, the regional settings Settings offers a business, its
 * addresses' labels and format - with the day changed defaults start.
 */
class UpdateCountryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // The format's lines each trimmed, an empty one dropped.
        $lines = array_filter(array_map('trim', explode("\n", str_replace("\r", '', (string) $this->input('address_format')))), 'strlen');

        $this->merge([
            'currency'       => $this->filled('currency') ? strtoupper(trim((string) $this->input('currency'))) : null,
            'address_format' => implode("\n", $lines),
        ]);
    }

    public function rules(): array
    {
        return [
            'name'                    => ['required', 'string', 'max:100'],
            'calling_code'            => ['nullable', 'regex:/^\d{1,4}$/'],
            'currency'                => ['nullable', 'regex:/^[A-Z]{3}$/'],
            'timezone'                => ['nullable', Rule::in(\DateTimeZone::listIdentifiers())],
            'date_format'             => ['required', Rule::in(DocumentTemplate::DATE_FORMATS)],
            'time_format'             => ['required', Rule::in(Workspace::TIME_FORMATS)],
            'number_format'           => ['required', Rule::in(DocumentTemplate::NUMBER_FORMATS)],
            'week_start'              => ['required', Rule::in(array_keys(Workspace::WEEK_STARTS))],
            'paper_size'              => ['required', Rule::in(DocumentTemplate::PAPER_SIZES)],
            'fiscal_year_start_month' => ['required', 'integer', 'between:1,12'],
            'region_label'            => ['required', 'string', 'max:40'],
            'postal_label'            => ['required', 'string', 'max:40'],
            'address_format'          => ['required', 'string', 'max:255'],
            'effective_on'            => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    /** Each line of the address format names a part, the first line among them, and only parts it knows. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $format = (string) $this->input('address_format');

                if ($format === '' || $validator->errors()->has('address_format')) {
                    return;
                }

                $unknown = AddressFormat::unknownParts($format);
                $bare    = collect(explode("\n", $format))->contains(fn (string $line) => ! preg_match('/\{[a-z0-9]+\}/', $line));

                $message = match (true) {
                    $unknown !== []                       => 'The address format names '.implode(', ', array_map(fn ($part) => '{'.$part.'}', $unknown)).'. Use only {'.implode('}, {', AddressFormat::PARTS).'}.',
                    ! str_contains($format, '{line1}')    => 'The address format needs {line1}, the address\'s first line.',
                    $bare                                 => 'Each line of the address format names at least one part, such as {city}.',
                    default                               => null,
                };

                if ($message) {
                    $validator->errors()->add('address_format', $message);
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'calling_code.regex'           => 'Enter the calling code as digits only, such as 880.',
            'currency.regex'               => 'Enter the currency as its three-letter code, such as BDT.',
            'timezone.in'                  => 'Choose a time zone from the list.',
            'effective_on.after_or_equal'  => 'Choose today or a later day.',
        ];
    }
}
