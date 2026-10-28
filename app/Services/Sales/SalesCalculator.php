<?php

namespace App\Services\Sales;

use App\Support\Decimal;

/**
 * The one calculation of a sales document's totals (Doc 08 section 5, Doc 14
 * section 14.8), used by the form's preview and by every save, so a document
 * shows the same totals everywhere. Exact decimals, rounded half up to the
 * cent where money is shown.
 *
 *   1. Each line: quantity × unit price, to the cent, less its discount.
 *   2. The subtotal: the lines added up.
 *   3. The document discount, shared out over the lines by their amounts.
 *   4. Tax on what is left of each line - added on top when prices exclude
 *      tax, worked out of the price when they include it - rounded on each
 *      line, or once per rate for the whole document.
 *   5. The grand total, and the net total without tax.
 *   6. The same totals in the base currency, at the exchange rate.
 *
 * A discount never goes past what it is taken from, so no total is negative.
 */
final class SalesCalculator
{
    private const SCALE = 10;

    /**
     * @param  array<int|string, array{quantity: string, unit_price: string, discount_type?: ?string, discount_value?: ?string, tax?: ?array{id: ?int, name: ?string, code: ?string, rate: string, treatment: ?string}}>  $lines
     */
    public function calculate(array $lines, ?string $discountType, ?string $discountValue, string $priceTaxMode, string $taxRounding, string $exchangeRate = '1'): array
    {
        $rows = [];

        foreach ($lines as $key => $line) {
            $gross    = Decimal::round(bcmul((string) $line['quantity'], (string) $line['unit_price'], self::SCALE));
            $discount = $this->discount($gross, $line['discount_type'] ?? null, $line['discount_value'] ?? null);

            $rows[$key] = ['discount_amount' => $discount, 'amount' => bcsub($gross, $discount, 2), 'tax_amount' => '0.00'];
        }

        $subtotal      = Decimal::sum(array_column($rows, 'amount'));
        $discountTotal = $this->discount($subtotal, $discountType, $discountValue);
        $shares        = $this->share($discountTotal, array_map(fn (array $row) => $row['amount'], $rows), $subtotal);

        $groups = [];

        foreach ($lines as $key => $line) {
            $tax = $line['tax'] ?? null;

            if (! $tax) {
                continue;
            }

            $taxable = bcsub($rows[$key]['amount'], $shares[$key], 2);
            $raw     = $priceTaxMode === 'inclusive'
                ? bcdiv(bcmul($taxable, $tax['rate'], self::SCALE), bcadd('100', $tax['rate'], self::SCALE), self::SCALE)
                : bcdiv(bcmul($taxable, $tax['rate'], self::SCALE), '100', self::SCALE);

            $rows[$key]['tax_amount'] = Decimal::round($raw);

            $group = $this->groupKey($tax);

            $groups[$group] ??= ['tax' => $tax, 'taxable' => '0', 'raw' => '0', 'rounded' => '0'];
            $groups[$group]['taxable'] = bcadd($groups[$group]['taxable'], $taxable, 2);
            $groups[$group]['raw']     = bcadd($groups[$group]['raw'], $raw, self::SCALE);
            $groups[$group]['rounded'] = bcadd($groups[$group]['rounded'], $rows[$key]['tax_amount'], 2);
        }

        $taxes = array_values(array_map(fn (array $group) => $this->breakdown($group, $priceTaxMode, $taxRounding), $groups));

        $taxTotal   = Decimal::sum(array_column($taxes, 'tax_amount'));
        $afterTotal = bcsub($subtotal, $discountTotal, 2);
        $grandTotal = $priceTaxMode === 'inclusive' ? $afterTotal : bcadd($afterTotal, $taxTotal, 2);

        $baseGrand = Decimal::round(bcmul($grandTotal, $exchangeRate, self::SCALE));
        $baseTax   = Decimal::round(bcmul($taxTotal, $exchangeRate, self::SCALE));

        return [
            'lines'            => $rows,
            'subtotal'         => $subtotal,
            'discount_total'   => $discountTotal,
            'tax_total'        => $taxTotal,
            'grand_total'      => $grandTotal,
            'net_total'        => bcsub($grandTotal, $taxTotal, 2),
            'base_grand_total' => $baseGrand,
            'base_tax_total'   => $baseTax,
            'base_net_total'   => bcsub($baseGrand, $baseTax, 2),
            'taxes'            => $taxes,
        ];
    }

    /** A discount off $base: a percentage of it, or an amount - never more than $base itself. */
    private function discount(string $base, ?string $type, ?string $value): string
    {
        if (! $type || Decimal::isZero($value)) {
            return '0.00';
        }

        $discount = $type === 'percent'
            ? Decimal::round(bcdiv(bcmul($base, (string) $value, self::SCALE), '100', self::SCALE))
            : Decimal::round((string) $value);

        return Decimal::min($discount, bcadd($base, '0', 2));
    }

    /**
     * The document discount shared out over the lines by their amounts, to
     * the cent. What rounding leaves over goes to the largest line, so the
     * shares always add up to the discount.
     */
    private function share(string $discount, array $amounts, string $subtotal): array
    {
        $shares = array_map(fn () => '0.00', $amounts);

        if (Decimal::isZero($discount) || Decimal::isZero($subtotal)) {
            return $shares;
        }

        foreach ($amounts as $key => $amount) {
            $shares[$key] = Decimal::round(bcdiv(bcmul($discount, $amount, self::SCALE), $subtotal, self::SCALE));
        }

        $largest          = array_search(max(array_map('floatval', $amounts)), array_map('floatval', $amounts), true);
        $shares[$largest] = bcadd($shares[$largest], bcsub($discount, Decimal::sum($shares), 2), 2);

        return $shares;
    }

    /** Lines share a group when they share a rate; a rate deleted since is told apart by what it was. */
    private function groupKey(array $tax): string
    {
        return $tax['id'] ? "id:{$tax['id']}" : implode('|', [$tax['name'], $tax['rate'], $tax['treatment']]);
    }

    /** One rate's line of the tax breakdown: what it was charged on, without tax, and the tax. */
    private function breakdown(array $group, string $priceTaxMode, string $taxRounding): array
    {
        $tax = $taxRounding === 'total' ? Decimal::round($group['raw']) : $group['rounded'];

        return [
            'tax_rate_id'    => $group['tax']['id'],
            'name'           => $group['tax']['name'],
            'code'           => $group['tax']['code'],
            'rate'           => $group['tax']['rate'],
            'treatment'      => $group['tax']['treatment'],
            'taxable_amount' => $priceTaxMode === 'inclusive' ? bcsub($group['taxable'], $tax, 2) : $group['taxable'],
            'tax_amount'     => $tax,
        ];
    }
}
