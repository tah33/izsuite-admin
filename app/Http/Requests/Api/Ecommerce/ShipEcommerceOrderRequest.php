<?php

namespace App\Http\Requests\Api\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A shipment of an online order (Doc 19 Phase G1): how many of each of its
 * products that ship - none for one not in it - the day it left, its carrier,
 * tracking number and link, and notes for the business alone. What is left
 * of each, and the day's bounds, are the service's to check.
 */
class ShipEcommerceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lines'            => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.id'       => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:999999'],
            'shipped_on'       => ['required', 'date_format:Y-m-d'],
            'carrier'          => ['nullable', 'string', 'max:100'],
            'tracking_number'  => ['nullable', 'string', 'max:100'],
            'tracking_url'     => ['nullable', 'string', 'max:500', 'url:https,http'],
            'notes'            => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.required'   => 'Ship at least one product.',
            'tracking_url.url' => 'Give the tracking link as a web address, starting https://.',
        ];
    }

    public function attributes(): array
    {
        return ['shipped_on' => 'day it shipped', 'lines.*.quantity' => 'quantity'];
    }
}
