<?php

namespace App\Services\Api\Ecommerce;

use App\Enums\Edition;
use App\Models\User\CatalogProduct;
use App\Models\User\CatalogProductVariant;
use App\Models\User\EcommerceSetting;
use App\Models\User\Item;
use App\Models\User\Storefront;
use App\Models\User\TaxRate;
use App\Models\User\Workspace;
use App\Repositories\User\EcommerceSettingRepository;
use App\Repositories\User\PriceListRepository;
use App\Repositories\User\TaxRateRepository;
use App\Services\Api\BusinessSetup\ExchangeRateService;
use App\Services\Api\Sales\PriceListPricing;
use App\Services\User\Apps\WorkspaceApps;

/**
 * What a storefront sells its products at (Doc 19 section 19.13 - Phases C1
 * and D3): with full Sales, its own price list's price - with the list's
 * quantity breaks - and, before it, a signed-in customer's own lists (Phase
 * E3: those naming their contact, by priority); else each item's sales
 * price, a combo priced as its components' sum theirs, in the storefront's
 * currency at the day's rate (in the base currency while the business has no
 * rate for it).
 *
 * Shown, a price has its tax added or taken out as Settings → eCommerce shows
 * prices; sold (the cart, Phase D3), it is kept as the business keeps prices,
 * with or without tax, its tax on its own line.
 *
 * Its tax is the one a sale of it takes for no customer in particular, as
 * POS picks it: none while taxes are off; else the item's own sales tax while
 * it can be used, else the default one. Promotions come with Phase H.
 */
class StorefrontPricing
{
    public const ITEM = 'item';

    public const PRICE_LIST = 'price_list';

    public function __construct(
        protected EcommerceSettingRepository $settings,
        protected TaxRateRepository $taxRates,
        protected ExchangeRateService $exchangeRates,
        protected PriceListRepository $priceLists,
        protected PriceListPricing $listPricing,
        protected StoreVisitor $visitor,
    ) {}

    /**
     * How $storefront prices on $date: its currency and that currency's rate
     * (the base currency per unit), whether its prices are shown with tax -
     * null while the business has taxes off - the rate of each tax a price
     * may take, by id, with the default one, and (Phase D3) the price lists it
     * sells at, in their order - with full Sales, each while it is active, in
     * its currency and within its days: the signed-in customer's own (Phase
     * E3), for the store's location, then the store's.
     *
     * @return array{currency: string, rate: string, with_tax: ?bool, inclusive: bool, rates: array<int, string>, default: ?int, date: string, lists: list<\App\Models\User\PriceList>}
     */
    public function terms(Workspace $workspace, Storefront $storefront, string $date): array
    {
        $rate     = $storefront->currency ? $this->exchangeRates->rateOn($workspace, $storefront->currency, $date) : null;
        $currency = $rate !== null && bccomp($rate, '0', 10) > 0 ? $storefront->currency : $workspace->base_currency;
        $taxed    = $workspace->usesTaxes();
        $usable   = $taxed ? $this->taxRates->getFor($workspace)->filter(fn (TaxRate $tax) => $this->usable($tax, $date)) : collect();
        $default  = $taxed ? $this->taxRates->defaultFor($workspace, TaxRate::SALES) : null;
        $full     = WorkspaceApps::for($workspace)->has('sales', Edition::Full);
        $store    = $full && $storefront->price_list_id !== null ? $this->priceLists->fallback($workspace, $storefront->price_list_id, $currency, $date) : null;
        $contact  = $full ? $this->visitor->customer()?->contact_id : null;
        $own      = $contact ? $this->priceLists->eligible($workspace, $currency, $date, $contact, $storefront->location_id)->where('all_customers', false) : collect();

        return [
            'currency'  => $currency,
            'rate'      => $currency === $workspace->base_currency ? '1' : (string) $rate,
            'with_tax'  => $taxed ? $this->settings->forWorkspace($workspace)->price_display === EcommerceSetting::WITH_TAX : null,
            'inclusive' => $workspace->price_tax_mode === 'inclusive',
            'rates'     => $usable->mapWithKeys(fn (TaxRate $tax) => [(int) $tax->id => (string) $tax->rate])->all(),
            'default'   => $default && $usable->contains('id', $default->id) ? (int) $default->id : null,
            'date'      => $date,
            'lists'     => $own->push($store)->filter()->unique('id')->values()->all(),
        ];
    }

    /** $quantity of $item's price as $terms show it, to the cent - null when it has none. A combo's components are to be loaded. */
    public function priceOf(Item $item, array $terms, string $quantity = '1'): ?string
    {
        $listed = $this->listed($item, $terms, $quantity);
        $price  = $listed['unit_price'] ?? $this->ownPrice($item);

        if ($price === null) {
            return null;
        }

        $price = (string) $price;
        $tax   = $this->taxRateOf($item, $terms);

        if ($tax !== null && $terms['with_tax'] !== null && $terms['with_tax'] !== $terms['inclusive']) {
            $factor = bcadd('1', bcdiv($tax, '100', 10), 10);
            $price  = $terms['with_tax'] ? bcmul($price, $factor, 10) : bcdiv($price, $factor, 10);
        }

        // A list's price is in the storefront's currency already.
        if ($listed === null && $terms['rate'] !== '1') {
            $price = bcdiv($price, $terms['rate'], 10);
        }

        return $this->cents($price);
    }

