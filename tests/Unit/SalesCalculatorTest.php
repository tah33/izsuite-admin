<?php

namespace Tests\Unit;

use App\Services\Sales\SalesCalculator;
use PHPUnit\Framework\TestCase;

/**
 * A sales document's totals (Doc 08 section 5, Doc 14 section 14.8): every
 * figure here is worked out by hand, to the cent.
 */
class SalesCalculatorTest extends TestCase
{
    private const VAT = ['id' => 1, 'name' => 'VAT', 'code' => 'VAT15', 'rate' => '15.0000', 'treatment' => 'standard'];

    private function line(string $quantity, string $price, array $more = []): array
    {
        return ['quantity' => $quantity, 'unit_price' => $price, 'tax' => self::VAT, ...$more];
    }

    private function totals(array $result): array
    {
        return array_intersect_key($result, array_flip(['subtotal', 'discount_total', 'tax_total', 'grand_total', 'net_total']));
    }

    public function test_tax_is_added_on_top_and_rounded_on_each_line(): void
    {
        $result = (new SalesCalculator)->calculate([
            $this->line('2', '10.00', ['discount_type' => 'percent', 'discount_value' => '10']),
            $this->line('1', '9.99'),
        ], null, null, 'exclusive', 'line');

        $this->assertSame([
            ['discount_amount' => '2.00', 'amount' => '18.00', 'tax_amount' => '2.70'],
            ['discount_amount' => '0.00', 'amount' => '9.99', 'tax_amount' => '1.50'],
        ], $result['lines']);

        $this->assertSame(['subtotal' => '27.99', 'discount_total' => '0.00', 'tax_total' => '4.20', 'grand_total' => '32.19', 'net_total' => '27.99'], $this->totals($result));
        $this->assertSame([['tax_rate_id' => 1, 'name' => 'VAT', 'code' => 'VAT15', 'rate' => '15.0000', 'treatment' => 'standard', 'taxable_amount' => '27.99', 'tax_amount' => '4.20']], $result['taxes']);
    }

    public function test_the_document_discount_is_shared_out_before_tax_and_the_base_currency_follows_the_rate(): void
    {
        $result = (new SalesCalculator)->calculate([
            $this->line('2', '10.00', ['discount_type' => 'percent', 'discount_value' => '10']),
            $this->line('1', '9.99'),
        ], 'amount', '10', 'exclusive', 'line', '121.5');

        // 6.43 and 3.57 of the 10.00 come off the lines, leaving 11.57 and 6.42 to tax.
        $this->assertSame(['1.74', '0.96'], array_column($result['lines'], 'tax_amount'));
        $this->assertSame(['subtotal' => '27.99', 'discount_total' => '10.00', 'tax_total' => '2.70', 'grand_total' => '20.69', 'net_total' => '17.99'], $this->totals($result));
        $this->assertSame('17.99', $result['taxes'][0]['taxable_amount']);

        $this->assertSame(['2513.84', '328.05', '2185.79'], [$result['base_grand_total'], $result['base_tax_total'], $result['base_net_total']]);
    }

    public function test_prices_that_include_tax_have_it_worked_out_of_them(): void
    {
        $result = (new SalesCalculator)->calculate([$this->line('1', '115.00')], null, null, 'inclusive', 'line');

        $this->assertSame(['subtotal' => '115.00', 'discount_total' => '0.00', 'tax_total' => '15.00', 'grand_total' => '115.00', 'net_total' => '100.00'], $this->totals($result));
        $this->assertSame('100.00', $result['taxes'][0]['taxable_amount']);
    }

    public function test_rounding_once_on_the_total_can_differ_from_rounding_each_line(): void
    {
        $lines = [$this->line('1', '0.10'), $this->line('1', '0.10'), $this->line('1', '0.10')];

        $byLine  = (new SalesCalculator)->calculate($lines, null, null, 'exclusive', 'line');
        $byTotal = (new SalesCalculator)->calculate($lines, null, null, 'exclusive', 'total');

        $this->assertSame(['0.06', '0.36'], [$byLine['tax_total'], $byLine['grand_total']]);
        $this->assertSame(['0.05', '0.35'], [$byTotal['tax_total'], $byTotal['grand_total']]);
        $this->assertSame(['0.02', '0.02', '0.02'], array_column($byTotal['lines'], 'tax_amount'));
    }

    public function test_no_discount_takes_a_total_below_zero(): void
    {
        $result = (new SalesCalculator)->calculate([
            ['quantity' => '1', 'unit_price' => '10.00', 'discount_type' => 'percent', 'discount_value' => '150'],
            ['quantity' => '1', 'unit_price' => '5.00'],
        ], 'amount', '50', 'exclusive', 'line');

        $this->assertSame(['10.00', '0.00'], array_column($result['lines'], 'discount_amount'));
        $this->assertSame(['subtotal' => '5.00', 'discount_total' => '5.00', 'tax_total' => '0.00', 'grand_total' => '0.00', 'net_total' => '0.00'], $this->totals($result));
        $this->assertSame([], $result['taxes']);
    }

    public function test_a_zero_rated_line_is_kept_in_the_breakdown_with_no_tax(): void
    {
        $exports = ['id' => 2, 'name' => 'Exports', 'code' => 'ZR', 'rate' => '0.0000', 'treatment' => 'zero_rated'];

        $result = (new SalesCalculator)->calculate([$this->line('3', '50.00', ['tax' => $exports])], null, null, 'exclusive', 'line');

        $this->assertSame('150.00', $result['grand_total']);
        $this->assertSame([['tax_rate_id' => 2, 'name' => 'Exports', 'code' => 'ZR', 'rate' => '0.0000', 'treatment' => 'zero_rated', 'taxable_amount' => '150.00', 'tax_amount' => '0.00']], $result['taxes']);
    }
}
