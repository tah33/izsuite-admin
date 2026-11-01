<?php

namespace App\Http\Requests\Api\Purchases;

use App\Models\User\SupplierPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSupplierPaymentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Payments Made's search and filters: by voucher number, reference,
     * supplier or purchase number; by status, account, supplier and a range
     * of dates.
     */
    public function rules(): array
    {
        return [
            'search'             => ['nullable', 'string', 'max:100'],
            'status'             => ['nullable', Rule::in([SupplierPayment::COMPLETED, SupplierPayment::REVERSED])],
            'contact_id'         => ['nullable', 'integer'],
            'payment_account_id' => ['nullable', 'integer'],
            'date_from'          => ['nullable', 'date_format:Y-m-d'],
            'date_to'            => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