    /**
     * Phase D3: $quantity of $item's price as it is sold - as the business
     * keeps prices, with or without tax, in the storefront's currency, to the
     * cent - and where it came from: the storefront's price list (with its
     * rule) or the item. Null price when it has none.
     *
     * @return array{price: ?string, source: string, price_list_id: ?int, price_list_rule_id: ?int}
     */
    public function unitPrice(Item $item, array $terms, string $quantity): array
    {
        if ($listed = $this->listed($item, $terms, $quantity)) {
            return ['price' => $listed['unit_price'], 'source' => self::PRICE_LIST, 'price_list_id' => $listed['list']->id, 'price_list_rule_id' => $listed['price_list_rule_id']];
        }

        $own = $this->ownPrice($item);

        return [
            'price'              => $own === null ? null : $this->cents($terms['rate'] === '1' ? (string) $own : bcdiv((string) $own, $terms['rate'], 10)),
            'source'             => self::ITEM,
            'price_list_id'      => null,
            'price_list_rule_id' => null,
        ];
    }

    /**
     * Phase D3: a calculated line's amount (SalesCalculator's) as $terms show
     * prices - its tax put in, or taken out, when the store shows them
     * otherwise than the business keeps them.
     */
    public function shownAmount(array $calculated, array $terms): string
    {
        if ($terms['with_tax'] === null || $terms['with_tax'] === $terms['inclusive']) {
            return $calculated['amount'];
        }

        return $terms['with_tax'] ? bcadd($calculated['amount'], $calculated['tax_amount'], 2) : bcsub($calculated['amount'], $calculated['tax_amount'], 2);
    }

    /**
     * What an order's discount takes off as $terms show prices, from its
     * totals without it ($plain) and with it ($result) - SalesCalculator's: as
     * the business keeps prices, or, shown otherwise, with its tax put in (the
     * total it takes off) or taken out (the total before tax it takes off).
     * An order's discount edited (Phase F5b) and a code at checkout (Phase H1).
     */
    public function shownDiscount(array $plain, array $result, array $terms): string
    {
        return match (true) {
            $terms['with_tax'] === null, $terms['with_tax'] === $terms['inclusive'] => $result['discount_total'],
            $terms['with_tax']                                                     => bcsub($plain['grand_total'], $result['grand_total'], 2),
            default                                                                => bcsub($plain['net_total'], $result['net_total'], 2),
        };
    }

    /**
     * Phase D4: $price, kept in the base currency as the business keeps
     * prices, with the tax $taxId, as $terms show prices - its tax in or out,
     * in the store's currency, to the cent.
     */
    public function shownBase(string $price, ?int $taxId, array $terms): string
    {
        $tax = $taxId !== null ? ($terms['rates'][$taxId] ?? null) : null;

        if ($tax !== null && $terms['with_tax'] !== null && $terms['with_tax'] !== $terms['inclusive']) {
            $factor = bcadd('1', bcdiv($tax, '100', 10), 10);
            $price  = $terms['with_tax'] ? bcmul($price, $factor, 10) : bcdiv($price, $factor, 10);
        }

        return $this->inCurrency($price, $terms);
    }

    /** Phase D4: $price in the base currency, in the store's, to the cent. */
    public function inCurrency(string $price, array $terms): string
    {
        return $this->cents($terms['rate'] === '1' ? $price : bcdiv($price, $terms['rate'], 10));
    }

    /** Phase D3: the tax rate $item's sale takes for no customer in particular, by id - null for none. */
    public function taxIdOf(Item $item, array $terms): ?int
    {
        if (isset($terms['rates'][$item->sales_tax_id ?? 0])) {
            return (int) $item->sales_tax_id;
        }

        return $terms['default'] !== null && isset($terms['rates'][$terms['default']]) ? $terms['default'] : null;
    }

    /**
     * A listing's lowest price as $terms show it - its own item's, or with
     * variants (Phase B3) those shown - and whether its prices differ. Its
     * variants and their items are to be loaded.
     *
     * @return array{price: ?string, varies: bool}
     */
    public function fromPrice(CatalogProduct $product, array $terms): array
    {
        $prices = ($product->variants->isNotEmpty()
            ? $product->variants->map(fn (CatalogProductVariant $variant) => $this->priceOf($variant->item, $terms))
            : collect([$this->priceOf($product->item, $terms)]))
            ->filter(fn (?string $price) => $price !== null)
            ->values();

        return [
            'price'  => $prices->reduce(fn (?string $lowest, string $price) => $lowest === null || bccomp($price, $lowest, 2) < 0 ? $price : $lowest),
            'varies' => $prices->unique()->count() > 1,
        ];
    }

    /** The first of $terms' lists that prices $quantity of $item: its price in its currency, its rule and the list - null when none does. */
    private function listed(Item $item, array $terms, string $quantity): ?array
    {
        foreach ($terms['lists'] ?? [] as $list) {
            if ($price = $this->listPricing->listPrice($list, $item, $quantity, $terms['date'], $terms['rate'])) {
                return [...$price, 'list' => $list];
            }
        }

        return null;
    }

    /** The item's own sales price, in the base currency: a combo priced as its components' sum, theirs. */
    private function ownPrice(Item $item): mixed
    {
        return $item->isCombo() && $item->combo_pricing === 'sum' ? $item->componentTotal() : $item->sale_price;
    }

    /** The rate of $item's tax, as a percentage - null for none. */
    private function taxRateOf(Item $item, array $terms): ?string
    {
        $id = $this->taxIdOf($item, $terms);

        return $id === null ? null : $terms['rates'][$id];
    }

    /** $value to the cent, half up. */
    private function cents(string $value): string
    {
        return bcadd($value, bccomp($value, '0', 10) < 0 ? '-0.005' : '0.005', 2);
    }

    /** Whether a sale on $date can take $tax: active, for sales, and in effect. */
    private function usable(TaxRate $tax, string $date): bool
    {
        return $tax->is_active && $tax->for_sales && $tax->effective_from <= $date && (! $tax->effective_to || $tax->effective_to >= $date);
    }
}
