<?php

namespace App\Services\Api\Pos;

use App\Models\User\PosOrder;
use App\Models\User\PosOrderPayment;
use App\Models\User\PosPaymentMethod;
use App\Models\User\PosSession;
use App\Repositories\User\PosCashMovementRepository;
use App\Repositories\User\PosOrderRepository;
use App\Repositories\User\PosReturnRepository;

/**
 * What a POS session has taken (Doc 18 sections 18.27 and 18.28), for its
 * page while it is open and for its Z report when it closes: its sales -
 * how many, their total and their taxes - the payments by method, those
 * waiting to be confirmed (Phase D2), the cash
 * taken in sales, the cash paid back in returns and refunds, the cash put in
 * and taken out, and the cash the drawer should hold: the opening float,
 * plus the cash sales and the cash put in, less the cash refunds and the
 * cash taken out.
 *
 * Phase C2 brings the sales and their payments: its completed orders, and
 * what each payment paid of its order - for cash, what was handed over less
 * the change. Phase E1 brings the cash its returns paid back, and Phase E3
 * what its exchanges paid back of the difference; Phase F1 the cash
 * movements that moved - put into its drawer, or taken out. Worked out in
 * decimal, so a total is to the cent on any database.
 */
class PosSessionTotals
{
    private const NIL = '0.00';

    public function __construct(
        protected PosOrderRepository $orders,
        protected PosReturnRepository $returns,
        protected PosCashMovementRepository $movements,
    ) {}

    /**
     * @return array{sales_count: int, sales_total: string, tax_total: string, payments: list<array{method: string, amount: string}>, pending: string,
     *     cash_sales: string, cash_refunds: string, cash_in: string, cash_out: string, expected_cash: string}
     */
    public function of(PosSession $session): array
    {
        $orders   = $this->orders->completedIn($session);
        $byMethod = [];
        $cash     = self::NIL;
        $pending  = self::NIL;

        foreach ($this->orders->paymentsIn($session) as $payment) {
            /** @var PosOrderPayment $payment */
            // Phase D2: a payment waiting to be confirmed is counted apart; one declined, not at all.
            if ($payment->status !== PosOrderPayment::COMPLETED) {
                $pending = $payment->isPending() ? bcadd($pending, (string) $payment->amount, 2) : $pending;

                continue;
            }

            $byMethod[$payment->method_name] = bcadd($byMethod[$payment->method_name] ?? self::NIL, (string) $payment->amount, 2);

            if ($payment->method_type === PosPaymentMethod::CASH) {
                $cash = bcadd($cash, (string) $payment->amount, 2);
            }
        }

        $moved  = $this->movements->totalsIn($session);
        $totals = [
            'sales_count'  => $orders->count(),
            'sales_total'  => $orders->reduce(fn (string $sum, PosOrder $order) => bcadd($sum, (string) $order->grand_total, 2), self::NIL),
            'tax_total'    => $orders->reduce(fn (string $sum, PosOrder $order) => bcadd($sum, (string) ($order->invoice?->tax_total ?? '0'), 2), self::NIL),
            'payments'     => array_map(fn ($method, string $amount) => ['method' => (string) $method, 'amount' => $amount], array_keys($byMethod), array_values($byMethod)),
            'pending'      => $pending,
            'cash_sales'   => $cash,
            // Phase E1: what its returns paid back in cash - and Phase E3: its exchanges.
            'cash_refunds' => bcadd($this->returns->cashRefundedIn($session), $this->orders->exchangeRefundedIn($session), 2),
            // Phase F1: what its cash movements put into the drawer, and took out of it.
            'cash_in'      => $moved['in'],
            'cash_out'     => $moved['out'],
        ];

        $in  = bcadd(bcadd((string) $session->opening_float, $totals['cash_sales'], 2), $totals['cash_in'], 2);
        $out = bcadd($totals['cash_refunds'], $totals['cash_out'], 2);

        return [...$totals, 'expected_cash' => bcsub($in, $out, 2)];
    }
}
