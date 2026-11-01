<?php

namespace App\Services\Documents;

use App\Enums\PurchaseStatus;
use App\Models\User\Purchase;
use App\Models\User\SupplierPayment;
use App\Support\Decimal;
use Illuminate\Support\Str;

/**
 * What a purchase record's sheet and a supplier payment voucher say (Doc 09
 * section 10, Doc 06 section 13), worked out for the views to lay out. A
 * purchase's lines are laid out as a sales document's (DocumentSheet); its
 * facts are the supplier's invoice, and once recorded its totals show what
 * paid it and what it still owes.
 */
class PurchaseSheet
{
    public function __construct(
        protected DocumentSheet $documentSheet,
    ) {}

    /**
     * The facts beside the supplier: its dates, the supplier's own invoice,
     * its reference and location as the template keeps them, its currency -
     * always - and the rate of a foreign currency.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function facts(Purchase $purchase, DocumentFormat $format, array $template, string $baseCurrency): array
    {
        $visible = $template['visible_fields'] ?? [];

        return array_values(array_filter([
            ['Purchase Date', $format->date($purchase->document_date)],
            $purchase->due_date ? ['Due Date', $format->date($purchase->due_date)] : null,
            $purchase->supplier_invoice_number ? ['Supplier Invoice No.', $purchase->supplier_invoice_number] : null,
            $purchase->supplier_invoice_date ? ['Supplier Invoice Date', $format->date($purchase->supplier_invoice_date)] : null,
            in_array('references', $visible, true) && $purchase->reference ? ['Reference', $purchase->reference] : null,
            in_array('location', $visible, true) && $purchase->location ? ['Location', $purchase->location->name] : null,
            ['Currency', $purchase->currency],
            $purchase->currency !== $baseCurrency
                ? ['Exchange Rate', "1 {$purchase->currency} = ".$format->quantity((string) $purchase->exchange_rate)." {$baseCurrency}"]
                : null,
        ]));
    }

    /**
     * The totals under the lines - and, once recorded, what paid it and what
     * it still owes.
     *
     * @return list<array{0: string, 1: string, 2: bool}> label, amount, whether it stands out
     */
    public function totals(Purchase $purchase, DocumentFormat $format, string $baseCurrency): array
    {
        $money    = fn ($amount) => $format->money((string) $amount, $purchase->currency);
        $included = $purchase->price_tax_mode === 'inclusive' ? ' (included)' : '';

        $rows = [['Subtotal', $money($purchase->subtotal), false]];

        if (! Decimal::isZero((string) $purchase->discount_total)) {
            $rows[] = ['Discount', '−'.$money($purchase->discount_total), false];
        }

        foreach ($purchase->taxes ?? [] as $tax) {
            $rows[] = [$tax['name'].' '.$this->documentSheet->rate((string) $tax['rate'], $tax['treatment']).$included, $money($tax['tax_amount']), false];
        }

        $rows[] = ['Total', $money($purchase->grand_total), true];

        if ($purchase->status === PurchaseStatus::Recorded) {
            foreach ([['Paid', $purchase->amount_paid], ['Tax Withheld', $purchase->withholding_amount]] as [$label, $amount]) {
                if (! Decimal::isZero((string) $amount)) {
                    $rows[] = [$label, '−'.$money($amount), false];
                }
            }

            $rows[] = ['Balance Due', $money($purchase->balance_due), true];
        }

        if ($purchase->currency !== $baseCurrency) {
            $rows[] = ["Total in {$baseCurrency}", $format->money((string) $purchase->base_grand_total, $baseCurrency), false];
        }

        return $rows;
    }

    /**
     * The texts under the totals: why it was voided, why it was recorded again
     * though its supplier invoice was recorded before, and its notes while
     * the template keeps them.
     *
     * @return list<array{0: string, 1: string}> heading, text
     */
    public function texts(Purchase $purchase, array $template): array
    {
        return array_values(array_filter([
            $purchase->status === PurchaseStatus::Void && $purchase->status_reason ? ['Why It Was Voided', $purchase->status_reason] : null,
            $purchase->duplicate_reason ? ['Recorded Again Because', $purchase->duplicate_reason] : null,
            ($template['show_notes'] ?? true) && $purchase->notes ? ['Notes', $purchase->notes] : null,
        ]));
    }

    /** A voucher's details (Doc 06 section 13): label and value. */
    public function voucherRows(SupplierPayment $payment, DocumentFormat $format): array
    {
        $purchase = $payment->purchase;

        return array_values(array_filter([
            ['Voucher Number', (string) $payment->number],
            ['Payment Date', $format->date($payment->payment_date)],
            ['Purchase', (string) $purchase->number],
            $purchase->supplier_invoice_number ? ['Supplier Invoice No.', $purchase->supplier_invoice_number] : null,
            ['Paid From', $payment->account->name],
            $payment->method ? ['Payment Method', Str::headline($payment->method)] : null,
            $payment->reference ? ['Reference', $payment->reference] : null,
            $payment->account->currency !== $purchase->currency ? ['Paid Off the Purchase', $format->money((string) $payment->applied_amount, $purchase->currency)] : null,
            Decimal::isZero((string) $payment->withholding_amount) ? null : [($payment->withholding_name ?? 'Tax').' Withheld', $format->money((string) $payment->withholding_amount, $purchase->currency)],
            ['Still to Pay After It', $format->money((string) $payment->balance_after, $purchase->currency)],
        ]));
    }
}
