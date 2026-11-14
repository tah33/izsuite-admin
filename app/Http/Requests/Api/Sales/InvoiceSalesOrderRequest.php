<?php

namespace App\Http\Requests\Api\Sales;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A sales order's lines to invoice (Doc 16 sections 16.11 and 16.12): how
 * much of each, and - when that is more than a line has left - why.
 * SalesOrderInvoicing checks what is left and the permission it needs.
 */
class InvoiceSalesOrderRequest extends FormRequest
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
            'reason'           => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<int, string> order line id => quantity */
    public function quantities(): array
    {
        return collect($this->validated('lines'))->mapWithKeys(fn (array $line) => [(int) $line['line_id'] => (string) $line['quantity']])->all();
    }
}
