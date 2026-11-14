<?php

namespace App\Services\Reports;

use App\Models\User\Expense;
use App\Models\User\ExpenseLine;
use App\Models\User\Purchase;
use App\Models\User\PurchaseLine;
use App\Models\User\SalesDocument;
use App\Models\User\SalesDocumentLine;
use App\Support\Decimal;

/**
 * The sums Reports and the Dashboard share (Doc 07 sections 19 and 20, Doc 02
 * section 10), so the same sale is the same figure everywhere:
 *
 * - net sales - and purchases - leave separately identified tax out; an
 *   expense leaves out only the tax it can claim back (Doc 10 section 6);
 * - a total across currencies is in the base currency, at the rate each
 *   transaction kept - never today's;
 * - what an invoice owed on a day is InvoiceSettlement's balance, from what
 *   had settled it by then - and a purchase's, PurchaseSettlement's, an
 *   expense's, ExpenseSettlement's.
 */
class SalesFigures
{
    private const SCALE = 10;

    /** $amount in the base currency at $rate, to the cent. */
    public function base(mixed $amount, mixed $rate): string
    {
        return Decimal::round(bcmul(Decimal::of($amount), Decimal::of($rate), self::SCALE));
    }

    /**
     * A line's net amount in the base currency: its amount after its share of
     * the document discount - shared by amount, as SalesCalculator shares it -
     * less the tax an inclusive price holds. Expects issuedLines()'s - or
     * recordedLines()'s - columns.
     */
    public function lineNet(SalesDocumentLine|PurchaseLine $line): string
    {
        $amount   = Decimal::of($line->amount);
        $subtotal = Decimal::of($line->subtotal);
        $kept     = Decimal::isZero($subtotal) ? '0' : bcdiv(bcmul($amount, bcsub($subtotal, Decimal::of($line->discount_total), self::SCALE), self::SCALE), $subtotal, self::SCALE);
        $net      = $line->price_tax_mode === 'inclusive' ? bcsub($kept, Decimal::of($line->tax_amount), self::SCALE) : $kept;

        return $this->base($net, $line->exchange_rate);
    }

    /**
     * What an invoice from invoicesAsOf() still owed that day: its total less
     * payments and tax withheld, less the advances and other credit notes
     * applied to it, less its credit notes' credit not refunded or applied
     * elsewhere - never below zero (see InvoiceSettlement).
     */
    public function owedAsOf(SalesDocument $invoice): string
    {
        $settled = Decimal::sum([
            Decimal::of($invoice->paid_by),
            Decimal::of($invoice->withheld_by),
            Decimal::of($invoice->allocated_by),
            bcsub(bcsub(Decimal::of($invoice->credited_by), Decimal::of($invoice->refunded_by), 2), Decimal::of($invoice->given_by), 2),
        ]);
        $owed = bcsub((string) $invoice->grand_total, $settled, 2);

        return bccomp($owed, '0', 2) > 0 ? $owed : '0.00';
    }

    /** What a purchase from purchasesAsOf() still owed that day: its total less payments and tax withheld - never below zero. */
    public function purchaseOwedAsOf(Purchase $purchase): string
    {
        $owed = bcsub(bcsub((string) $purchase->grand_total, Decimal::of($purchase->paid_by), 2), Decimal::of($purchase->withheld_by), 2);

        return bccomp($owed, '0', 2) > 0 ? $owed : '0.00';
    }

    /**
     * The rates whose tax an expense cannot claim back: those its lines kept
     * as not recoverable. Any other tax on it can be. Expects its lines.
     *
     * @return list<int>
     */
    public function unclaimableRates(Expense $expense): array
    {
        return $expense->lines->where('tax_recoverability', 'non_recoverable')->pluck('tax_rate_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /**
     * The tax on an expense that cannot be claimed back, in its own currency:
     * the tax it kept for each of its unclaimable rates. It stays part of the
     * expense's amount; the rest is shown apart. Expects its lines.
     */
    public function unclaimableTax(Expense $expense): string
    {
        $rates = $this->unclaimableRates($expense);

        return Decimal::sum(array_map(
            fn (array $tax) => Decimal::of($tax['tax_amount']),
            array_filter($expense->taxes ?? [], fn (array $tax) => in_array((int) ($tax['tax_rate_id'] ?? 0), $rates, true)),
        ));
    }

    /**
     * One line of an expense, in the expense's currency: its amount before
     * tax with the tax it cannot claim back, the tax it can, and its total.
     * For a share of the expense - a category's - rather than the expense,
     * whose own figures are its taxes'.
     *
     * @return array{amount: string, tax: string, total: string}
     */
    public function expenseLine(Expense $expense, ExpenseLine $line): array
    {
        $tax    = Decimal::round(Decimal::of($line->tax_amount));
        $before = $expense->price_tax_mode === 'inclusive' ? bcsub(Decimal::of($line->amount), $tax, 2) : Decimal::round(Decimal::of($line->amount));
        $kept   = $line->tax_recoverability === 'non_recoverable';

        return ['amount' => $kept ? bcadd($before, $tax, 2) : $before, 'tax' => $kept ? '0.00' : $tax, 'total' => bcadd($before, $tax, 2)];
    }

    /** An expense's amount in its own currency: before tax, with the tax it cannot claim back. */
    public function expenseAmount(Expense $expense): string
    {
        return bcadd(Decimal::of($expense->net_total), $this->unclaimableTax($expense), 2);
    }

    /** The same in the base currency, at the expense's rate (Doc 02 section 3.5). */
    public function baseExpenseAmount(Expense $expense): string
    {
        return bcadd(Decimal::round(Decimal::of($expense->base_net_total)), $this->base($this->unclaimableTax($expense), $expense->exchange_rate), 2);
    }

    /** What an expense read as of a day (see ExpenseReportRepository::recorded) still owed then: its total less payments and tax withheld - never below zero. */
    public function expenseOwedAsOf(Expense $expense): string
    {
        $owed = bcsub(bcsub((string) $expense->grand_total, Decimal::of($expense->paid_by), 2), Decimal::of($expense->withheld_by), 2);

        return bccomp($owed, '0', 2) > 0 ? $owed : '0.00';
    }

    /** Days past its due date on $asOf: 0 while it is not due. */
    public function daysOverdue(?string $dueDate, string $asOf): int
    {
        if (! $dueDate || $dueDate >= $asOf) {
            return 0;
        }

        return (int) ((strtotime($asOf) - strtotime($dueDate)) / 86400);
    }

    /** Doc 07 section 9's aging groups. */
    public function agingGroup(int $daysOverdue): string
    {
        return match (true) {
            $daysOverdue <= 0  => 'Not Due',
            $daysOverdue <= 30 => '1-30 Days Overdue',
            $daysOverdue <= 60 => '31-60 Days Overdue',
            $daysOverdue <= 90 => '61-90 Days Overdue',
            default            => 'More Than 90 Days Overdue',
        };
    }

    /** The supplier as the purchase names them: as it kept them, or as they are. */
    public function supplierName(Purchase $purchase): string
    {
        return $purchase->supplier_snapshot['name'] ?? $purchase->contact?->name ?? 'Unknown supplier';
    }

    /** Who an expense paid: as it kept them, the contact as they are, or the name typed in. */
    public function payeeName(Expense $expense): string
    {
        return $expense->payeeName() ?? 'Unknown payee';
    }

    /** The customer as the document names them: as it kept them, or as they are. */
    public function customerName(SalesDocument $document): string
    {
        return $document->customer_snapshot['name'] ?? $document->contact?->name ?? 'Unknown customer';
    }
}
