<?php

namespace App\Services\Reports\Pos;

use App\Models\User\PosReturn;
use App\Models\User\Workspace;
use App\Services\Reports\ReportTable;

/**
 * Returns and Refunds (Doc 18 section 18.36 - Phase H2): each POS return of
 * the period - dated as its credit note - oldest first: its sale, where and
 * by whom, why, what it credited and how it was paid back, in the base
 * currency at the sale's rate. Paid back, waiting to be, or not: kept as the
 * customer's credit, used in an exchange, or a refund declined.
 */
class PosReturnsReport extends PosReport
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        $base    = $workspace->base_currency;
        $rows    = [];
        $waiting = '0.00';

        foreach ($this->pos->returns($workspace, $filters['from'], $filters['to'], $filters) as $return) {
            $rate    = (string) $return->exchange_rate;
            $due     = $this->base((string) $return->refund_due, $rate);
            $waiting = $this->add($waiting, $due);
            $rows[]  = [
                'document_date' => $return->document_date,
                'number'        => $return->number,
                'sale'          => $return->sale_number,
                'register'      => $return->register_name ? "{$return->register_name} ({$return->register_code})" : 'Removed',
                'cashier'       => $this->person($return->cashier_first_name, $return->cashier_last_name),
                'reason'        => $return->reason,
                'credited'      => (string) $return->base_grand_total,
                'method'        => $this->method($return),
                'refunded'      => $this->base((string) $return->refunded, $rate),
                'status'        => $this->status($return),
            ];
        }

        $totals = $this->totals($rows, ['credited', 'refunded']);

        return new ReportTable(
            title: $filters['report_name'] ?? 'Returns and Refunds',
            period: $this->scope($filters),
            columns: [
                $this->column('document_date', 'Date', 'date'),
                $this->column('number', 'Return'),
                $this->column('sale', 'Sale'),
                $this->column('register', 'Register'),
                $this->column('cashier', 'Cashier'),
                $this->column('reason', 'Why'),
                $this->column('credited', 'Credited', 'money', $base),
                $this->column('method', 'Paid Back By'),
                $this->column('refunded', 'Paid Back', 'money', $base),
                $this->column('status', 'Refund'),
            ],
            rows: $rows,
            totals: ['document_date' => 'Total', ...$totals],
            summary: [
                ['label' => 'Credited', 'value' => $totals['credited'], 'currency' => $base],
                ['label' => 'Paid Back', 'value' => $totals['refunded'], 'currency' => $base],
                ['label' => 'Waiting to Be Paid Back', 'value' => $waiting, 'currency' => $base],
                ['label' => 'Not Paid Back', 'value' => $this->subtract($this->subtract($totals['credited'], $totals['refunded']), $waiting), 'currency' => $base],
            ],
            notes: [
                'Each POS return as its credit note is dated, in the base currency at its sale\'s rate. Credited is what its credit note credits, tax included.',
                'Not Paid Back stays the customer\'s credit - a declined refund\'s too - or paid for a replacement, in an exchange.',
            ],
        );
    }

    /** How it was paid back: by the method it named, or not at all. */
    private function method(object $return): string
    {
        return match ($return->refund_method) {
            PosReturn::CREDIT   => 'Kept as credit',
            PosReturn::EXCHANGE => 'Exchange',
            default             => $return->refund_method_name ?? 'Removed',
        };
    }

    /** Where its refund stands: paid, waiting or declined - none for credit kept or an exchange. */
    private function status(object $return): ?string
    {
        if (in_array($return->refund_method, [PosReturn::CREDIT, PosReturn::EXCHANGE], true)) {
            return null;
        }

        return match ($return->refund_status) {
            PosReturn::PENDING  => 'Waiting',
            PosReturn::DECLINED => 'Declined',
            default             => 'Paid',
        };
    }
}
