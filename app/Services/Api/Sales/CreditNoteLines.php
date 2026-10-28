<?php

namespace App\Services\Api\Sales;

use App\Models\User\SalesDocument;
use App\Models\User\SalesDocumentLine;
use App\Repositories\User\SalesDocumentRepository;
use App\Services\Sales\SalesDocumentRules;
use App\Support\Decimal;
use Illuminate\Validation\ValidationException;

/**
 * A credit note's lines, made from its invoice's (Doc 08 section 15, Doc 06
 * section 12): each credits one invoice line - a quantity of it, at its price
 * or less - and keeps that line's item, name, unit and tax as the invoice kept
 * them, so the tax is reversed exactly as it was charged.
 *
 * Discounts are shared out the way they were given: a percentage stays that
 * percentage, an amount is shared by quantity (a line's) or by subtotal (the
 * invoice's own). The credit note keeps its invoice's currency, exchange rate
 * and tax settings, so its totals are worked out like the invoice's were.
 *
 * Nothing is credited twice: a line takes at most what its invoice line
 * amounted to, less what issued credit notes took of it already, and the
 * whole credit note at most what is left to credit of the invoice.
 */
class CreditNoteLines
{
    public function __construct(
        protected SalesDocumentRepository $salesDocumentRepository,
        protected SalesDocumentService $salesDocumentService,
        protected SalesDocumentRules $salesDocumentRules,
    ) {}

    /**
     * Each invoice line, by id, with what issued credit notes took of it and
     * what is left to credit.
     *
     * @return array<int, array{line: SalesDocumentLine, credited_quantity: string, credited_amount: string, remaining_quantity: string, remaining_amount: string}>
     */
    public function remaining(SalesDocument $invoice): array
    {
        $credited = $this->salesDocumentRepository->creditedLinesOf($invoice)->groupBy('source_line_id');
        $lines    = [];

        foreach ($invoice->lines as $line) {
            $rows     = $credited->get($line->id, collect());
            $quantity = Decimal::sum($rows->pluck('quantity'), 3);
            $amount   = Decimal::sum($rows->pluck('amount'));

            $lines[$line->id] = [
                'line'               => $line,
                'credited_quantity'  => $quantity,
                'credited_amount'    => $amount,
                'remaining_quantity' => $this->floor(bcsub((string) $line->quantity, $quantity, 3), 3),
                'remaining_amount'   => $this->floor(bcsub((string) $line->amount, $amount, 2), 2),
            ];
        }

        return $lines;
    }

