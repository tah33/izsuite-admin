<?php

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;

/** A POS cart to price (Doc 18 Phase C1): its customer - none for nobody chosen - and its lines, each an item and its quantity. */
class QuotePosCartRequest extends FormRequest
{
    /** The lines a sale takes. */
    public const MAX_LINES = 100;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact_id'       => ['nullable', 'integer'],
            'lines'            => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lines.*.item_id'  => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'lines.max'                => 'A sale takes up to '.self::MAX_LINES.' lines.',
            'lines.*.quantity.gt'      => 'The quantity must be more than 0.',
            'lines.*.quantity.decimal' => 'The quantity takes up to 3 decimals.',
        ];
    }
}
