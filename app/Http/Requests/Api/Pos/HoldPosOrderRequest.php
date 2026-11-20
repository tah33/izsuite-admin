<?php

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A POS cart to hold (Doc 18 Phase D1), as a sale takes it: its customer -
 * none for nobody chosen - its lines, each an item, its quantity, a note,
 * a price typed over (with why) and a discount; the order's discount, why
 * it goes beyond the limit, and its note.
 */
class HoldPosOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact_id'      => ['nullable', 'integer'],
            ...QuotePosCartRequest::cart(),
            'lines.*.note'    => ['nullable', 'string', 'max:500'],
            'discount_reason' => ['nullable', 'string', 'max:500'],
            'note'            => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return QuotePosCartRequest::cartMessages();
    }
}
