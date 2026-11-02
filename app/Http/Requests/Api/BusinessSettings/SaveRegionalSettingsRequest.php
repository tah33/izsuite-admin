<?php

namespace App\Http\Requests\Api\BusinessSettings;

use App\Models\User\DocumentTemplate;
use App\Models\User\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveRegionalSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Settings > Regional & Localization (Doc 11 section 5): a date, time and
     * number format each from its list - the number format brings its
     * decimal and thousands separators - the day the week starts, A4 or
     * Letter, and the month the fiscal year starts; and whether the document
     * templates the business has changed take the formats and paper too.
     */
    public function rules(): array
    {
        return [
            'date_format'             => ['required', Rule::in(DocumentTemplate::DATE_FORMATS)],
            'time_format'             => ['required', Rule::in(Workspace::TIME_FORMATS)],
            'number_format'           => ['required', Rule::in(DocumentTemplate::NUMBER_FORMATS)],
            'week_start'              => ['required', Rule::in(array_keys(Workspace::WEEK_STARTS))],
            'paper_size'              => ['required', Rule::in(DocumentTemplate::PAPER_SIZES)],
            'fiscal_year_start_month' => ['required', 'integer', 'between:1,12'],
            'apply_to_templates'      => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_format.in'                  => 'Choose a date format from the list.',
            'time_format.in'                  => 'Choose a time format from the list.',
            'number_format.in'                => 'Choose a number format from the list.',
            'week_start.in'                   => 'Choose the day your week starts on.',
            'paper_size.in'                   => 'Choose A4 or Letter.',
            'fiscal_year_start_month.between' => 'Choose the month your fiscal year starts in.',
        ];
    }
}
