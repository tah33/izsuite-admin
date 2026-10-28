<?php

namespace App\Http\Requests\Api\Sales;

use Illuminate\Foundation\Http\FormRequest;

class SaveCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A credit note (Doc 08 section 15): its date, why it is made, and the
     * invoice lines it credits - each with a quantity and price. That those
     * are the invoice's own lines, and credit no more than is left of them, is
     * CreditNoteLines' check. The form's totals preview sends the lines only.
     */
    public function rules(): array
    {
        $preview = $this->routeIs('api.sales.credit-notes.calculate');

        return [
            'document_date'          => $preview ? ['sometimes'] : ['required', 'date_format:Y-m-d'],
            'reason'                 => $preview ? ['sometimes'] : ['required', 'string', 'max:500'],
            'notes'                  => ['nullable', 'string', 'max:5000'],
            'lines'                  => ['required', 'array', 'min:1', 'max:500'],
            'lines.*.source_line_id' => ['required', 'integer', 'distinct'],
            'lines.*.quantity'       => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:9999999999'],
            'lines.*.unit_price'     => ['required', 'numeric', 'decimal:0,2', 'gte:0', 'max:9999999999999.99'],
        ];
    }

    public function attributes(): array
    {
        return [
            'document_date'      => 'date',
            'lines.*.quantity'   => 'quantity',
            'lines.*.unit_price' => 'price',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required'                 => 'Say why the credit note is made - a return, a price correction.',
            'lines.required'                  => 'Credit at least one line.',
            'lines.min'                       => 'Credit at least one line.',
            'lines.*.source_line_id.distinct' => 'Each invoice line is credited once on a credit note.',
            'lines.*.quantity.gt'             => 'The quantity must be more than zero.',
            'decimal'                         => 'The :attribute has too many decimal places.',
        ];
    }
}
