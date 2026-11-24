<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\CatalogProduct;
use App\Models\User\CatalogProductVariant;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderLine;
use App\Models\User\Item;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\CatalogProductRepository;
use App\Repositories\User\EcommerceOrderRepository;
use App\Repositories\User\ItemRepository;
use App\Repositories\User\StorefrontCatalogRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Api\Sales\SalesLines;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\BusinessCalendar;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An online order's products changed before payment (Doc 19 section 19.21 -
 * Phase F5b), under Edit Orders:
 *
 * - offered(): what the order's customer could be sold now - the listings its
 *   storefront shows them (signed in, as themselves: Phase E3), searched -
 *   each priced as the order shows prices;
 * - save(): its products as they are to be. One added sells at its price now
 *   for the order's customer, with its tax now; the others keep theirs, their
 *   quantities changed, or taken off - never the last. More of a product
 *   needs its item active and it in stock. A Limited listing or variant
 *   follows the order once it was confirmed - counted down for more, given
 *   back for fewer - and, before, is not counted but never asked for past
 *   what is left. A shipped order's shipping is re-priced by its own method
 *   (EcommerceOrderDeliveryEdit::reprice()); an order that delivers nothing
 *   takes no product to deliver, nor does one that delivers lose them all.
 *   The totals and an issued invoice are worked out again, the payment
 *   method still to take the new total.
 */
class EcommerceOrderProductEdit
{
    use RefusesConflicts;

    /** How many listings offered() finds. */
    private const OFFERED = 10;

    public function __construct(
        protected EcommerceOrderService $orderService,
        protected EcommerceOrderEdits $edits,
        protected EcommerceOrderDeliveryEdit $deliveryEdit,
        protected EcommerceOrderRepository $orders,
        protected EcommerceOrderChecks $checks,
        protected StorefrontCatalogRepository $catalog,
        protected CatalogProductRepository $listings,
        protected ItemRepository $items,
        protected StorefrontPricing $pricing,
        protected StorefrontCheckout $checkout,
        protected StoreVisitor $visitor,
        protected SalesLines $salesLines,
        protected BusinessCalendar $calendar,
    ) {}

    /**
     * The listings order $id's storefront sells its customer now at a fixed
     * price, $search searched - each, or each of its variants, with its price
     * as the order shows prices, and whether it is out of stock or how much is
     * left of it.
     *
     * @return array{order: EcommerceOrder, products: list<array>}
     */
    public function offered(Workspace $workspace, int $id, ?string $search): array
    {
        $order = $this->orders->forConfirming($this->orderService->find($workspace, $id), false);
        $today = $this->calendar->today($workspace)->toDateString();

        $this->edits->checkEditable($order);

        return $this->asCustomer($order, function () use ($workspace, $order, $search, $today) {
            $terms = $this->sellingTerms($workspace, $order, $today);

            return [
                'order'    => $order,
                'products' => $this->catalog->products($order->storefront, ['search' => $search], $today, self::OFFERED)->getCollection()
                    ->filter(fn (CatalogProduct $product) => $product->sales_method === CatalogProduct::FIXED_PRICE)
                    ->map(fn (CatalogProduct $product) => [
                        'id'       => $product->id,
                        'title'    => $product->title,
                        'ships'    => $this->checkout->ships($product->item),
                        ...$this->stockWords($product, $product->item, $terms),
                        'variants' => $product->variants->map(fn (CatalogProductVariant $variant) => [
                            'id'      => $variant->id,
                            'options' => implode(', ', $variant->option_values ?? []),
                            ...$this->stockWords($variant, $variant->item, $terms),
                        ])->values()->all(),
                    ])->values()->all(),
            ];
        });
    }

