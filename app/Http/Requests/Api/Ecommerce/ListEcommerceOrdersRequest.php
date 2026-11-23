<?php

namespace App\Http\Requests\Api\Ecommerce;

use App\Models\User\EcommerceOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The Orders page's filters (Doc 19 Phase F1): the storefront, each of the three statuses, and a number, name or e-mail searched for. */
class ListEcommerceOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'storefront_id'     => ['nullable', 'integer'],
            'status'            => ['nullable', Rule::in(EcommerceOrder::STATUSES)],
            'payment_status'    => ['nullable', Rule::in(EcommerceOrder::PAYMENT_STATUSES)],
            'fulfilment_status' => ['nullable', Rule::in(EcommerceOrder::FULFILMENT_STATUSES)],
            'search'            => ['nullable', 'string', 'max:100'],
        ];
    }
}
