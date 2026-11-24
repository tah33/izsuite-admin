<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderLine;
use App\Repositories\User\EcommerceOrderRepository;
use App\Services\Api\Sales\SalesLines;
use App\Services\Sales\SalesCalculator;

/**
 * An online order's totals worked out again after it is edited (Doc 19
 * section 19.21 - Phase F5), as checkout worked them out: each line's amount
 * and tax from its quantity, price and tax as the order keeps them - at the
 * order's price-tax mode, rounding and rate (SalesCalculator) - shown as the
 * store showed prices when it was placed; then its products, shipping, tax
 * and total.
 */
class EcommerceOrderTotals
{
    public function __construct(
        protected EcommerceOrderRepository $orders,
        protected SalesLines $salesLines,
        protected SalesCalculator $calculator,
        protected StorefrontPricing $pricing,
    ) {}

    /** $order's lines - read again - and totals, saved. */
    public function recalculate(EcommerceOrder $order): void
    {
        $lines  = $this->orders->forConfirming($order, false)->lines->values();
        $result = $this->calculator->calculate($lines->map(fn (EcommerceOrderLine $line) => $this->salesLines->forCalculation([
            'quantity'      => (string) $line->quantity,
            'unit_price'    => (string) $line->unit_price,
            'tax_rate_id'   => $line->tax_rate_id,
            'tax_name'      => $line->tax_name,
            'tax_code'      => $line->tax_code,
            'tax_rate'      => $line->tax_rate === null ? null : (string) $line->tax_rate,
            'tax_treatment' => $line->tax_treatment,
        ]))->all(), null, null, $order->price_tax_mode, $order->tax_rounding, (string) $order->exchange_rate);

        // Shown as the store showed prices when it was placed.
        $shown    = ['with_tax' => $order->prices_include_tax, 'inclusive' => $order->price_tax_mode === 'inclusive'];
        $products = '0.00';
        $shipping = '0.00';

        foreach ($lines as $at => $line) {
            $amount = $this->pricing->shownAmount($result['lines'][$at], $shown);
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
            'discount_total'   => $result['discount_total'],
            'tax_total'        => $result['tax_total'],
            'grand_total'      => $result['grand_total'],
            'net_total'        => $result['net_total'],
            'base_grand_total' => $result['base_grand_total'],
            'taxes'            => $result['taxes'],
        ]);

        $order->unsetRelation('lines');
    }
}