    /** $lines as UpdateEcommerceOrderProductsRequest takes them: order $id's products as they are to be. The order, as its page shows it. */
    public function save(Workspace $workspace, User $user, int $id, array $lines): EcommerceOrder
    {
        $found = $this->orderService->find($workspace, $id);

        DB::transaction(function () use ($workspace, $user, $found, $lines) {
            $order    = $this->edits->locked($found);
            $today    = $this->calendar->today($workspace)->toDateString();
            $products = $order->lines->where('kind', EcommerceOrderLine::PRODUCT)->keyBy('id');
            $before   = $this->words($order);
            $total    = (string) $order->grand_total;
            $held     = $this->held($this->checks->taken($workspace, $order, true));

            ['kept' => $kept, 'added' => $added] = $this->sorted($products, $lines);

            $new = $added === [] ? [] : $this->asCustomer($order, fn () => $this->added($workspace, $order, $products, $kept, $added, $today));

            $this->checkDelivered($order, $products, $kept, $new);
            $this->checkMore($workspace, $products, $kept);

            foreach ($products as $line) {
                if (! isset($kept[$line->id])) {
                    $this->orders->deleteLine($line);
                } elseif (bccomp($kept[$line->id], (string) $line->quantity, 3) !== 0) {
                    // The line's weight follows its quantity.
                    $this->orders->updateLine($line, ['quantity' => $kept[$line->id], 'weight' => $line->weight === null ? null : bcmul(bcdiv((string) $line->weight, (string) $line->quantity, 10), $kept[$line->id], 3)]);
                }
            }

            $this->orders->addLines($order, $new);

            $order = $this->orders->forConfirming($order, false);

            $this->stock($workspace, $order, $held);
            $this->deliveryEdit->reprice($workspace, $order);

            $order = $this->edits->retotal($workspace, $user, $order);

            $this->edits->checkPaymentLimits($workspace, $order, $total);

            $changes = $this->changes($before, $this->words($order));

            if ($changes !== []) {
                // Logged here, by what happened, so LogApiWriteActivity does not add its own vaguer entry - what each was, in its changes.
                ActivityLogService::record('updated', 'Changed the products of '.$this->orderService->describe($order), $order, ['source' => 'api', 'changes' => $changes]);
            }
        });

        return $this->orderService->page($workspace, $id);
    }

    /**
     * The lines given, sorted: the order's products kept - by id, with their
     * quantities - and those added, by their place among the lines given.
     *
     * @return array{kept: array<int, string>, added: array<int, array>}
     */
    private function sorted(Collection $products, array $lines): array
    {
        $kept  = [];
        $added = [];

        foreach (array_values($lines) as $at => $given) {
            if (empty($given['id'])) {
                $added[$at] = $given;

                continue;
            }

            $line = $products->get((int) $given['id']);

            if (! $line || isset($kept[$line->id])) {
                throw ValidationException::withMessages(["lines.{$at}.id" => $line ? "{$this->checks->title($line)} is given twice: give it once, with its quantity." : 'That product is no longer on the order: reload the order.']);
            }

            $kept[$line->id] = (string) (int) $given['quantity'];
        }

        return ['kept' => $kept, 'added' => $added];
    }

    /**
     * The lines $added as the order's storefront sells them now to its
     * customer - at its price now, with its tax now - each refused, under its
     * place, when it cannot be: none while the store sells in another
     * currency than the order's.
     */
    private function added(Workspace $workspace, EcommerceOrder $order, Collection $products, array $kept, array $added, string $today): array
    {
        $terms = $this->sellingTerms($workspace, $order, $today);

        if ($terms['currency'] !== $order->currency) {
            $this->refuse("{$order->storefront->name} sells in {$terms['currency']} now, and order {$order->number} is in {$order->currency}: its products' quantities can change, or some be taken off, but none be added.");
        }

        $shown = $this->catalog->shownAmong($order->storefront, array_values(array_unique(array_map(fn (array $given) => (int) $given['catalog_product_id'], $added))), $today);
        $on    = $products->filter(fn (EcommerceOrderLine $line) => isset($kept[$line->id]))->map(fn (EcommerceOrderLine $line) => $line->catalog_product_id.'-'.$line->catalog_product_variant_id)->values()->all();
        $rows  = [];

        foreach ($added as $at => $given) {
            $product  = $shown->get((int) $given['catalog_product_id']);
            $variant  = $product?->variants->firstWhere('id', (int) ($given['catalog_product_variant_id'] ?? 0));
            $quantity = (string) (int) $given['quantity'];
            $field    = "lines.{$at}.catalog_product_id";

            if (! $product || $product->sales_method !== CatalogProduct::FIXED_PRICE) {
                throw ValidationException::withMessages([$field => "{$order->storefront->name} does not sell this product now."]);
            }

            if ($product->variants->isNotEmpty() !== ($variant !== null)) {
                throw ValidationException::withMessages(["lines.{$at}.catalog_product_variant_id" => "Choose one of the options {$product->title} is sold in."]);
            }

            $title = $product->title.($variant ? ' ('.implode(', ', $variant->option_values ?? []).')' : '');
            $item  = $variant?->item ?? $product->item;
            $price = $this->pricing->unitPrice($item, $terms, $quantity);

            match (true) {
                in_array($product->id.'-'.$variant?->id, $on, true)                        => throw ValidationException::withMessages([$field => "{$title} is on the order already: change its quantity."]),
                $price['price'] === null                                                    => throw ValidationException::withMessages([$field => "{$title} has no price to sell at."]),
                ($variant ?? $product)->availability === 'out_of_stock'                     => throw ValidationException::withMessages([$field => "{$title} is out of stock."]),
                default                                                                     => null,
            };

            $on[] = $product->id.'-'.$variant?->id;
            $row  = $this->salesLines->rows($workspace, [[
                'item_id' => $item->id, 'name' => $item->name, 'quantity' => $quantity, 'unit' => $item->unit, 'unit_price' => $price['price'],
                'tax_rate_id' => $this->pricing->taxIdOf($item, $terms), 'price_list_id' => $price['price_list_id'], 'price_list_rule_id' => $price['price_list_rule_id'],
            ]])[0];

            $rows[] = [
                'kind'                       => EcommerceOrderLine::PRODUCT,
                'catalog_product_id'         => $product->id,
                'catalog_product_variant_id' => $variant?->id,
                'item_id'                    => $item->id,
                'title'                      => $product->title,
                'options'                    => $variant ? array_values(array_map(fn ($value, $i) => ['name' => $product->options[$i]['name'] ?? '', 'value' => $value], $variant->option_values, array_keys($variant->option_values))) : null,
                'sku'                        => $item->sku,
                'name'                       => $row['name'],
                'unit'                       => $row['unit'],
                'quantity'                   => $quantity,
                'unit_price'                 => $row['unit_price'],
                'price_source'               => $price['source'],
                'price_list_id'              => $row['price_list_id'],
                'price_list_rule_id'         => $row['price_list_rule_id'],
                ...Arr::only($row, ['tax_rate_id', 'tax_name', 'tax_code', 'tax_rate', 'tax_treatment']),
                'amount'                     => '0.00',
                'tax_amount'                 => '0.00',
                'shown_unit_price'           => $this->pricing->priceOf($item, $terms, $quantity),
                'weight'                     => $this->checkout->weightOf(['variant' => $variant, 'product' => $product, 'line' => (object) ['quantity' => $quantity]]),
                'shipped'                    => $this->checkout->ships($item),
            ];
        }

        return $rows;
    }

