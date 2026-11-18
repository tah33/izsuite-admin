<?php

namespace App\Http\Requests\Api\Sales;

use App\Models\User\SalesReturn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The Returns page's filters (Doc 16 Phase E). */
class ListSalesReturnsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'    => ['nullable', 'string', 'max:100'],
            'status'    => ['nullable', Rule::in([SalesReturn::DRAFT, SalesReturn::SUBMITTED, SalesReturn::AUTHORISED, SalesReturn::REJECTED, SalesReturn::RECEIVED, SalesReturn::COMPLETED, SalesReturn::CANCELLED])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}