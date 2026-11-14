<?php

namespace App\Http\Requests\Api\Sales;

use App\Models\User\SalesDelivery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The Deliveries page's filters (Doc 16 Phase C). */
class ListSalesDeliveriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'    => ['nullable', 'string', 'max:100'],
            'status'    => ['nullable', Rule::in([SalesDelivery::DRAFT, SalesDelivery::READY, SalesDelivery::DELIVERED, SalesDelivery::CANCELLED])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
