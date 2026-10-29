<?php

namespace App\Http\Requests\Api\Reports;

use App\Enums\TaxTreatment;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\Sales\OutstandingInvoicesReport;
use App\Services\Reports\Sales\SalesSummaryReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RunReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A report's filters (Doc 07 section 3), each used where the report takes
     * it: a range by name - This Month unless told - or two dates, an as-of
     * day, a grouping, a location, contact, currency, item, item category,
     * payment status, payment account and tax treatment - and the one rate a
     * Tax Summary row opens Tax Details for.
     */
    public function rules(): array
    {
        return [
            'range'              => ['nullable', Rule::in(ReportPeriod::RANGES)],
            'date_from'          => ['nullable', 'required_with:date_to', 'date_format:Y-m-d'],
            'date_to'            => ['nullable', 'required_with:date_from', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'as_of'              => ['nullable', 'date_format:Y-m-d'],
            'group_by'           => ['nullable', Rule::in(array_keys(SalesSummaryReport::GROUPS))],
            'location_id'        => ['nullable', 'integer'],
            'contact_id'         => ['nullable', 'integer'],
            'currency'           => ['nullable', 'string', 'size:3'],
            'item_id'            => ['nullable', 'integer'],
            'category_id'        => ['nullable', 'integer'],
            'payment_status'     => ['nullable', Rule::in(array_keys(OutstandingInvoicesReport::PAYMENT_STATUSES))],
            'payment_account_id' => ['nullable', 'integer'],
            'tax_treatment'      => ['nullable', Rule::enum(TaxTreatment::class)],
            'tax'                => ['nullable', 'string', 'max:150'],
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
