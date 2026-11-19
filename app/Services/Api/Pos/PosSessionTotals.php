<?php

namespace App\Services\Api\Pos;

use App\Models\User\PosSession;

/**
 * What a POS session has taken (Doc 18 sections 18.27 and 18.28), for its
 * page while it is open and for its Z report when it closes: its sales -
 * how many, their total and their taxes - the payments by method, the cash
 * taken in sales, the cash paid back in returns and refunds, the cash put in
 * and taken out, and the cash the drawer should hold: the opening float,
 * plus the cash sales and the cash put in, less the cash refunds and the
 * cash taken out.
 *
 * Phase B1 has no sales, payments, returns or cash movements yet. The phases
 * that bring them add them here (C and D the sales and payments, E the
 * returns and refunds, F the cash movements), so for now the cash expected
 * is the float alone.
 */
class PosSessionTotals
{
    private const NIL = '0.00';

    /**
     * @return array{sales_count: int, sales_total: string, tax_total: string, payments: list<array{method: string, amount: string}>,
     *     cash_sales: string, cash_refunds: string, cash_in: string, cash_out: string, expected_cash: string}
     */
    public function of(PosSession $session): array
    {
        $totals = [
            'sales_count'  => 0,
            'sales_total'  => self::NIL,
            'tax_total'    => self::NIL,
            'payments'     => [],
            'cash_sales'   => self::NIL,
            'cash_refunds' => self::NIL,
            'cash_in'      => self::NIL,
            'cash_out'     => self::NIL,
        ];

        $in  = bcadd(bcadd((string) $session->opening_float, $totals['cash_sales'], 2), $totals['cash_in'], 2);
        $out = bcadd($totals['cash_refunds'], $totals['cash_out'], 2);

        return [...$totals, 'expected_cash' => bcsub($in, $out, 2)];
    }
}
