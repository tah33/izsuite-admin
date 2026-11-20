<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\CatalogProduct;
use App\Models\User\CatalogProductVariant;
use App\Models\User\EcommerceSetting;
use App\Models\User\Item;
use App\Models\User\Storefront;
use App\Models\User\TaxRate;
use App\Models\User\Workspace;
use App\Repositories\User\EcommerceSettingRepository;
use App\Repositories\User\TaxRateRepository;
use App\Services\Api\BusinessSetup\ExchangeRateService;

/**
 * What a storefront shows its products at (Doc 19 section 19.13 - Phase C1),
 * before a customer, a cart or a promotion comes into it: each item's sales
 * price - a combo priced as its components' sum, theirs - with its tax added
 * or taken out as Settings → eCommerce shows prices, and in the storefront's
 * currency at the day's rate (in the base currency while the business has no
 * rate for it).
 *
 * Its tax is the one a sale of it takes for no customer in particular, as
 * POS picks it: none while taxes are off; else the item's own sales tax while
 * it can be used, else the default one. Price lists and promotions come with
 * the cart (Phases D and H).
 */
class StorefrontPricing
{
    public function __construct(
        protected EcommerceSettingRepository $settings,
        protected TaxRateRepository $taxRates,
        protected ExchangeRateService $exchangeRates,
    ) {}

    /**
     * How $storefront shows prices on $date: its currency and that
     * currency's rate (the base currency per unit), whether its prices include
     * tax - null while the business has taxes off - and the rate of each
     * tax a price may take, by id, with the default one.
     *
     * @return array{currency: string, rate: string, with_tax: ?bool, inclusive: bool, rates: array<int, string>, default: ?int}
     */
    public function terms(Workspace $workspace, Storefront $storefront, string $date): array
    {
        $rate     = $storefront->currency ? $this->exchangeRates->rateOn($workspace, $storefront->currency, $date) : null;
        $currency = $rate !== null && bccomp($rate, '0', 10) > 0 ? $storefront->currency : $workspace->base_currency;
        $taxed    = $workspace->usesTaxes();
        $usable   = $taxed ? $this->taxRates->getFor($workspace)->filter(fn (TaxRate $tax) => $this->usable($tax, $date)) : collect();
        $default  = $taxed ? $this->taxRates->defaultFor($workspace, TaxRate::SALES) : null;

        return [
            'currency'  => $currency,
            'rate'      => $currency === $workspace->base_currency ? '1' : (string) $rate,
            'with_tax'  => $taxed ? $this->settings->forWorkspace($workspace)->price_display === EcommerceSetting::WITH_TAX : null,
            'inclusive' => $workspace->price_tax_mode === 'inclusive',
            'rates'     => $usable->mapWithKeys(fn (TaxRate $tax) => [(int) $tax->id => (string) $tax->rate])->all(),
            'default'   => $default && $usable->contains('id', $default->id) ? (int) $default->id : null,
        ];
    }

    /** $item's price as $terms show it, to the cent - null when it has none. A combo's components are to be loaded. */
    public function priceOf(Item $item, array $terms): ?string
    {
        $price = $item->isCombo() && $item->combo_pricing === 'sum' ? $item->componentTotal() : $item->sale_price;

        if ($price === null) {
            return null;
        }

        $price = (string) $price;
        $tax   = $terms['rates'][$item->sales_tax_id ?? 0] ?? ($terms['default'] !== null ? $terms['rates'][$terms['default']] ?? null : null);

        if ($tax !== null && $terms['with_tax'] !== null && $terms['with_tax'] !== $terms['inclusive']) {
            $factor = bcadd('1', bcdiv($tax, '100', 10), 10);
            $price  = $terms['with_tax'] ? bcmul($price, $factor, 10) : bcdiv($price, $factor, 10);
        }

        if ($terms['rate'] !== '1') {
            $price = bcdiv($price, $terms['rate'], 10);
        }

        return $this->cents($price);
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