    /**
     * The credit note's header discount and totals, and its line rows, for
     * $requested lines: [{source_line_id, quantity, unit_price}].
     *
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    public function build(SalesDocument $invoice, array $requested): array
    {
        $lines = $invoice->lines->keyBy('id');
        $rows  = [];

        foreach (array_values($requested) as $index => $request) {
            $line = $lines->get((int) $request['source_line_id']);

            if (! $line) {
                throw ValidationException::withMessages(["lines.{$index}.source_line_id" => 'This line is not on the invoice.']);
            }

            $rows[] = $this->row($line, $index, (string) $request['quantity'], Decimal::round((string) $request['unit_price']));
        }

        $header = [
            'price_tax_mode' => $invoice->price_tax_mode,
            'tax_rounding'   => $invoice->tax_rounding,
            'exchange_rate'  => (string) $invoice->exchange_rate,
            ...$this->documentDiscount($invoice, $rows),
        ];

        [$totals, $rows] = $this->salesDocumentService->totals($header, $rows);

        return [[...array_intersect_key($header, ['discount_type' => true, 'discount_value' => true]), ...$totals], $rows];
    }

    /**
     * Refuses a credit note that would credit more than is left: per line -
     * $rows with their amounts worked out - and in total. $grandTotal is the
     * credit note's.
     */
    public function check(SalesDocument $invoice, array $rows, string $grandTotal): void
    {
        $remaining = $this->remaining($invoice);
        $errors    = [];

        foreach (array_values($rows) as $index => $row) {
            $left = $remaining[$row['source_line_id']] ?? null;
            $line = $left['line'] ?? null;

            if (! $line) {
                continue;
            }

            if (bccomp((string) $row['quantity'], (string) $line->quantity, 3) > 0) {
                $errors["lines.{$index}.quantity"] = 'More than was invoiced: '.$this->plain((string) $line->quantity).'.';
            } elseif (bccomp((string) $row['unit_price'], (string) $line->unit_price, 2) > 0) {
                $errors["lines.{$index}.unit_price"] = "More than it was invoiced at: {$line->unit_price}.";
            } elseif (bccomp((string) $row['amount'], $left['remaining_amount'], 2) > 0) {
                $errors["lines.{$index}.quantity"] = Decimal::isZero($left['remaining_amount'])
                    ? 'This line has been credited in full already.'
                    : "Only {$invoice->currency} {$left['remaining_amount']} of this line is left to credit.";
            }
        }

        $creditable = $this->salesDocumentRules->creditable($invoice);

        if (! $errors && bccomp($grandTotal, $creditable, 2) > 0) {
            $errors['lines'] = "This credit note comes to {$invoice->currency} {$grandTotal}, but only {$invoice->currency} {$creditable} of the invoice is left to credit.";
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** A credit note line made from invoice $line: its quantity and price, the rest as the invoice kept it. */
    private function row(SalesDocumentLine $line, int $index, string $quantity, string $price): array
    {
        $discount = $line->discount_type && ! Decimal::isZero((string) $line->discount_value);

        return [
            'source_line_id' => $line->id,
            'position'       => $index + 1,
            'item_id'        => $line->item_id,
            'name'           => $line->name,
            'description'    => $line->description,
            'quantity'       => $quantity,
            'unit'           => $line->unit,
            'unit_price'     => $price,
            'discount_type'  => $discount ? $line->discount_type : null,
            'discount_value' => ! $discount ? null : ($line->discount_type === 'amount'
                ? Decimal::round(bcdiv(bcmul((string) $line->discount_value, $quantity, 10), (string) $line->quantity, 10))
                : (string) $line->discount_value),
            'tax_rate_id'    => $line->tax_rate_id,
            'tax_name'       => $line->tax_name,
            'tax_code'       => $line->tax_code,
            'tax_rate'       => $line->tax_rate !== null ? (string) $line->tax_rate : null,
            'tax_treatment'  => $line->tax_treatment,
            'components'     => null,
            'warranty'       => null,
        ];
    }

    /**
     * The invoice's document discount for these rows: the same percentage, or
     * its amount's share by subtotal - worked out from the rows' own subtotal.
     */
    private function documentDiscount(SalesDocument $invoice, array $rows): array
    {
        if (! $invoice->discount_type || Decimal::isZero((string) $invoice->discount_total)) {
            return ['discount_type' => null, 'discount_value' => null];
        }

        if ($invoice->discount_type === 'percent') {
            return ['discount_type' => 'percent', 'discount_value' => (string) $invoice->discount_value];
        }

        [$totals] = $this->salesDocumentService->totals(['price_tax_mode' => $invoice->price_tax_mode, 'tax_rounding' => $invoice->tax_rounding, 'exchange_rate' => '1'], $rows);

        $share = Decimal::isZero((string) $invoice->subtotal) ? '0.00'
            : Decimal::round(bcdiv(bcmul((string) $invoice->discount_total, $totals['subtotal'], 10), (string) $invoice->subtotal, 10));

        return ['discount_type' => 'amount', 'discount_value' => $share];
    }

    /** $value, but never below zero. */
    private function floor(string $value, int $scale): string
    {
        return bccomp($value, '0', $scale) > 0 ? $value : bcadd('0', '0', $scale);
    }

    /** "2.000" as a person writes it: "2". */
    private function plain(string $value): string
    {
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }
}
