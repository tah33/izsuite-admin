<?php

namespace App\Services\Reports\Pos;

use App\Models\User\PosHeldOrder;
use App\Models\User\Workspace;
use App\Services\Reports\ReportTable;

/**
 * The Void and Cancellation Report (Doc 18 section 18.36 - Phase H2): what
 * was called off in the period, by the business's days - held orders
 * cancelled, with why, or left to expire; card and other payments declined
 * on confirming them; refunds declined - oldest first, each in its own
 * currency, with where, whose and who called it off. A completed sale is
 * never voided: a return corrects it.
 */
class PosVoidsReport extends PosReport
{
    public function build(Workspace $workspace, array $filters): ReportTable
    {
        [$from, $to] = $this->instants($workspace, $filters);
        $rows        = [];

        foreach ($this->pos->closedHeld($workspace, $from, $to, $filters) as $held) {
            $rows[] = $this->row($workspace, $held, $held->status === PosHeldOrder::CANCELLED ? 'Held order cancelled' : 'Held order expired', (string) $held->grand_total, $held->cancel_reason);
        }

        foreach ($this->pos->declinedPayments($workspace, $from, $to, $filters) as $payment) {
            $rows[] = $this->row($workspace, $payment, "{$payment->method_name} payment declined", $this->cents($payment->amount), $payment->decline_reason);
        }

        foreach ($this->pos->declinedRefunds($workspace, $from, $to, $filters) as $return) {
            $rows[] = $this->row($workspace, $return, "{$return->refund_method_name} refund declined", (string) $return->total, $return->refund_decline_reason);
        }

        usort($rows, fn (array $a, array $b) => $a['moment'] <=> $b['moment'] ?: strcmp($a['number'], $b['number']));

        return new ReportTable(
            title: $filters['report_name'] ?? 'Void and Cancellation Report',
            period: $this->scope($filters),
            columns: [
                $this->column('date', 'Date', 'date'),
                $this->column('kind', 'What'),
                $this->column('number', 'Number'),
                $this->column('register', 'Register'),
                $this->column('cashier', 'Cashier'),
                $this->column('currency', 'Currency'),
                $this->column('amount', 'Amount', 'money'),
                $this->column('by', 'By'),
                $this->column('reason', 'Why'),
            ],
            rows: array_map(fn (array $row) => array_diff_key($row, ['moment' => true]), $rows),
            notes: [
                'A held order is a cart put aside: cancelled, or expired at the end of its time, it never became a sale. A declined payment\'s sale still owes it; a declined refund\'s return - its credit shown - stays the customer\'s credit.',
                'Each amount is in its own currency. Completed sales are never voided: their returns are in Returns and Refunds.',
            ],
        );
    }

    /** A row: when it was called off, in the business's time, what, its number, where, whose, how much, by whom and why. */
    private function row(Workspace $workspace, object $record, string $kind, string $amount, ?string $reason): array
    {
        return [
            'moment'   => (string) $record->closed_at,
            'date'     => $this->dayOf($workspace, $record->closed_at),
            'kind'     => $kind,
            'number'   => $record->number,
            'register' => $record->register_name ? "{$record->register_name} ({$record->register_code})" : 'Removed',
            'cashier'  => $this->person($record->cashier_first_name, $record->cashier_last_name),
            'amount'   => $amount,
            'currency' => $record->currency,
            'by'       => $record->closer_first_name || $record->closer_last_name ? $this->person($record->closer_first_name, $record->closer_last_name) : 'Its time ran out',
            'reason'   => $reason,
        ];
    }
}
