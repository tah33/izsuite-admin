<?php

namespace App\Http\Resources\Ecommerce;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What an online order's customer could be sold now, as its products are
 * edited (Doc 19 Phase F5b): each listing its storefront shows them at a
 * fixed price - whether it is delivered, its price as the order shows prices
 * (none with variants: theirs), out of stock or how much is left of it when
 * Limited - and the variants it shows, each by its options, with the same.
 * EcommerceOrderProductEdit::offered() gives it.
 */
class EcommerceOrderProductOptionsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $offered = $this->resource;
        $stock   = fn (array $row) => ['price' => $row['price'], 'out_of_stock' => $row['out_of_stock'], 'left' => $row['left']];

        return [
            'currency' => $offered['order']->currency,
            'products' => array_map(fn (array $product) => [
                'id'       => $product['id'],
                'title'    => $product['title'],
                'ships'    => $product['ships'],
                ...$stock($product),
                'variants' => array_map(fn (array $variant) => ['id' => $variant['id'], 'options' => $variant['options'], ...$stock($variant)], $product['variants']),
            ], $offered['products']),
        ];
    }
}
