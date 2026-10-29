<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Enums\SalesDocumentStatus;
use App\Models\User\SalesDocument;
use App\Models\User\SalesDocumentLine;
use App\Models\User\SalesPayment;
use App\Support\Decimal;
use Illuminate\Support\Str;

/**
 * What a document's sheet says, worked out for the views to lay out: the
 * facts beside the customer, the line columns worth showing, the lines, the
 * totals and the texts - each as the template asks.
 *
 * A column only shows when its template keeps it and a line uses it: a
 * document without tax shows no tax column and no zero tax (Doc 06 section
 * 16). An issued invoice shows what settled it and what it still owes.
 */
class DocumentSheet
{
    public function __construct(
        protected PaymentDetails $paymentDetails,
    ) {}

    /**
     * The facts beside the customer (Doc 06 section 6): its dates, what it
     * credits, its references and location as the template keeps them, its
     * currency - always, as an amount's symbol can stand for more than one -
     * and the rate of a foreign currency.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function facts(SalesDocument $document, DocumentFormat $format, array $template, string $baseCurrency): array
    {
        $visible = $template['visible_fields'] ?? [];

        return array_values(array_filter([
            ['Date', $format->date($document->document_date)],
            $document->due_date ? ['Due Date', $format->date($document->due_date)] : null,
            $document->expiry_date ? ['Valid Until', $format->date($document->expiry_date)] : null,
            $document->type === DocumentType::CreditNote && $document->source ? ['Invoice', (string) $document->source->number] : null,
            in_array('references', $visible, true) && $document->customer_reference ? ['Your Reference', $document->customer_reference] : null,
            in_array('references', $visible, true) && $document->sales_reference ? ['Our Reference', $document->sales_reference] : null,
            in_array('location', $visible, true) && $document->location ? ['Location', $document->location->name] : null,
            ['Currency', $document->currency],
            $document->currency !== $baseCurrency
                ? ['Exchange Rate', "1 {$document->currency} = ".$format->quantity((string) $document->exchange_rate)." {$baseCurrency}"]
                : null,
        ]));
    }

    /** @return array{quantity: bool, unit_price: bool, discount: bool, tax: bool} */
    public function columns(SalesDocument $document, array $template): array
    {
        $kept = $template['line_columns'] ?? [];

        return [
            'quantity'   => in_array('quantity', $kept, true),
            'unit_price' => in_array('unit_price', $kept, true),
            'discount'   => in_array('discount', $kept, true) && $document->lines->contains(fn (SalesDocumentLine $line) => ! Decimal::isZero((string) $line->discount_amount)),
            'tax'        => in_array('tax', $kept, true) && $document->lines->contains(fn (SalesDocumentLine $line) => $line->tax_name !== null),
        ];
    }

    public function lines(SalesDocument $document, DocumentFormat $format, array $template): array
    {
        $visible = $template['visible_fields'] ?? [];
        $money   = fn ($amount) => $format->money((string) $amount, $document->currency);

        return $document->lines->map(fn (SalesDocumentLine $line) => [
            'name'       => $line->name,
            'notes'      => array_values(array_filter([
                in_array('descriptions', $visible, true) ? $line->description : null,
                ...(in_array('components', $visible, true) ? array_map(fn (array $part) => $format->quantity((string) $part['quantity']).' × '.$part['name'], $line->components ?? []) : []),
                ...(in_array('warranty', $visible, true) ? $this->warranties($line->warranty) : []),
            ])),
            'quantity'   => trim($format->quantity((string) $line->quantity).' '.($line->unit ?? '')),
            'unit_price' => $money($line->unit_price),
            'discount'   => Decimal::isZero((string) $line->discount_amount) ? '—' : '−'.$money($line->discount_amount),
            'tax'        => $line->tax_name !== null ? $line->tax_name.' '.$this->rate((string) $line->tax_rate, $line->tax_treatment) : '—',
            'amount'     => $money($line->amount),
        ])->all();
    }

    /** "Warranty: 12 months" for a product; for a combo, one such line for each component that has one, by name. */
    private function warranties(?array $warranty): array
    {
        if (! $warranty) {
            return [];
        }

        if (! array_is_list($warranty)) {
            return ["Warranty: {$warranty['period']} {$warranty['unit']}"];
        }

        return array_map(fn (array $part) => "Warranty: {$part['name']}, {$part['period']} {$part['unit']}", $warranty);
    }

