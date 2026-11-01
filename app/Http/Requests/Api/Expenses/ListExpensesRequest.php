<?php

namespace App\Http\Requests\Api\Expenses;

use App\Enums\ExpenseStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListExpensesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The expenses list's search and filters (Doc 10 section 8): by number,
     * receipt number, payee, description, category or reference; by status,
     * payment status - "outstanding" is every recorded expense still owing -
     * overdue, payee contact, category, location, currency and a range of
     * dates.
     */
    public function rules(): array
    {
        return [
            'search'         => ['nullable', 'string', 'max:100'],
            'status'         => ['nullable', Rule::enum(ExpenseStatus::class)],
            'payment_status' => ['nullable', Rule::in(['outstanding', 'unpaid', 'partially_paid', 'paid'])],
            'overdue'        => ['nullable', 'boolean'],
            'contact_id'     => ['nullable', 'integer'],
            'category_id'    => ['nullable', 'integer'],
            'location_id'    => ['nullable', 'integer'],
            'currency'       => ['nullable', 'string', 'size:3'],
            'date_from'      => ['nullable', 'date_format:Y-m-d'],
            'date_to'        => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
