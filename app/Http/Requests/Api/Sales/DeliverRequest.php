<?php

namespace App\Http\Requests\Api\Sales;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A delivery that arrived (Doc 16 section 16.10): the day, who received it,
 * whether what the order still has left becomes a backorder, and - for one
 * not yet numbered - a number typed rather than the next one.
 */
class DeliverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivered_on'   => ['nullable', 'date_format:Y-m-d'],
            'recipient_name' => ['nullable', 'string', 'max:150'],
            'backorder'      => ['sometimes', 'boolean'],
            'number'         => ['nullable', 'string', 'max:40', 'regex:#^[A-Za-z0-9][A-Za-z0-9 ._/-]*$#'],
        ];
    }
}
