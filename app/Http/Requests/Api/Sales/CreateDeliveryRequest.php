<?php

namespace App\Http\Requests\Api\Sales;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A sales order's lines to deliver (Doc 16 section 16.10): how much of each.
 * SalesDeliveryService keeps each within what its line has left.
 */
class CreateDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lines'            => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.line_id'  => ['required', 'integer', 'distinct'],
            'lines.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999.999'],
        ];
    }

    /** @return array<int, string> order line id => quantity */
    public function quantities(): array
    {
        return collect($this->validated('lines'))->mapWithKeys(fn (array $line) => [(int) $line['line_id'] => (string) $line['quantity']])->all();
    }
}
