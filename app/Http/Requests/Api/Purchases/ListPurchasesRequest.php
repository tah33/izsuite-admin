<?php

namespace App\Http\Requests\Api\Purchases;

use App\Enums\PurchaseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPurchasesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The purchases list's search and filters (Doc 09 section 8): by number,
     * supplier invoice number, supplier, item or reference; by status, payment
     * status - "outstanding" is every recorded purchase still owing - overdue,
     * supplier, location, currency and a range of dates.
     */
    public function rules(): array
    {
        return [
            'search'         => ['nullable', 'string', 'max:100'],
            'status'         => ['nullable', Rule::enum(PurchaseStatus::class)],
            'payment_status' => ['nullable', Rule::in(['outstanding', 'unpaid', 'partially_paid', 'paid'])],
            'overdue'        => ['nullable', 'boolean'],
            'contact_id'     => ['nullable', 'integer'],
            'location_id'    => ['nullable', 'integer'],
            'currency'       => ['nullable', 'string', 'size:3'],
            'date_from'      => ['nullable', 'date_format:Y-m-d'],
            'date_to'        => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