    /** @return list<array{0: string, 1: string, 2: bool}> label, amount, whether it stands out */
    public function totals(SalesDocument $document, DocumentFormat $format, string $baseCurrency): array
    {
        $money     = fn ($amount) => $format->money((string) $amount, $document->currency);
        $included  = $document->price_tax_mode === 'inclusive' ? ' (included)' : '';
        $issued    = $document->type === DocumentType::Invoice && $document->status === SalesDocumentStatus::Issued;
        $positive  = fn ($amount) => ! Decimal::isZero((string) $amount);

        $rows = [['Subtotal', $money($document->subtotal), false]];

        if ($positive($document->discount_total)) {
            $rows[] = ['Discount', '−'.$money($document->discount_total), false];
        }

        foreach ($document->taxes ?? [] as $tax) {
            $rows[] = [$tax['name'].' '.$this->rate((string) $tax['rate'], $tax['treatment']).$included, $money($tax['tax_amount']), false];
        }

        $rows[] = [$document->type === DocumentType::CreditNote ? 'Total Credit' : 'Total', $money($document->grand_total), true];

        if ($issued) {
            foreach ([['Paid', $document->amount_paid, '−'], ['Tax Withheld', $document->withholding_amount, '−'], ['Credit Notes', $document->credited_amount, '−'], ['Refunded', $document->refunded_amount, '+']] as [$label, $amount, $sign]) {
                if ($positive($amount)) {
                    $rows[] = [$label, $sign.$money($amount), false];
                }
            }

            $rows[] = ['Balance Due', $money($document->balance_due), true];
        }

        if ($document->currency !== $baseCurrency) {
            $rows[] = ["Total in {$baseCurrency}", $format->money((string) $document->base_grand_total, $baseCurrency), false];
        }

        return $rows;
    }

    /**
     * The texts under the totals. A proforma or invoice also says how to pay -
     * its own instructions, and the details of the $accounts shown to
     * customers (Doc 05 section 9) - while its template keeps payment
     * instructions.
     *
     * @return list<array{0: string, 1: string}> heading, text
     */
    public function texts(SalesDocument $document, array $template, array $accounts = []): array
    {
        $paid = $document->type->isPaidFrom() && ($template['show_payment_instructions'] ?? true);

        return array_values(array_filter([
            $document->type === DocumentType::CreditNote && $document->reason ? ['Reason', $document->reason] : null,
            ($template['show_notes'] ?? true) && $document->notes ? ['Notes', $document->notes] : null,
            $paid && $document->payment_instructions ? ['Payment Instructions', $document->payment_instructions] : null,
            $paid && $accounts ? ['Payment Details', implode("\n\n", array_map(fn (array $account) => $this->paymentDetails->text($account), $accounts))] : null,
            ($template['show_terms'] ?? true) && $document->terms ? ['Terms and Conditions', $document->terms] : null,
        ]));
    }

    /** A receipt's details (Doc 06 section 13): label and value. */
    public function receiptRows(SalesPayment $payment, DocumentFormat $format): array
    {
        $document = $payment->document;

        return array_values(array_filter([
            ['Receipt Number', (string) $payment->number],
            ['Payment Date', $format->date($payment->payment_date)],
            [$document->type->label(), (string) $document->number],
            ['Paid Into', $payment->account->name],
            $payment->method ? ['Payment Method', Str::headline($payment->method)] : null,
            $payment->reference ? ['Reference', $payment->reference] : null,
            $payment->account->currency !== $document->currency ? ['Paid Off the Invoice', $format->money((string) $payment->applied_amount, $document->currency)] : null,
            Decimal::isZero((string) $payment->withholding_amount) ? null : [($payment->withholding_name ?? 'Tax').' Withheld', $format->money((string) $payment->withholding_amount, $document->currency)],
            ['Still to Pay After It', $format->money((string) $payment->balance_after, $document->currency)],
        ]));
    }

    /** "15%" for a charged rate; the treatment's name - "Exempt" - for one that charges nothing. */
    private function rate(string $rate, ?string $treatment): string
    {
        if ($treatment && $treatment !== 'standard') {
            return '('.Str::headline($treatment).')';
        }

        return (str_contains($rate, '.') ? rtrim(rtrim($rate, '0'), '.') : $rate).'%';
    }
}
