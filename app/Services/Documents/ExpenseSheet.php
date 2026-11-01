<?php

namespace App\Services\Documents;

use App\Enums\ExpenseStatus;
use App\Models\User\Expense;
use App\Models\User\ExpenseLine;
use App\Models\User\ExpensePayment;
use App\Support\Decimal;
use Illuminate\Support\Str;

/**
 * What an expense's record (Doc 10 section 10) shows beside its payee and
 * under its lines, in its template's formats: the facts, the lines - each by
 * its category, what it was for under it - the totals with what paid it and
 * what it still owes, and the texts at the end - and an expense payment's
 * voucher. Expenses take no discounts.
 */
class ExpenseSheet
{
    public function __construct(
        protected DocumentSheet $documentSheet,
    ) {}

    /**
     * The facts beside the payee: its dates, the receipt number, its reference
     * and location as the template keeps them, its currency - always - and the
     * rate of a foreign currency.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function facts(Expense $expense, DocumentFormat $format, array $template, string $baseCurrency): array
    {
        $visible = $template['visible_fields'] ?? [];

        return array_values(array_filter([
            ['Expense Date', $format->date($expense->document_date)],
            $expense->due_date ? ['Due Date', $format->date($expense->due_date)] : null,
            $expense->receipt_number ? ['Receipt No.', $expense->receipt_number] : null,
            in_array('references', $visible, true) && $expense->reference ? ['Reference', $expense->reference] : null,
            in_array('location', $visible, true) && $expense->location ? ['Location', $expense->location->name] : null,
            ['Currency', $expense->currency],
            $expense->currency !== $baseCurrency
                ? ['Exchange Rate', "1 {$expense->currency} = ".$format->quantity((string) $expense->exchange_rate)." {$baseCurrency}"]
                : null,
        ]));
    }

    /** The line columns the template keeps - tax only where a line has some. */
    public function columns(Expense $expense, array $template): array
    {
        $kept = $template['line_columns'] ?? [];

        return [
            'quantity'   => in_array('quantity', $kept, true),
            'unit_price' => in_array('unit_price', $kept, true),
            'discount'   => false,
            'tax'        => in_array('tax', $kept, true) && $expense->lines->contains(fn (ExpenseLine $line) => $line->tax_name !== null),
        ];
    }

    public function lines(Expense $expense, DocumentFormat $format, array $template): array
    {
        $visible = $template['visible_fields'] ?? [];
        $money   = fn ($amount) => $format->money((string) $amount, $expense->currency);

        return $expense->lines->map(fn (ExpenseLine $line) => [
            'name'       => $line->category_name ?? 'Expense',
            'notes'      => in_array('descriptions', $visible, true) && $line->description ? [$line->description] : [],
            'quantity'   => $format->quantity((string) $line->quantity),
            'unit_price' => $money($line->unit_price),
            'discount'   => '—',
            'tax'        => $line->tax_name !== null ? $line->tax_name.' '.$this->documentSheet->rate((string) $line->tax_rate, $line->tax_treatment) : '—',
            'amount'     => $money($line->amount),
        ])->all();
    }

    /**
     * The totals under the lines - and, once recorded, what paid it and what
     * it still owes.
     *
     * @return list<array{0: string, 1: string, 2: bool}> label, amount, whether it stands out
     */
    public function totals(Expense $expense, DocumentFormat $format, string $baseCurrency): array
    {
        $money    = fn ($amount) => $format->money((string) $amount, $expense->currency);
        $included = $expense->price_tax_mode === 'inclusive' ? ' (included)' : '';

        $rows = [['Subtotal', $money($expense->subtotal), false]];

        foreach ($expense->taxes ?? [] as $tax) {
            $rows[] = [$tax['name'].' '.$this->documentSheet->rate((string) $tax['rate'], $tax['treatment']).$included, $money($tax['tax_amount']), false];
        }

        $rows[] = ['Total', $money($expense->grand_total), true];

        if ($expense->status === ExpenseStatus::Recorded) {
            foreach ([['Paid', $expense->amount_paid], ['Tax Withheld', $expense->withholding_amount]] as [$label, $amount]) {
                if (! Decimal::isZero((string) $amount)) {
                    $rows[] = [$label, '−'.$money($amount), false];
                }
            }

            $rows[] = ['Balance Due', $money($expense->balance_due), true];
        }

        if ($expense->currency !== $baseCurrency) {
            $rows[] = ["Total in {$baseCurrency}", $format->money((string) $expense->base_grand_total, $baseCurrency), false];
        }

        return $rows;
    }

    /**
     * The texts under the totals: why it was voided, why it was recorded again
     * though its receipt may have been recorded before, and its notes while
     * the template keeps them.
     *
     * @return list<array{0: string, 1: string}> heading, text
     */
    public function texts(Expense $expense, array $template): array
    {
        return array_values(array_filter([
            $expense->status === ExpenseStatus::Void && $expense->status_reason ? ['Why It Was Voided', $expense->status_reason] : null,
            $expense->duplicate_reason ? ['Recorded Again Because', $expense->duplicate_reason] : null,
            ($template['show_notes'] ?? true) && $expense->notes ? ['Notes', $expense->notes] : null,
        ]));
    }

    /** A voucher's details (Doc 06 section 13): label and value. */
    public function voucherRows(ExpensePayment $payment, DocumentFormat $format): array
    {
        $expense = $payment->expense;

        return array_values(array_filter([
            ['Voucher Number', (string) $payment->number],
            ['Payment Date', $format->date($payment->payment_date)],
            ['Expense', (string) $expense->number],
            $expense->receipt_number ? ['Receipt No.', $expense->receipt_number] : null,
            ['Paid From', $payment->account->name],
            $payment->method ? ['Payment Method', Str::headline($payment->method)] : null,
            $payment->reference ? ['Reference', $payment->reference] : null,
            $payment->account->currency !== $expense->currency ? ['Paid Off the Expense', $format->money((string) $payment->applied_amount, $expense->currency)] : null,
            Decimal::isZero((string) $payment->withholding_amount) ? null : [($payment->withholding_name ?? 'Tax').' Withheld', $format->money((string) $payment->withholding_amount, $expense->currency)],
            ['Still to Pay After It', $format->money((string) $payment->balance_after, $expense->currency)],
        ]));
    }
}