    /** An order that delivers nothing takes no product to deliver; one that delivers keeps one. */
    private function checkDelivered(EcommerceOrder $order, Collection $products, array $kept, array $new): void
    {
        $shipped = collect($new)->firstWhere('shipped', true);

        if ($order->delivery === EcommerceOrder::NONE && $shipped) {
            $this->refuse("{$shipped['title']} is delivered, and order {$order->number} has nothing to deliver: the customer can order it apart.");
        }

        if ($order->delivery !== EcommerceOrder::NONE && ! $shipped && $products->filter(fn (EcommerceOrderLine $line) => isset($kept[$line->id]) && $line->shipped)->isEmpty()) {
            $this->refuse("Order {$order->number} would have nothing to deliver: keep a product to deliver, or cancel the order for the customer to place it again.");
        }
    }

    /** More of a product the order has: its item still active, and its listing - or variant - not out of stock. */
    private function checkMore(Workspace $workspace, Collection $products, array $kept): void
    {
        $more = $products->filter(fn (EcommerceOrderLine $line) => isset($kept[$line->id]) && bccomp($kept[$line->id], (string) $line->quantity, 3) > 0)->values();

        if ($more->isEmpty()) {
            return;
        }

        $items = $this->items->findManyWithComponents($workspace->id, $more->pluck('item_id')->filter()->unique()->values())->keyBy('id');
        $held  = $this->listings->stockOf($workspace, $more->pluck('catalog_product_id')->filter()->unique()->values()->all(), $more->pluck('catalog_product_variant_id')->filter()->unique()->values()->all(), false);

        foreach ($more as $line) {
            $item  = $items->get($line->item_id);
            $stock = $line->catalog_product_variant_id ? $held['variants']->get($line->catalog_product_variant_id) : $held['products']->get($line->catalog_product_id);

            match (true) {
                ! $item                                  => $this->refuse("{$this->checks->title($line)} is no longer among the business's items: keep its quantity."),
                ! $item->is_active                       => $this->refuse("{$item->name} is inactive in Items: make it active, or keep its quantity."),
                $stock?->availability === 'out_of_stock' => $this->refuse("{$this->checks->title($line)} is out of stock in the Catalog: keep its quantity, or change its stock there first."),
                default                                  => null,
            };
        }
    }

