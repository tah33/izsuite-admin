<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\EcommerceOrderLine;
use App\Models\User\EcommercePaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payment link's page for its customer (Doc 19 Phase F6a): the order it
 * is for - its number, products and total - what the link asks now and the
 * business's day it works until, whether it is active, expired, revoked or
 * paid, and while active each method it offers, with its instructions.
 * Nothing of the business's own notes.
 * StorefrontPaymentLinkPage::show() gives it.
 */
class StorePaymentLinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        ['link' => $link, 'state' => $state, 'amount' => $amount, 'expires_on' => $expiresOn, 'methods' => $methods] = $this->resource;
        $order = $link->order;

        return [
            'number'     => $order->number,
            'state'      => $state,
            'amount'     => $amount,
            'currency'   => $link->currency,
            'total'      => (string) $order->grand_total,
            'expires_on' => $expiresOn,
            'lines'      => $order->lines->where('kind', EcommerceOrderLine::PRODUCT)->map(fn (EcommerceOrderLine $line) => [
                'title'    => $line->title,
                'options'  => array_column($line->options ?? [], 'value'),
                'quantity' => (int) $line->quantity,
            ])->values(),
            'methods'    => $methods->map(fn (EcommercePaymentMethod $method) => [
                'id'           => $method->id,
                'name'         => $method->name,
                'instructions' => $method->instructions,
            ])->values(),
        ];
    }
}
