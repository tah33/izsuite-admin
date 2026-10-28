<?php

namespace App\Services\Reports;

use App\Models\User\SalesDocument;
use App\Models\User\SalesDocumentLine;
use App\Support\Decimal;

/**
 * The sums Reports and the Dashboard share (Doc 07 sections 19 and 20, Doc 02
 * section 10), so the same sale is the same figure everywhere:
 *
 * - net sales leave separately identified tax out;
 * - a total across currencies is in the base currency, at the rate each
 *   transaction kept - never today's;
 * - what an invoice owed on a day is InvoiceSettlement's balance, from what
 *   had settled it by then.
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
     * A line's net sales in the base currency: its amount after its share of
     * the document discount - shared by amount, as SalesCalculator shares it -
     * less the tax an inclusive price holds. Expects issuedLines()'s columns.
     */
    public function lineNet(SalesDocumentLine $line): string
    {
        $amount   = Decimal::of($line->amount);
        $subtotal = Decimal::of($line->subtotal);
        $kept     = Decimal::isZero($subtotal) ? '0' : bcdiv(bcmul($amount, bcsub($subtotal, Decimal::of($line->discount_total), self::SCALE), self::SCALE), $subtotal, self::SCALE);
        $net      = $line->price_tax_mode === 'inclusive' ? bcsub($kept, Decimal::of($line->tax_amount), self::SCALE) : $kept;

        return $this->base($net, $line->exchange_rate);
    }

    /**
     * What an invoice from invoicesAsOf() still owed that day: its total less
     * payments and tax withheld, less its credit notes' credit not refunded -
     * never below zero (see InvoiceSettlement).
     */
    public function owedAsOf(SalesDocument $invoice): string
    {
        $settled = Decimal::sum([
            Decimal::of($invoice->paid_by),
            Decimal::of($invoice->withheld_by),
            bcsub(Decimal::of($invoice->credited_by), Decimal::of($invoice->refunded_by), 2),
        ]);
        $owed = bcsub((string) $invoice->grand_total, $settled, 2);

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

    /** The customer as the document names them: as it kept them, or as they are. */
    public function customerName(SalesDocument $document): string
    {
        return $document->customer_snapshot['name'] ?? $document->contact?->name ?? 'Unknown customer';
    }
}
