<?php

namespace App\Http\Requests\Api\Sales;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The sales form asking what its lines cost by the price lists (Doc 16 Phase
 * F1): its customer, location, currency, date and rate, and each line's item
 * and quantity under a key of its own.
 */
class QuotePricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact_id'       => ['nullable', 'integer'],
            'location_id'      => ['nullable', 'integer'],
            'currency'         => ['required', 'string', 'size:3'],
            'date'             => ['required', 'date_format:Y-m-d'],
            'exchange_rate'    => ['nullable', 'numeric', 'gt:0'],
            'lines'            => ['present', 'array', 'max:200'],
            'lines.*.key'      => ['required', 'string', 'max:40'],
            'lines.*.item_id'  => ['nullable', 'integer'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}