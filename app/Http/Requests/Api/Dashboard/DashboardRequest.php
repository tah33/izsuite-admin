<?php

namespace App\Http\Requests\Api\Dashboard;

use App\Services\Reports\ReportPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The Dashboard's filters (Doc 02 section 2): a range by name - Today,
     * This Week, This Month (the default), This Quarter, This Year - or two
     * dates of a custom one, and a location.
     */
    public function rules(): array
    {
        return [
            'range'       => ['nullable', Rule::in(ReportPeriod::RANGES)],
            'date_from'   => ['nullable', 'required_with:date_to', 'date_format:Y-m-d'],
            'date_to'     => ['nullable', 'required_with:date_from', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'location_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'date_from.required_with' => 'Choose both dates of the range.',
            'date_to.required_with'   => 'Choose both dates of the range.',
            'date_to.after_or_equal'  => 'The range ends before it starts.',
        ];
    }
}
