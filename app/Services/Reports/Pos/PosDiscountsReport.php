<?php

namespace App\Services\Reports\Pos;

use App\Models\User\Workspace;
use App\Services\Reports\ReportTable;

/**
 * Discounts and Price Overrides (Doc 18 section 18.36 - Phase H2): each
 * completed sale of the period that gave a discount - on its lines, on the
 * whole, or both - or had a price overridden, oldest first. What it came to
 * before its discounts, the discounts and their share, how many prices were
 * overridden and what that changed, in the base currency at the sale's rate
 * and as it shows its prices; why, and the manager who approved a discount
 * beyond the limit (Phase C3).
 */
class PosDiscountsReport extends PosReport
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base      = $workspace->base_currency;
        $sales     = $this->pos->discounted($workspace, $filters['from'], $filters['to'], $filters);
        $overrides = $this->pos->overrides($sales->pluck('id')->all())->groupBy('pos_order_id');
        $rows      = [];
        $parts     = ['lines' => '0.00', 'whole' => '0.00'];

        foreach ($sales as $sale) {
            $rate      = (string) $sale->exchange_rate;
            $onLines   = $this->base((string) $sale->line_discounts, $rate);
            $onWhole   = $this->base((string) $sale->discount_total, $rate);
            $discount  = $this->add($onLines, $onWhole);
            $before    = $this->base(bcadd((string) $sale->subtotal, (string) $sale->line_discounts, 2), $rate);
            $overridden = $overrides->get($sale->id, collect());
            $change    = $overridden->reduce(fn (string $sum, object $line) => $this->add($sum, $this->base(bcmul(bcsub((string) $line->unit_price, (string) $line->standard_price, 2), (string) $line->quantity, 10), $rate)), '0.00');
            $reasons   = array_values(array_unique(array_filter([$sale->discount_reason, ...$overridden->pluck('override_reason')->all()])));

            $parts['lines'] = $this->add($parts['lines'], $onLines);
            $parts['whole'] = $this->add($parts['whole'], $onWhole);
            $rows[] = [
                'document_date' => $sale->document_date,
                'number'        => $sale->number,
                'register'      => $sale->register_name ? "{$sale->register_name} ({$sale->register_code})" : 'Removed',
                'cashier'       => $this->person($sale->cashier_first_name, $sale->cashier_last_name),
                'before'        => $before,
                'discount'      => $discount,
                'percent'       => $this->percent($discount, $before),
                'overrides'     => $overridden->count(),
                'change'        => $change,
                'reason'        => implode('; ', $reasons) ?: null,
                'approved_by'   => $sale->discount_approved_by ? $this->person($sale->approver_first_name, $sale->approver_last_name) : null,
            ];
        }

        $totals = $this->totals($rows, ['before', 'discount', 'change']);

        return new ReportTable(
            title: $filters['report_name'] ?? 'Discounts and Price Overrides',
            period: $this->scope($filters),
            columns: [
                $this->column('document_date', 'Date', 'date'),
                $this->column('number', 'Sale'),
                $this->column('register', 'Register'),
                $this->column('cashier', 'Cashier'),
                $this->column('before', 'Before Discounts', 'money', $base),
                $this->column('discount', 'Discounts', 'money', $base),
                $this->column('percent', 'Discount %', 'percent'),
                $this->column('overrides', 'Prices Overridden', 'number'),
                $this->column('change', 'Override Change', 'money', $base),
                $this->column('reason', 'Why'),
                $this->column('approved_by', 'Approved By'),
            ],
            rows: $rows,
            totals: ['document_date' => 'Total', ...$totals, 'percent' => $this->percent($totals['discount'], $totals['before']), 'overrides' => array_sum(array_column($rows, 'overrides'))],
            summary: [
                ['label' => 'Line Discounts', 'value' => $parts['lines'], 'currency' => $base],
                ['label' => 'Sale Discounts', 'value' => $parts['whole'], 'currency' => $base],
                ['label' => 'Override Change', 'value' => $totals['change'], 'currency' => $base],
            ],
            notes: [
                'Completed POS sales that gave a discount or had a price overridden, in the base currency at each sale\'s rate, as it shows its prices. Held, pending and cancelled orders never count.',
                'Override Change is each overridden price less the price it replaced, times its quantity: below zero when it was lowered. Approved By names the manager who let a discount go beyond the limit.',
            ],
        );
    }
}
