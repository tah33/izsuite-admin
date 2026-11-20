<?php

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A POS cart to price (Doc 18 Phases C1 and C3): its customer - none for
 * nobody chosen - its lines, each an item, its quantity and, optionally, a
 * price typed over the item's and a discount; and the order's discount.
 */
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
            'contact_id' => ['nullable', 'integer'],
            ...self::cart(),
        ];
    }

    /** A cart's lines and the order's discount, as completing a sale takes them too (CompletePosOrderRequest). */
    public static function cart(): array
    {
        return [
            'lines'                   => ['required', 'array', 'min:1', 'max:'.self::MAX_LINES],
            'lines.*.item_id'         => ['required', 'integer'],
            'lines.*.quantity'        => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999'],
            // Phase C3: a price typed over the item's, why, and a discount.
            'lines.*.price'           => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'lines.*.override_reason' => ['nullable', 'string', 'max:500'],
            'lines.*.discount_type'   => ['nullable', 'in:percent,amount'],
            'lines.*.discount_value'  => ['nullable', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999999999.99'],
            'discount_type'           => ['nullable', 'in:percent,amount'],
            'discount_value'          => ['nullable', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999999999.99'],
            // Doc 18 Phase I2: a manager's approvals at the register, each a token.
            'approvals'               => ['nullable', 'array'],
            'approvals.beyond'        => ['nullable', 'string', 'max:80'],
            'approvals.override'      => ['nullable', 'string', 'max:80'],
        ];
    }

    public function messages(): array
    {
        return self::cartMessages();
    }

    public static function cartMessages(): array
    {
        return [
            'lines.max'                => 'A sale takes up to '.self::MAX_LINES.' lines.',
            'lines.*.quantity.gt'      => 'The quantity must be more than 0.',
            'lines.*.quantity.decimal' => 'The quantity takes up to 3 decimals.',
            'lines.*.price.decimal'    => 'A price is to the cent.',
        ];
    }
}