    /**
     * Limited stock following the order's products, from what it $held of
     * each before: once confirmed - counted down then - counted down for more
     * and given back for fewer; before, not counted, but never asked for past
     * what is left.
     */
    private function stock(Workspace $workspace, EcommerceOrder $order, array $held): void
    {
        $now     = $this->held($this->checks->taken($workspace, $order, true));
        $counted = $order->confirmed_at !== null;

        foreach (array_unique([...array_keys($held), ...array_keys($now)]) as $key) {
            $took  = $now[$key] ?? $held[$key];
            $left  = (string) ($took['stock']->stock_quantity ?? '0');
            $after = $now[$key]['quantity'] ?? '0';
            $more  = bcsub($after, $held[$key]['quantity'] ?? '0', 3);

            if (bccomp($more, '0', 3) > 0 && bccomp($counted ? $more : $after, $left, 3) > 0) {
                $this->refuse("Only {$this->checks->plain($left)} of {$took['title']} left, and the order would ".($counted ? "take {$this->checks->plain($more)} more" : "have {$this->checks->plain($after)}").": change what is left in the Catalog, or the order's quantity.");
            }

            if ($counted && bccomp($more, '0', 3) !== 0) {
                $this->listings->setLeft($took['stock'], bcsub($left, $more, 3));
            }
        }
    }

    /** EcommerceOrderChecks::taken()'s holdings by listing ("p…") or variant ("v…"). */
    private function held(array $taken): array
    {
        return collect($taken)->keyBy(fn (array $took) => ($took['stock'] instanceof CatalogProductVariant ? 'v' : 'p').$took['stock']->id)->all();
    }

    /** What the timeline compares: each product's quantity and price, by its title - then the shipping, the discount and the total. */
    private function words(EcommerceOrder $order): array
    {
        $words = [];

        foreach ($order->lines->where('kind', EcommerceOrderLine::PRODUCT) as $line) {
            $words['product:'.$this->checks->title($line)] = $this->checks->plain((string) $line->quantity)." at {$order->currency} ".($line->shown_unit_price ?? $line->unit_price);
        }

        return [
            ...$words,
            ...($order->delivery === EcommerceOrder::SHIPPING ? ['shipping' => ($order->delivery_snapshot['quoted'] ?? false) ? 'quoted after the order' : "{$order->currency} {$order->shipping_total}"] : []),
            ...($order->discount_type ? ['discount' => "{$order->currency} {$order->discount_total}"] : []),
            'total' => "{$order->currency} {$order->grand_total}",
        ];
    }

    /** What changed, as the timeline lists it - the products first, then the shipping, discount and total: a product added or taken off is "none" on the other side. */
    private function changes(array $before, array $after): array
    {
        $keys   = array_unique([...array_keys($before), ...array_keys($after)]);
        $labels = [];

        foreach ([...array_filter($keys, fn (string $key) => str_starts_with($key, 'product:')), ...array_intersect(['shipping', 'discount', 'total'], $keys)] as $key) {
            $labels[$key] = str_starts_with($key, 'product:') ? substr($key, 8) : $key;
        }

        return array_map(fn (array $change) => [...$change, 'from' => $change['from'] ?? 'none', 'to' => $change['to'] ?? 'none'], $this->edits->changes($before, $after, $labels));
    }

    /** What a listing or variant offered sells at, as the order shows prices - and whether it is out of stock, or how much is left. */
    private function stockWords(CatalogProduct|CatalogProductVariant $stock, ?Item $item, array $terms): array
    {
        return [
            'price'        => $item ? $this->pricing->priceOf($item, $terms) : null,
            'out_of_stock' => $stock->availability === 'out_of_stock',
            'left'         => $stock->availability === CatalogProduct::LIMITED ? $this->checks->plain((string) ($stock->stock_quantity ?? '0')) : null,
        ];
    }

    /**
     * How the store sells to the order's customer now - its terms today, as
     * the order shows prices and keeps them. The visitor is to be signed in as
     * the customer (asCustomer()).
     */
    private function sellingTerms(Workspace $workspace, EcommerceOrder $order, string $today): array
    {
        return [...$this->pricing->terms($workspace, $order->storefront, $today), 'with_tax' => $order->prices_include_tax, 'inclusive' => $order->price_tax_mode === 'inclusive'];
    }

    /** $work done as the store serves the order's customer: signed in, as themselves (Phase E3) - else as a visitor. */
    private function asCustomer(EcommerceOrder $order, \Closure $work): mixed
    {
        $this->visitor->signIn($order->customerAccount);

        try {
            return $work();
        } finally {
            $this->visitor->signIn(null);
        }
    }
}
