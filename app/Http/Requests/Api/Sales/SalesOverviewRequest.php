<?php

namespace App\Http\Requests\Api\Sales;

use App\Services\Reports\ReportPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The Sales Overview's filters (Doc 16 section 16.5): a range as the Dashboard takes it - This Month unless changed - or two dates; a location, customer, salesperson or team; and a currency to show amounts in. */
class SalesOverviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper($this->input('currency'))]);
        }
    }

    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');

        return [
            'range'          => ['nullable', Rule::in(ReportPeriod::RANGES)],
            'date_from'      => ['nullable', 'required_with:date_to', 'date_format:Y-m-d'],
            'date_to'        => ['nullable', 'required_with:date_from', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'location_id'    => ['nullable', 'integer'],
            'contact_id'     => ['nullable', 'integer'],
            'salesperson_id' => ['nullable', 'integer'],
            'sales_team_id'  => ['nullable', 'integer'],
            'currency'       => ['nullable', Rule::in($workspace?->currencyCodes() ?? [])],
        ];
    }
}
