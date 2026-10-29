<?php

namespace App\Http\Requests\Api\Sales;

use App\Enums\DocumentType;
use App\Enums\SalesDocumentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSalesDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A list's search and filters (Doc 08 sections 6, 9 and 10): by number,
     * reference or customer name; by status - expired included for quotations
     * and proformas; for invoices by payment status - "outstanding" is every
     * issued invoice still owing, unpaid or partly paid (Doc 02 section 7) -
     * and overdue; by customer, location, currency and a range of dates.
     */
    public function rules(): array
    {
        $type = DocumentType::fromKind((string) $this->route('kind')) ?? DocumentType::Invoice;

        // A credit note's "payment status" is what became of its credit.
        $settled = $type === DocumentType::CreditNote
            ? ['available', 'partially_applied', 'fully_applied', 'refunded']
            : ['outstanding', 'unpaid', 'partially_paid', 'paid', 'refunded'];

        return [
            'search'         => ['nullable', 'string', 'max:100'],
            'status'         => ['nullable', Rule::in(SalesDocumentStatus::filtersFor($type))],
            'payment_status' => ['nullable', Rule::in($settled)],
            'overdue'        => ['nullable', 'boolean'],
            'contact_id'     => ['nullable', 'integer'],
            'location_id'    => ['nullable', 'integer'],
            'currency'       => ['nullable', 'string', 'size:3'],
            'date_from'      => ['nullable', 'date_format:Y-m-d'],
            'date_to'        => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
