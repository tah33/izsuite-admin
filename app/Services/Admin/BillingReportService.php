<?php

namespace App\Services\Admin;

use App\Repositories\Admin\BillingReportRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Admin > Billing Reports (Doc 15 section 14 - Phase C2b): for a span of
 * months - the last twelve unless chosen - and a currency or plan, what
 * izSuite collected by month and by plan, with its tax and what it refunded
 * (refunds count in the month confirmed); the invoices open and overdue
 * now; the card tries that failed; and its credit to businesses. Amounts
 * never mix currencies.
 */
class BillingReportService
{
    public function __construct(
        protected BillingReportRepository $reports,
    ) {}

    /** @return array<string, mixed> what the report page shows */
    public function report(array $filters): array
    {
        // "!" starts the month on its 1st: today's day of the month would push February into March.
        $to       = isset($filters['to']) ? Carbon::createFromFormat('!Y-m', $filters['to'])->endOfMonth() : now()->endOfMonth();
        $from     = isset($filters['from']) ? Carbon::createFromFormat('!Y-m', $filters['from'])->startOfMonth() : $to->copy()->startOfMonth()->subMonthsNoOverflow(11);
        $currency = $filters['currency'] ?? null;
        $plan     = $filters['plan'] ?? null;
        $today    = now()->toDateString();

        $months   = $this->kept($this->reports->paidByMonth($from, $to, $currency, $plan), $this->reports->refundsByMonth($from, $to, $currency, $plan), 'month')
            ->sortBy([['month', 'desc'], ['currency', 'asc']])->values();
        $plans    = $this->kept($this->reports->paidByPlan($from, $to, $currency, $plan), $this->reports->refundsByPlan($from, $to, $currency, $plan), 'plan')
            ->sortBy([['plan', 'asc'], ['currency', 'asc']])->values();

        return [
            'from'       => $from,
            'to'         => $to,
            'months'     => $months,
            'plans'      => $plans,
            'totals'     => $this->totals($months),
            // A refund with no invoice has no plan: the plans' totals can be less.
            'planTotals' => $this->totals($plans),
            'open'       => $this->reports->open($today, $currency),
            'overdue'    => $this->reports->overdue($today, $currency),
            'failed'     => $this->reports->failedByMonth($from, $to, $currency)->sortBy([['month', 'desc'], ['currency', 'asc']])->values(),
            'failures'   => $this->reports->latestFailures($from, $to, $currency),
            'credits'    => $this->reports->credits($from, $to, $currency),
            'currencies' => $this->reports->currencies(),
            'planNames'  => $this->reports->plans(),
        ];
    }

    /**
     * Paid invoices and refunds side by side, by $key (month or plan) and
     * currency: how many invoices, collected, tax, refunded, and kept - what
     * was collected less what went back - with a total line per currency.
     */
    private function kept(Collection $paid, Collection $refunds, string $key): Collection
    {
        $rows = [];

        foreach ($paid as $row) {
            $rows["{$row->{$key}}|{$row->currency}"] = [
                $key => $row->{$key}, 'currency' => $row->currency, 'invoices' => (int) $row->invoices,
                'collected' => self::money($row->collected), 'tax' => self::money($row->tax), 'refunded' => '0.00',
            ];
        }

        foreach ($refunds as $row) {
            $rows["{$row->{$key}}|{$row->currency}"] ??= [$key => $row->{$key}, 'currency' => $row->currency, 'invoices' => 0, 'collected' => '0.00', 'tax' => '0.00', 'refunded' => '0.00'];
            $rows["{$row->{$key}}|{$row->currency}"]['refunded'] = self::money($row->refunded);
        }

        return collect($rows)->map(fn (array $row) => [...$row, 'kept' => bcsub($row['collected'], $row['refunded'], 2)]);
    }

    /** The rows summed by currency: the span's totals. */
    private function totals(Collection $rows): Collection
    {
        return $rows->groupBy('currency')->map(fn (Collection $rows, string $currency) => [
            'currency' => $currency,
            'invoices' => $rows->sum('invoices'),
            ...collect(['collected', 'tax', 'refunded', 'kept'])->mapWithKeys(fn (string $column) => [
                $column => $rows->reduce(fn (string $sum, array $row) => bcadd($sum, $row[$column], 2), '0.00'),
            ])->all(),
        ])->values();
    }

    /** A sum as the database gives it - a string, or a float in SQLite - to the cent. */
    private static function money(mixed $sum): string
    {
        return bcadd((string) ($sum ?? '0'), '0', 2);
    }
}
