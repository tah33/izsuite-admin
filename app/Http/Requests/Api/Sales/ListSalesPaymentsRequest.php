<?php

namespace App\Http\Requests\Api\Sales;

use App\Models\User\SalesPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSalesPaymentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Payments Received's search and filters: by receipt number, reference,
     * customer or invoice number; by status, account and a range of dates.
     * A contact's profile asks for one customer's, refunds too (kind=all).
     */
    public function rules(): array
    {
        return [
            'search'             => ['nullable', 'string', 'max:100'],
            'kind'               => ['nullable', Rule::in([SalesPayment::PAYMENT, SalesPayment::REFUND, 'all'])],
            'contact_id'         => ['nullable', 'integer'],
            'status'             => ['nullable', Rule::in([SalesPayment::COMPLETED, SalesPayment::REVERSED])],
            'payment_account_id' => ['nullable', 'integer'],
            'date_from'          => ['nullable', 'date_format:Y-m-d'],
            'date_to'            => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
