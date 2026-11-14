<?php

namespace App\Services\Api\Sales;

use App\Models\User\Item;
use App\Models\User\PriceList;
use App\Models\User\PriceListRule;
use App\Models\User\Workspace;
use App\Repositories\User\PriceListRepository;
use App\Support\Decimal;

/**
 * Pricing sales lines from price lists (Doc 16 section 16.19). For each line
 * of an item, the lists that hold - active, in the document's currency, on its
 * date, for its customer and location - are tried by priority, lowest number
 * first; in a list, the most specific rule wins (item, then category, then
 * brand, then all), then the one from the highest quantity the line reaches.
 * Only one final price comes out per line, with the list and rule that gave
 * it. A line no rule prices keeps the item's own price.
 *
 * A fixed price is the list's, in its currency. A discount comes off the
 * item's sale price and a markup goes on its cost, both in the base currency,
 * so in another currency they are turned at the document's rate.
 */
class PriceListPricing
{
    private const SPECIFIC = ['item' => 0, 'category' => 1, 'brand' => 2, 'all' => 3];

    public function __construct(
        protected PriceListRepository $priceLists,
    ) {}

    /**
     * $context: contact_id, location_id, currency, date, exchange_rate (base
     * per unit of the currency). $lines: key => [item_id, quantity].
     *
     * @return array<string|int, array{unit_price: string, price_list_id: int, price_list_rule_id: int, price_list: string}>
     */
    public function quote(Workspace $workspace, array $context, array $lines): array
    {
        $lists = $this->priceLists->eligible($workspace, $context['currency'], $context['date'], $context['contact_id'] ?? null, $context['location_id'] ?? null);
        $items = $this->priceLists->itemsFor($workspace, array_values(array_filter(array_column($lines, 'item_id'))))->keyBy('id');
        $rate  = Decimal::isZero((string) ($context['exchange_rate'] ?? '1')) ? '1' : (string) ($context['exchange_rate'] ?? '1');
        $out   = [];

        foreach ($lines as $key => $line) {
            $item = $items->get($line['item_id'] ?? 0);

            if (! $item || $lists->isEmpty()) {
                continue;
            }

            foreach ($lists as $list) {
                $rule  = $this->ruleFor($list, $item, (string) ($line['quantity'] ?? '1'), $context['date']);
                $price = $rule ? $this->priceBy($rule, $item, $rate) : null;

                if ($price !== null) {
                    $out[$key] = ['unit_price' => $price, 'price_list_id' => $list->id, 'price_list_rule_id' => $rule->id, 'price_list' => $list->name];

                    break;
                }
            }
        }

        return $out;
    }

    /** The rule of $list that prices $quantity of $item on $date: the most specific, then from the highest quantity. */
    private function ruleFor(PriceList $list, Item $item, string $quantity, string $date): ?PriceListRule
    {
        return $list->rules
            ->filter(fn (PriceListRule $rule) => $this->matches($rule, $item)
                && bccomp((string) $rule->min_quantity, $quantity, 3) <= 0
                && (! $rule->valid_from || $rule->valid_from <= $date)
                && (! $rule->valid_to || $rule->valid_to >= $date))
            ->sortBy([
                fn (PriceListRule $a, PriceListRule $b) => self::SPECIFIC[$a->target] <=> self::SPECIFIC[$b->target],
                fn (PriceListRule $a, PriceListRule $b) => bccomp((string) $b->min_quantity, (string) $a->min_quantity, 3),
                fn (PriceListRule $a, PriceListRule $b) => $a->position <=> $b->position,
            ])
            ->first();
    }

    private function matches(PriceListRule $rule, Item $item): bool
    {
        return match ($rule->target) {
            'item'     => $rule->item_id === $item->id,
            'category' => $rule->item_category_id !== null && $rule->item_category_id === $item->category_id,
            'brand'    => $rule->item_brand_id !== null && $rule->item_brand_id === $item->brand_id,
            default    => true,
        };
    }

    /** What $rule makes $item cost, in the document's currency - null when it cannot say (a markup without a cost). */
    public function priceBy(PriceListRule $rule, Item $item, string $rate): ?string
    {
        $percent = (string) $rule->value;

        $base = match ($rule->method) {
            'fixed'    => null,
            'discount' => $item->sale_price !== null ? bcmul((string) $item->sale_price, bcsub('1', bcdiv($percent, '100', 10), 10), 10) : null,
            'markup'   => $item->purchase_price !== null ? bcmul((string) $item->purchase_price, bcadd('1', bcdiv($percent, '100', 10), 10), 10) : null,
            default    => null,
        };

        if ($rule->method === 'fixed') {
            return Decimal::round((string) $rule->value);
        }

        return $base === null ? null : Decimal::max(Decimal::round(bcdiv($base, $rate, 10)), '0.00');
    }
}
