<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderLine;
use App\Repositories\User\EcommerceOrderRepository;
use App\Services\Api\Sales\SalesLines;
use App\Services\Sales\SalesCalculator;
use App\Support\Decimal;

/**
 * An online order's totals worked out again after it is edited (Doc 19
 * section 19.21 - Phase F5), as checkout worked them out: each line's amount
 * and tax from its quantity, price and tax as the order keeps them - at the
 * order's price-tax mode, rounding and rate (SalesCalculator) - shown as the
 * store showed prices when it was placed; then its products, shipping,
 * discount, tax and total.
 *
 * Its discount (Phase F5b) is a percentage of its products, or an amount no
 * more than they come to - discountOff() - taken off the order as Sales takes
 * an invoice's: an amount shared over its lines, so the order and its invoice
 * total alike.
 */
class EcommerceOrderTotals
{
    public function __construct(
        protected EcommerceOrderRepository $orders,
        protected SalesLines $salesLines,
        protected SalesCalculator $calculator,
        protected StorefrontPricing $pricing,
    ) {}

    /**
     * $order's lines - read again - and totals, saved. Each line - and so its
     * products and shipping - is shown as it is before any discount, so a
     * discount never changes what a line shows; the discount is shown as the
     * store shows prices, with or without its tax, so what the order shows
     * adds up to its total.
     */
    public function recalculate(EcommerceOrder $order): void
    {
        $order = $this->orders->forConfirming($order, false);
        $lines = $order->lines->values();
        $rows  = $lines->map(fn (EcommerceOrderLine $line) => $this->salesLines->forCalculation([
            'quantity'      => (string) $line->quantity,
            'unit_price'    => (string) $line->unit_price,
            'tax_rate_id'   => $line->tax_rate_id,
            'tax_name'      => $line->tax_name,
            'tax_code'      => $line->tax_code,
            'tax_rate'      => $line->tax_rate === null ? null : (string) $line->tax_rate,
            'tax_treatment' => $line->tax_treatment,
        ]))->all();
        $off    = $this->discountOff($order);
        $plain  = $this->calculator->calculate($rows, null, null, $order->price_tax_mode, $order->tax_rounding, (string) $order->exchange_rate);
        $result = $off === null ? $plain : $this->calculator->calculate($rows, EcommerceOrder::AMOUNT, $off, $order->price_tax_mode, $order->tax_rounding, (string) $order->exchange_rate);

        // Shown as the store showed prices when it was placed.
        $shown    = ['with_tax' => $order->prices_include_tax, 'inclusive' => $order->price_tax_mode === 'inclusive'];
        $products = '0.00';
        $shipping = '0.00';

        foreach ($lines as $at => $line) {
            $amount = $this->pricing->shownAmount($plain['lines'][$at], $shown);
            $data   = ['amount' => $result['lines'][$at]['amount'], 'tax_amount' => $result['lines'][$at]['tax_amount'], 'shown_amount' => $amount];

            if ($line->kind === EcommerceOrderLine::SHIPPING) {
                $data['shown_unit_price'] = $amount;
                $shipping                 = bcadd($shipping, $amount, 2);
            } else {
                $products = bcadd($products, $amount, 2);
            }

            $this->orders->updateLine($line, $data);
        }

        $this->orders->update($order, [
            'subtotal'         => $products,
            'shipping_total'   => $shipping,
            'discount_total'   => $this->pricing->shownDiscount($plain, $result, $shown),
            'tax_total'        => $result['tax_total'],
            'grand_total'      => $result['grand_total'],
            'net_total'        => $result['net_total'],
            'base_grand_total' => $result['base_grand_total'],
            'taxes'            => $result['taxes'],
        ]);

        $order->unsetRelation('lines');
    }

    /**
     * What $order's products come to before any discount - each line's
     * quantity by its price, to the cent, as SalesCalculator works it out: as
     * the business keeps prices, in the order's currency. Its lines are to be
     * loaded.
     */
    public function productsBase(EcommerceOrder $order): string
    {
        return Decimal::sum($order->lines->where('kind', EcommerceOrderLine::PRODUCT)
            ->map(fn (EcommerceOrderLine $line) => Decimal::round(bcmul((string) $line->quantity, (string) $line->unit_price, 10))));
    }

    /**
     * Phase F5b: what $order's discount takes off - a percentage of its
     * products, to the cent, or its amount, never more than they come to -
     * null without one. Its lines are to be loaded.
     */
    public function discountOff(EcommerceOrder $order): ?string
    {
        if ($order->discount_type === null || Decimal::isZero((string) $order->discount_value)) {
            return null;
        }

        $base = $this->productsBase($order);
        $off  = $order->discount_type === EcommerceOrder::PERCENT
            ? Decimal::round(bcdiv(bcmul($base, (string) $order->discount_value, 10), '100', 10))
            : Decimal::round((string) $order->discount_value);

        return Decimal::min($off, $base);
    }
}
