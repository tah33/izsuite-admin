<?php

namespace App\Http\Requests\Api\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A customer's payment of an online order (Doc 19 Phase F3): its amount, the
 * day it came - not after today, nor before its invoice (checked as Sales
 * checks a payment) - and its reference. Its account is the order's payment
 * method's.
 */
class RecordEcommercePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount'       => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'],
            'payment_date' => ['required', 'date_format:Y-m-d'],
            'reference'    => ['nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return ['payment_date' => 'date'];
    }
}
