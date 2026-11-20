<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the POS selling screen starts with (Doc 18 Phase C1): the user's open
 * session and its register - its location, currency and price list - the
 * walk-in customer, the category tiles and the most-sold items, whether
 * prices include tax, and whether the register's currency has no rate
 * today. Without an open session, its session alone, null.
 */
class PosSellScreenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $session = $this->resource['session'];

        if (! $session) {
            return ['session' => null];
        }

        $register = $session->register;
        $walkIn   = $this->resource['walk_in'];

        return [
            'session'        => ['id' => $session->id, 'number' => $session->number],
            'register'       => [
                'name'       => $register->name,
                'code'       => $register->code,
                'currency'   => $register->currency,
                'location'   => $register->location?->name,
                'price_list' => $register->priceList?->name,
            ],
            'walk_in'        => $walkIn ? ['id' => $walkIn->id, 'name' => $walkIn->name] : null,
            // Phase C2: the payment methods the register takes now, in their order.
            'methods'        => $this->resource['methods']->map(fn ($method) => [
                'id' => $method->id, 'name' => $method->name, 'type' => $method->type, 'needs_reference' => $method->needs_reference, 'gives_change' => $method->gives_change,
                'confirms_later' => $method->confirms_later,
            ])->all(),
            'categories'     => $this->resource['categories']->map(fn ($category) => ['id' => $category->id, 'name' => $category->name])->all(),
            'frequent'       => PosSellItemResource::collection($this->resource['frequent']),
            'price_tax_mode' => $this->resource['price_tax_mode'],
            'rate_missing'   => $this->resource['rate_missing'],
            // Phase C3: the step a cash sale's total rounds to - null for none.
            'cash_rounding'  => $this->resource['cash_rounding'] === null ? null : (string) $this->resource['cash_rounding'],
            // Phase C4: the session's last sale - its receipt's copies counted - or null.
            'last_order'     => $this->resource['last_order'] ? new PosReceiptResource($this->resource['last_order']) : null,
            // Phase D1: how many orders wait at the register.
            'held_count'     => $this->resource['held_count'],
            // Phase D2: how many of the session's payments wait to be confirmed.
            'pending_count'  => $this->resource['pending_count'],
            // Phase D3a: whether a sale can be left on a customer's account.
            'credit_sales'   => $this->resource['credit_sales'],
            // Phase I1: whether the register is locked, after how many idle minutes it locks itself, and whether the cashier has a PIN.
            'lock'           => $this->resource['lock'],
        ];
    }
}
