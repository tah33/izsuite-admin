<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\CatalogProduct;
use App\Models\User\EcommerceCart;
use App\Models\User\EcommerceCartLine;
use App\Models\User\Storefront;
use App\Repositories\User\StorefrontCatalogRepository;
use App\Services\Api\Sales\SalesLines;
use App\Services\Sales\SalesCalculator;
use Illuminate\Support\Arr;

/**
 * A cart read against its store as it is now (Doc 19 sections 19.13 and
 * 19.14 - Phase D3), each line:
 *
 * - its listing still shown - at its link too - sold at its price, its
 *   variant one the listing shows (none when it shows none), its item
 *   priced: else Unavailable;
 * - in stock: else Out of Stock - or, Limited, no more than what is left:
 *   else Limited, with what is left;
 * - priced as it is sold (StorefrontPricing::unitPrice()) and as it is shown,
 *   with the price it was shown at before when that changed.
 *
 * The lines that can be bought are totalled as their invoice will be
 * (SalesLines, SalesCalculator), with the tax a sale takes for no customer
 * in particular; shown, amounts take their tax in or out as the store shows
 * prices. Nothing is reserved.
 */
class StorefrontCartLines
{
    public const UNAVAILABLE = 'unavailable';

    public const OUT_OF_STOCK = 'out_of_stock';

    public const LIMITED = 'limited';

    private const TOTALS = ['subtotal', 'discount_total', 'tax_total', 'grand_total', 'net_total', 'base_grand_total', 'base_tax_total', 'base_net_total', 'taxes'];

    public function __construct(
        protected StorefrontCatalogRepository $catalog,
        protected StorefrontPricing $pricing,
        protected SalesLines $salesLines,
        protected SalesCalculator $calculator,
    ) {}

    /**
     * $cart's lines as the store sells them on $today, at $terms, and the
     * totals of those that can be bought - with their invoice rows and each
     * one's price (for the order, Phase D4).
     *
     * @return array{lines: list<array>, shown: array{subtotal: string, tax_total: string, grand_total: string}, totals: array, rows: list<array>, prices: list<array>, ready: bool}
     */
    public function read(Storefront $storefront, EcommerceCart $cart, array $terms, string $today): array
    {
        $shown = $this->catalog->shownAmong($storefront, $cart->lines->pluck('catalog_product_id')->unique()->values()->all(), $today);
        $lines = $cart->lines->map(fn (EcommerceCartLine $line) => $this->line($line, $shown->get($line->catalog_product_id), $terms))->all();

        $buyable = array_values(array_filter($lines, fn (array $line) => $line['problem'] === null));
        $rows    = $buyable === [] ? [] : $this->salesLines->rows($storefront->workspace, array_map(fn (array $line) => [
            'item_id'            => $line['item']->id,
            'name'               => $line['item']->name,
            'quantity'           => (string) $line['line']->quantity,
            'unit'               => $line['item']->unit,
            'unit_price'         => $line['price']['price'],
            'tax_rate_id'        => $this->pricing->taxIdOf($line['item'], $terms),
            'price_list_id'      => $line['price']['price_list_id'],
            'price_list_rule_id' => $line['price']['price_list_rule_id'],
        ], $buyable));

        $workspace = $storefront->workspace;
        $result    = $this->calculator->calculate(array_map(fn (array $row) => $this->salesLines->forCalculation($row), $rows), null, null, $workspace->price_tax_mode, $workspace->tax_rounding, $terms['rate']);
        $subtotal  = '0.00';
        $amounts   = [];

        foreach ($buyable as $key => $line) {
            $amounts[$line['line']->id] = $this->pricing->shownAmount($result['lines'][$key], $terms);
            $rows[$key]                 = [...$rows[$key], ...$result['lines'][$key]];
            $subtotal                   = bcadd($subtotal, $amounts[$line['line']->id], 2);
        }

        $lines = array_map(fn (array $line) => [...$line, 'amount' => $amounts[$line['line']->id] ?? null], $lines);

        return [
            'lines'  => $lines,
            'shown'  => ['subtotal' => $subtotal, 'tax_total' => $result['tax_total'], 'grand_total' => $result['grand_total']],
            'totals' => Arr::only($result, self::TOTALS),
            'rows'   => $rows,
            'prices' => array_map(fn (array $line) => $line['price'], $buyable),
            'ready'  => $lines !== [] && count($buyable) === count($lines),
        ];
    }

    /** A line as the store sells it now: what it is, whether it can be bought - and how much is left, when not all of it - and its prices. */
    private function line(EcommerceCartLine $line, ?CatalogProduct $product, array $terms): array
    {
        $variant  = $product?->variants->firstWhere('id', $line->catalog_product_variant_id);
        $fits     = $product && $product->sales_method === CatalogProduct::FIXED_PRICE
            && ($product->variants->isEmpty() ? $line->catalog_product_variant_id === null : $variant !== null);
        $item     = $fits ? ($variant?->item ?? $product->item) : null;
        $quantity = (string) $line->quantity;
        $price    = $item ? $this->pricing->unitPrice($item, $terms, $quantity) : ['price' => null, 'source' => null, 'price_list_id' => null, 'price_list_rule_id' => null];
        $stock    = $variant ?? $product;
        $left     = $stock?->availability === CatalogProduct::LIMITED ? (string) ($stock->stock_quantity ?? '0') : null;

        $problem = match (true) {
            $item === null, $price['price'] === null                                    => self::UNAVAILABLE,
            $stock->availability === 'out_of_stock', $left !== null && bccomp($left, '0', 3) <= 0 => self::OUT_OF_STOCK,
            $left !== null && bccomp($quantity, $left, 3) > 0                            => self::LIMITED,
            default                                                                      => null,
        };

        $unit = $problem === self::UNAVAILABLE ? null : $this->pricing->priceOf($item, $terms, $quantity);

        return [
            'line'      => $line,
            'product'   => $product,
            'variant'   => $variant,
            'item'      => $item,
            'problem'   => $problem,
            'left'      => $problem === self::LIMITED ? $left : null,
            'price'     => $price,
            'unit'      => $unit,
            'amount'    => null,
            'price_was' => $unit !== null && $line->unit_price !== null && bccomp((string) $line->unit_price, $unit, 2) !== 0 ? (string) $line->unit_price : null,
        ];
    }

}
