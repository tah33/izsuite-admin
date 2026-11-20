<?php

namespace App\Services\Api\Pos;

use App\Enums\Edition;
use App\Models\User\Contact;
use App\Models\User\Item;
use App\Models\User\PosOrderLine;
use App\Models\User\PosRegister;
use App\Models\User\PriceList;
use App\Models\User\TaxRate;
use App\Models\User\Workspace;
use App\Repositories\User\PriceListRepository;
use App\Repositories\User\TaxRateRepository;
use App\Services\Api\BusinessSetup\ExchangeRateService;
use App\Services\Api\Sales\PriceListPricing;
use App\Services\Api\Sales\SalesLines;
use App\Services\Sales\SalesCalculator;
use App\Services\User\Apps\WorkspaceApps;
use App\Support\Decimal;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * What a POS register sells at (Doc 18 sections 18.12 to 18.14 - Phases C1
 * to C3), in its currency: the customer's own price list - an active one
 * that names them, for the register's currency and location, by priority -
 * else the register's price list, else the item's sales price (a combo
 * priced as its components' sum, theirs), divided by today's rate when the
 * register's currency is not the base one. Price lists are the full Sales
 * app's. Each price keeps where it came from - or, typed over (Phase C3),
 * the price it replaced.
 *
 * Taxes as Settings → Taxes says: none while taxes are off; else the
 * customer's tax treatment's rate - none when the business has no rate of
 * it - else the item's own sales tax while it can be used, else the default
 * one, as a sales form chooses them. A cart is totalled as a sales document
 * is (SalesCalculator): its line discounts and the order's - shared over its
 * lines before tax - with prices with or without tax, rounded by line or in
 * total.
 *
 * A cash sale's total rounds to Settings → Point of Sale's step (Phase C3):
 * the difference is a line of its own, Cash rounding - no discount's share,
 * no tax - kept apart from the discounts.
 */
class PosPricing
{
    public const ITEM = 'item';

    public const PRICE_LIST = 'price_list';

    public const CUSTOMER_PRICE_LIST = 'customer_price_list';

    public const ROUNDING = 'Cash rounding';

    private const TOTALS = ['subtotal', 'discount_total', 'tax_total', 'grand_total', 'net_total', 'base_grand_total', 'base_tax_total', 'base_net_total', 'taxes'];

    public function __construct(
        protected PriceListRepository $priceLists,
        protected PriceListPricing $listPricing,
        protected TaxRateRepository $taxRates,
        protected ExchangeRateService $exchangeRates,
        protected SalesLines $salesLines,
        protected SalesCalculator $calculator,
    ) {}

    /** The base currency per unit of the register's on $date - null when the business has no rate for it. */
    public function rate(Workspace $workspace, PosRegister $register, string $date): ?string
    {
        return $this->exchangeRates->rateOn($workspace, $register->currency, $date);
    }

    /**
     * $items' prices at the register for no customer in particular - what
     * the screen shows them at - keyed by item id.
     *
     * @return array<int, array{price: ?string, source: string, price_list: ?string}>
     */
    public function standard(Workspace $workspace, PosRegister $register, Collection $items, string $rate, string $date): array
    {
        $lines  = $items->values()->map(fn (Item $item) => ['item' => $item, 'quantity' => '1'])->all();
        $prices = $this->prices($workspace, $register, null, $lines, $rate, $date);
        $out    = [];

        foreach ($lines as $key => $line) {
            $out[$line['item']->id] = Arr::only($prices[$key], ['price', 'source', 'price_list']);
        }

        return $out;
    }

    /**
     * The cart for $contact - $lines as PosCounter::lines() gives them, in
     * order - priced, taxed and totalled as its invoice is: each line as a
     * sales line keeps it (SalesLines::rows()) - its note its description,
     * its discount its own - with its amounts; each line's price and where it
     * came from; the totals, after $discount, the order's (type and value);
     * and the cash rounding to $step, when one is given - the rounding line
     * last among the rows.
     *
     * @return array{rows: list<array>, prices: list<array>, totals: array, rounding: string}
     */
    public function priced(Workspace $workspace, PosRegister $register, ?Contact $contact, array $lines, string $rate, string $date, ?array $discount = null, ?string $step = null): array
    {
        $lines  = array_values($lines);
        $prices = $this->prices($workspace, $register, $contact, $lines, $rate, $date);
        $taxFor = $this->taxFor($workspace, $contact, $date);

        $rows = $this->salesLines->rows($workspace, array_map(fn (array $line, array $price) => [
            'item_id'            => $line['item']->id,
            'name'               => $line['item']->name,
            'description'        => $line['note'] ?? null,
            'quantity'           => $line['quantity'],
            'unit'               => $line['item']->unit,
            'unit_price'         => $price['price'] ?? '0',
            'discount_type'      => $line['discount_type'] ?? null,
            'discount_value'     => $line['discount_value'] ?? null,
            'tax_rate_id'        => $taxFor($line['item']),
            'price_list_id'      => $price['price_list_id'],
            'price_list_rule_id' => $price['price_list_rule_id'],
        ], $lines, $prices));

        [$totals, $rows] = $this->totals($workspace, $rows, $discount, $rate);
        $rounding        = $this->rounding($totals['grand_total'], $step);

        if (! Decimal::isZero($rounding)) {
            $rows[] = [...$this->salesLines->rows($workspace, [['name' => self::ROUNDING, 'quantity' => '1', 'unit_price' => $rounding]])[0], 'position' => count($rows) + 1];

            [$totals, $rows] = $this->totals($workspace, $rows, $discount, $rate, count($rows) - 1);
        }

        return ['rows' => $rows, 'prices' => $prices, 'totals' => $totals, 'rounding' => $rounding];
    }

    /**
     * $priced (priced()'s) as the screen shows the cart: each line's price,
     * the price an override replaced and where its price came from, its
     * discount and amount, and the subtotal, the order's discount, the taxes
     * and the total.
     *
     * @return array{lines: list<array>, subtotal: string, discount_total: string, grand_total: string, taxes: list<array>}
     */
    public function shown(array $priced): array
    {
        ['rows' => $rows, 'prices' => $prices, 'totals' => $totals] = $priced;

        return [
            'lines'          => array_map(fn (array $row, array $price) => [
                'item_id'    => $row['item_id'],
                'price'      => $price['price'],
                'standard'   => $price['standard'],
                'source'     => $price['source'],
                'price_list' => $price['price_list'],
                'discount'   => $row['discount_amount'],
                'amount'     => $row['amount'],
            ], $rows, $prices),
            'subtotal'       => $totals['subtotal'],
            'discount_total' => $totals['discount_total'],
            'grand_total'    => $totals['grand_total'],
            'taxes'          => $totals['taxes'],
        ];
    }

    /** How much a total of $total rounds by to $step - to the nearest, half up - for a cash sale; nil without a step. */
    public function rounding(string $total, ?string $step): string
    {
        if ($step === null || bccomp($step, '0', 2) <= 0) {
            return '0.00';
        }

        return bcsub(bcmul(Decimal::round(bcdiv($total, $step, 10), 0), $step, 2), $total, 2);
    }

    /**
     * $rows totalled by SalesCalculator, after $discount - the order's - each
     * row with its amounts; the row at $roundingKey a deduction: no share of
     * the discount, no tax.
     *
     * @return array{0: array, 1: list<array>}
     */
    private function totals(Workspace $workspace, array $rows, ?array $discount, string $rate, ?int $roundingKey = null): array
    {
        $lines = array_map(fn (array $row) => $this->salesLines->forCalculation($row), $rows);

        if ($roundingKey !== null) {
            $lines[$roundingKey] = [...$lines[$roundingKey], 'deduction' => true, 'tax' => null];
        }

        $result = $this->calculator->calculate($lines, $discount['type'] ?? null, $discount['value'] ?? null, $workspace->price_tax_mode, $workspace->tax_rounding, $rate);

        foreach ($rows as $key => $row) {
            $rows[$key] = [...$row, ...$result['lines'][$key]];
        }

        return [Arr::only($result, self::TOTALS), $rows];
    }

    /**
     * Each of $lines' prices, in order: a price typed over it (Phase C3),
     * else the customer's own price list's, else the register's, else the
     * item's own - with the price it would have had (its standard).
     *
     * @return list<array{price: ?string, standard: ?string, source: string, price_list: ?string, price_list_id: ?int, price_list_rule_id: ?int}>
     */
    private function prices(Workspace $workspace, PosRegister $register, ?Contact $contact, array $lines, string $rate, string $date): array
    {
        $full    = WorkspaceApps::for($workspace)->has('sales', Edition::Full);
        $own     = $full && $contact
            ? $this->priceLists->eligible($workspace, $register->currency, $date, $contact->id, $register->location_id)->reject(fn (PriceList $list) => $list->all_customers)->values()
            : collect();
        $its     = $full ? $this->priceLists->fallback($workspace, $register->price_list_id, $register->currency, $date) : null;
        $context = ['currency' => $register->currency, 'date' => $date, 'exchange_rate' => $rate];
        $asked   = array_map(fn (array $line) => ['item_id' => $line['item']->id, 'quantity' => $line['quantity']], $lines);
        $byOwn   = $own->isEmpty() ? [] : $this->listPricing->quoteFrom($workspace, $own, $context, $asked);
        $byList  = $its ? $this->listPricing->quoteFrom($workspace, collect([$its]), $context, array_diff_key($asked, $byOwn)) : [];

        return array_map(function (array $line, int $key) use ($byOwn, $byList, $rate): array {
            $quoted = $byOwn[$key] ?? $byList[$key] ?? null;
            $base   = $quoted ? null : $this->itemPrice($line['item']);
            $price  = $quoted
                ? ['price' => $quoted['unit_price'], 'source' => isset($byOwn[$key]) ? self::CUSTOMER_PRICE_LIST : self::PRICE_LIST, 'price_list' => $quoted['price_list'], 'price_list_id' => $quoted['price_list_id'], 'price_list_rule_id' => $quoted['price_list_rule_id']]
                : ['price' => $base === null ? null : Decimal::round(bcdiv($base, $rate, 10)), 'source' => self::ITEM, 'price_list' => null, 'price_list_id' => null, 'price_list_rule_id' => null];
            $price['standard'] = $price['price'];

            // A price typed over it: its own, no list's.
            if (($line['price'] ?? null) !== null) {
                $price = [...$price, 'price' => $line['price'], 'source' => PosOrderLine::OVERRIDE, 'price_list' => null, 'price_list_id' => null, 'price_list_rule_id' => null];
            }

            return $price;
        }, $lines, array_keys($lines));
    }

    /** The item's own sales price, in the base currency: a combo priced as its components' sum, theirs. Null for none. */
    private function itemPrice(Item $item): ?string
    {
        if ($item->isCombo() && $item->combo_pricing === 'sum') {
            return $item->componentTotal();
        }

        return $item->sale_price === null ? null : (string) $item->sale_price;
    }

    /** What gives the tax rate a line of an item takes for $contact on $date: none while taxes are off. */
    private function taxFor(Workspace $workspace, ?Contact $contact, string $date): Closure
    {
        if (! $workspace->usesTaxes()) {
            return fn (Item $item) => null;
        }

        $treatment = $contact?->salesTaxTreatment();

        if ($treatment) {
            $id = $this->taxRates->forSalesWithTreatment($workspace, $treatment, $date)?->id;

            return fn (Item $item) => $id;
        }

        $usable  = $this->taxRates->getFor($workspace)->filter(fn (TaxRate $rate) => $this->usable($rate, $date))->pluck('id')->map(fn ($id) => (int) $id)->all();
        $default = $this->taxRates->defaultFor($workspace, TaxRate::SALES);
        $none    = $default && in_array((int) $default->id, $usable, true) ? (int) $default->id : null;

        return fn (Item $item) => $item->sales_tax_id !== null && in_array($item->sales_tax_id, $usable, true) ? $item->sales_tax_id : $none;
    }

    /** Whether a sale on $date can take $rate: active, for sales, and in effect. */
    private function usable(TaxRate $rate, string $date): bool
    {
        return $rate->is_active && $rate->for_sales && $rate->effective_from <= $date && (! $rate->effective_to || $rate->effective_to >= $date);
    }
}
