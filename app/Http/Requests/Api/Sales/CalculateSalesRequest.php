<?php

namespace App\Http\Requests\Api\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalculateSalesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A form half filled in is still worth totals: a line without a quantity
     * or price counts as nothing yet.
     */
    protected function prepareForValidation(): void
    {
        if (is_array($this->input('lines'))) {
            $this->merge(['lines' => array_map(fn ($line) => is_array($line) ? [
                ...$line,
                'quantity'   => is_numeric($line['quantity'] ?? null) ? $line['quantity'] : '0',
                'unit_price' => is_numeric($line['unit_price'] ?? null) ? $line['unit_price'] : '0',
            ] : $line, $this->input('lines'))]);
        }
    }

    /** The lines and what the totals depend on; the taxes must be the business's own. */
    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');

        return [
            'lines'                  => ['present', 'array', 'max:200'],
            'lines.*.quantity'       => ['numeric', 'min:0', 'max:999999999.999'],
            'lines.*.unit_price'     => ['numeric', 'min:0', 'max:9999999999999.99'],
            'lines.*.discount_type'  => ['nullable', Rule::in(['percent', 'amount'])],
            'lines.*.discount_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'lines.*.tax_rate_id'    => ['nullable', 'integer', Rule::exists('tax_rates', 'id')->where('workspace_id', $workspace?->id)],
            'discount_type'          => ['nullable', Rule::in(['percent', 'amount'])],
            'discount_value'         => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'price_tax_mode'         => ['nullable', Rule::in(['exclusive', 'inclusive'])],
            'tax_rounding'           => ['nullable', Rule::in(['line', 'total'])],
            'exchange_rate'          => ['nullable', 'numeric', 'gt:0', 'max:9999999999'],
        ];
    }
}
