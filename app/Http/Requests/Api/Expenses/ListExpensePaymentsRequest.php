<?php

namespace App\Http\Requests\Api\Expenses;

use App\Models\User\ExpensePayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListExpensePaymentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Payments Made's search and filters (Doc 10 section 3): by voucher
     * number, reference, payee or expense number; by status, account, payee
     * contact and a range of dates.
     */
    public function rules(): array
    {
        return [
            'search'             => ['nullable', 'string', 'max:100'],
            'status'             => ['nullable', Rule::in([ExpensePayment::COMPLETED, ExpensePayment::REVERSED])],
            'contact_id'         => ['nullable', 'integer'],
            'payment_account_id' => ['nullable', 'integer'],
            'date_from'          => ['nullable', 'date_format:Y-m-d'],
            'date_to'            => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
