<?php

namespace App\Http\Requests\Api\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A deposit invoice of a sales order (Doc 16 section 16.14): a fixed amount,
 * or a percentage of the order's total - and why, when it bills the order
 * past its total.
 */
class CreateDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'   => ['required', Rule::in(['amount', 'percent'])],
            'value'  => ['required', 'numeric', 'decimal:0,2', 'gt:0', $this->input('type') === 'percent' ? 'max:100' : 'max:9999999999999.99'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'value.max' => $this->input('type') === 'percent' ? 'A deposit is at most 100% of the order.' : 'That amount is too large.',
        ];
    }
}
