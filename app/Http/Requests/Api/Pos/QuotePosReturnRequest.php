<?php

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;

/** A POS return to price (Doc 18 Phase E1): the sale, and each of its lines brought back with how much. */
class QuotePosReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return self::lines();
    }

    /** The sale and the lines, as recording the return takes them too (RecordPosReturnRequest). */
    public static function lines(): array
    {
        return [
            'order_id'         => ['required', 'integer'],
            'lines'            => ['required', 'array', 'min:1', 'max:'.QuotePosCartRequest::MAX_LINES],
            'lines.*.line_id'  => ['required', 'integer', 'distinct'],
            'lines.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999'],
        ];
    }

    public function messages(): array
    {
        return self::lineMessages();
    }

    public static function lineMessages(): array
    {
        return [
            'lines.required'           => 'Choose what is brought back.',
            'lines.*.quantity.gt'      => 'The quantity must be more than 0.',
            'lines.*.quantity.decimal' => 'The quantity takes up to 3 decimals.',
        ];
    }
}
