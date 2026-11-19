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
            'categories'     => $this->resource['categories']->map(fn ($category) => ['id' => $category->id, 'name' => $category->name])->all(),
            'frequent'       => PosSellItemResource::collection($this->resource['frequent']),
            'price_tax_mode' => $this->resource['price_tax_mode'],
            'rate_missing'   => $this->resource['rate_missing'],
        ];
    }
}
