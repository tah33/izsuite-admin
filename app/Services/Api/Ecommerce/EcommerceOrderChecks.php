<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\CatalogProduct;
use App\Models\User\CatalogProductVariant;
use App\Models\User\Contact;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderLine;
use App\Models\User\Workspace;
use App\Repositories\User\CatalogProductRepository;
use App\Repositories\User\EcommerceOrderRepository;
use App\Repositories\User\ItemRepository;
use Illuminate\Support\Collection;

/**
 * What stands in the way of confirming an online order, and what is pointed
 * out before it is (Doc 19 section 19.20 - Phase F2). A pending order is
 * confirmed once its products can still be sold:
 *
 * - each product's item still active;
 * - a Limited one - its variant, when it has variants - with enough left for
 *   what the order holds of it, which confirming counts down;
 * - and its contact - or, merged since, the one it was merged into - still
 *   an active customer, as its invoice goes to that contact.
 *
 * Pointed out, never applied: a price the store sells at now that is not the
 * one the order was placed at - the order keeps its prices - and a contact
 * merged since. Out of Stock, set by hand, stops nothing: the business may
 * have set it because of this very order. Nor does the store's status: the
 * order is the customer's already.
 */
class EcommerceOrderChecks
{
    public function __construct(
        protected EcommerceOrderRepository $orders,
        protected CatalogProductRepository $catalog,
        protected ItemRepository $items,
        protected StorefrontPricing $pricing,
        protected StoreVisitor $visitor,
    ) {}

    /**
     * $order - as EcommerceOrderRepository::forConfirming() reads it - on
     * $today, its Limited stock locked when $lock: what stops it (problems),
     * what is pointed out (notices, and prices: each product's price now,
     * where it is another, both as the store shows prices), what confirming
     * counts down (stock), and the contact its invoice goes to.
     *
     * @return array{problems: list<string>, notices: list<string>, prices: list<array{title: string, ordered: string, now: string}>, stock: list<array{stock: CatalogProduct|CatalogProductVariant, title: string, left: string, after: string}>, contact: Contact}
     */
    public function run(Workspace $workspace, EcommerceOrder $order, string $today, bool $lock = false): array
    {
        $products = $order->lines->where('kind', EcommerceOrderLine::PRODUCT)->values();
        $items    = $this->items->findManyWithComponents($workspace->id, $products->pluck('item_id')->filter()->unique()->values())->keyBy('id');
        $contact  = $this->orders->survivingContact($order->contact);
        $problems = $order->status === EcommerceOrder::PENDING ? [] : [$order->status === EcommerceOrder::CONFIRMED
            ? "Order {$order->number} is confirmed already."
            : "Only a pending order is confirmed: {$order->number} is ".str_replace('_', ' ', $order->status).'.'];

        foreach ($products as $line) {
            $item = $items->get($line->item_id);

            if (! $item) {
                $problems[] = "{$this->title($line)} is no longer among the business's items.";
            } elseif (! $item->is_active) {
                $problems[] = "{$item->name} is inactive in Items: make it active, then confirm the order.";
            }
        }

        ['problems' => $short, 'stock' => $stock] = $this->stock($workspace, $order, $lock);

        return [
            'problems' => [...$problems, ...$short, ...$this->contactProblems($contact)],
            'notices'  => $contact->id === $order->contact->id ? [] : ["{$order->contact->name} ({$order->contact->contact_number}) was merged into {$contact->name} ({$contact->contact_number}) since: the invoice goes to {$contact->contact_number}."],
            'prices'   => $this->prices($workspace, $order, $products, $items, $today),
            'stock'    => $stock,
            'contact'  => $contact,
        ];
    }

    /** A product line by its listing's title, with its variant's options. */
    public function title(EcommerceOrderLine $line): string
    {
        return $line->title.($line->options ? ' ('.implode(', ', array_column($line->options, 'value')).')' : '');
    }

