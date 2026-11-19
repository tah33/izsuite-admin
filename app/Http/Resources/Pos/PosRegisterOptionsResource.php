<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a POS register's form chooses from (Doc 18 Phase A2), as
 * PosRegisterService::options() gives it: the business's currencies; its
 * price lists (with full Sales) with their currency and locations - null for
 * every one; the accounts taking customer payments, with their currency and
 * location; the payment methods switched on, with their account's currency;
 * and who can be a cashier, with the locations they may use.
 */
class PosRegisterOptionsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'currencies'      => $this->resource['currencies'],
            'price_lists'     => $this->resource['price_lists']->map(fn ($list) => [
                'id'           => $list->id,
                'name'         => $list->name,
                'currency'     => $list->currency,
                'location_ids' => $list->all_locations ? null : $list->locations->pluck('id')->values(),
            ])->values(),
            'accounts'        => $this->resource['accounts']->map(fn ($account) => [
                'id'          => $account->id,
                'name'        => $account->name,
                'currency'    => $account->currency,
                'location_id' => $account->location_id,
            ])->values(),
            'payment_methods' => $this->resource['payment_methods']->map(fn ($method) => [
                'id'       => $method->id,
                'name'     => $method->name,
                'type'     => $method->type,
                'currency' => $method->paymentAccount?->currency,
            ])->values(),
            'cashiers'        => $this->resource['cashiers'],
        ];
    }
}
