<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\EcommerceCartLine;
use App\Services\Files\FileStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A storefront's cart as its visitor sees it (Doc 19 Phase D3): its token -
 * only when it was just made - its currency and whether its prices include
 * tax (null while the business has taxes off); its lines, each with its
 * product, picture, chosen options, quantity, price and amount as the store
 * shows prices, and why it cannot be bought now (unavailable, out of stock,
 * or limited, with what is left) or the price it was shown at before; how
 * many products it holds, its totals over what can be bought, and whether
 * it is ready for checkout. With no cart, an empty one.
 */
class StoreCartResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        ['read' => $read, 'terms' => $terms, 'token' => $token] = $this->resource;
        $lines = $read['lines'] ?? [];

        return [
            'token'              => $token,
            'currency'           => $terms['currency'],
            'prices_include_tax' => $terms['with_tax'],
            'lines'              => array_map(fn (array $line) => $this->line($line), $lines),
            'count'              => (int) array_sum(array_map(fn (array $line) => (float) $line['line']->quantity, $lines)),
            'subtotal'           => $read['shown']['subtotal'] ?? '0.00',
            'tax_total'          => $read['shown']['tax_total'] ?? '0.00',
            'grand_total'        => $read['shown']['grand_total'] ?? '0.00',
            'ready'              => (bool) ($read['ready'] ?? false),
        ];
    }

    private function line(array $read): array
    {
        /** @var EcommerceCartLine $line */
        $line    = $read['line'];
        $product = $read['product'] ?? $line->product;
        $values  = $read['variant']?->option_values ?? $line->variant?->option_values ?? [];
        $names   = array_column($product?->options ?? [], 'name');
        $picture = $read['variant']?->item?->image ?? $read['item']?->image ?? ($read['product'] ? ($read['product']->item->image ?? $read['product']->cover) : null);

        return [
            'id'         => $line->id,
            'title'      => $product?->title ?? $line->item?->name,
            'slug'       => $product?->slug,
            'image_url'  => $picture ? app(FileStorage::class)->url($picture, true) : null,
            'options'    => array_values(array_map(fn ($value, $at) => ['name' => $names[$at] ?? '', 'value' => $value], $values, array_keys($values))),
            'sku'        => $read['item']?->sku,
            'quantity'   => (int) $line->quantity,
            'unit_price' => $read['unit'],
            'amount'     => $read['amount'],
            'problem'    => $read['problem'],
            'left'       => $read['left'] !== null ? (int) $read['left'] : null,
            'price_was'  => $read['price_was'],
        ];
    }
}