    /**
     * What $order takes of each listing or variant that is Limited now - all
     * its product lines of it together, by the first one's title - locked
     * when $lock. A line whose variant is gone since counts toward none.
     * Confirming counts these down; cancelling a confirmed order gives them
     * back (Phase F4).
     *
     * @return list<array{stock: CatalogProduct|CatalogProductVariant, title: string, quantity: string}>
     */
    public function taken(Workspace $workspace, EcommerceOrder $order, bool $lock): array
    {
        $products = $order->lines->where('kind', EcommerceOrderLine::PRODUCT)->values();
        $wanted   = [];

        foreach ($products as $line) {
            $key = match (true) {
                $line->catalog_product_variant_id !== null                    => 'v'.$line->catalog_product_variant_id,
                $line->options === null && $line->catalog_product_id !== null => 'p'.$line->catalog_product_id,
                default                                                       => null,
            };

            if ($key !== null) {
                $wanted[$key] ??= ['quantity' => '0', 'title' => $this->title($line)];
                $wanted[$key]['quantity'] = bcadd($wanted[$key]['quantity'], (string) $line->quantity, 3);
            }
        }

        $held  = $this->catalog->stockOf($workspace, $products->pluck('catalog_product_id')->filter()->unique()->values()->all(), $products->pluck('catalog_product_variant_id')->filter()->unique()->values()->all(), $lock);
        $taken = [];

        foreach ($wanted as $key => $want) {
            $holder = $key[0] === 'v' ? $held['variants']->get((int) substr($key, 1)) : $held['products']->get((int) substr($key, 1));

            if ($holder && $holder->availability === CatalogProduct::LIMITED) {
                $taken[] = ['stock' => $holder, 'title' => $want['title'], 'quantity' => $want['quantity']];
            }
        }

        return $taken;
    }

    /** What confirming $order counts down, and what it takes more of than is left. */
    private function stock(Workspace $workspace, EcommerceOrder $order, bool $lock): array
    {
        $problems = [];
        $stock    = [];

        foreach ($this->taken($workspace, $order, $lock) as $taken) {
            $left = (string) ($taken['stock']->stock_quantity ?? '0');

            if (bccomp($taken['quantity'], $left, 3) > 0) {
                $problems[] = "Only {$this->plain($left)} of {$taken['title']} left, and the order has {$this->plain($taken['quantity'])}: change what is left in the Catalog, then confirm the order.";
            } else {
                $stock[] = ['stock' => $taken['stock'], 'title' => $taken['title'], 'left' => $left, 'after' => bcsub($left, $taken['quantity'], 3)];
            }
        }

        return ['problems' => $problems, 'stock' => $stock];
    }

    /** @return list<string> */
    private function contactProblems(Contact $contact): array
    {
        return match (true) {
            ! $contact->is_active    => ["{$contact->name} ({$contact->contact_number}) is inactive in Contacts: make the contact active, then confirm the order."],
            ! $contact->isCustomer() => ["{$contact->name} ({$contact->contact_number}) is no longer a customer in Contacts: make the contact a customer again, then confirm the order."],
            default                  => [],
        };
    }

    /**
     * Each product the store sells now at another price than the order's -
     * for its customer as they were priced (signed in, their own price lists
     * first: Phase E3) - with both prices as the store shows them. None while
     * the store sells in another currency than the order's.
     */
    private function prices(Workspace $workspace, EcommerceOrder $order, Collection $products, Collection $items, string $today): array
    {
        $this->visitor->signIn($order->customerAccount);

        try {
            $terms = $this->pricing->terms($workspace, $order->storefront, $today);
        } finally {
            $this->visitor->signIn(null);
        }

        if ($terms['currency'] !== $order->currency) {
            return [];
        }

        $changed = [];

        foreach ($products as $line) {
            $item = $items->get($line->item_id);
            $now  = $item ? $this->pricing->unitPrice($item, $terms, (string) $line->quantity)['price'] : null;

            if ($now !== null && bccomp($now, (string) $line->unit_price, 2) !== 0) {
                $changed[] = [
                    'title'   => $this->title($line),
                    'ordered' => (string) ($line->shown_unit_price ?? $line->unit_price),
                    'now'     => $this->pricing->priceOf($item, $terms, (string) $line->quantity) ?? $now,
                ];
            }
        }

        return $changed;
    }

    /** A quantity without the zeros after its point: 2.000 is 2, 1.500 is 1.5. */
    public function plain(string $quantity): string
    {
        return str_contains($quantity, '.') ? rtrim(rtrim($quantity, '0'), '.') : $quantity;
    }
}
