<?php

namespace App\Services\Documents;

use App\Models\User\PosOrder;
use App\Models\User\PosOrderLine;
use App\Models\User\PosOrderPayment;
use App\Models\User\PosReturn;
use App\Models\User\Workspace;
use App\Repositories\User\PosSettingRepository;
use App\Services\Shared\BusinessCalendar;
use App\Support\Decimal;

/**
 * What a POS sale's receipt shows (Doc 18 section 18.19 - Phase C4), written
 * as its invoice's template writes dates and money: when it was sold, in the
 * business's time, at which register and by whom, and the customer - none for
 * the walk-in one; each item with its quantity, price, discount and amount;
 * the totals - the items' subtotal, the sale's discount, each tax, the cash
 * rounding and the total; and each payment, with what was handed over, and
 * the change. Expects the order loaded for its receipt
 * (PosOrderRepository::loadForReceipt()).
 */
class PosReceiptSheet
{
    public function __construct(
        protected BusinessCalendar $calendar,
        protected DocumentSheet $documentSheet,
        protected PosSettingRepository $posSettings,
    ) {}

    /** @return array{when: string, register: string, cashier: ?string, customer: ?string} */
    public function facts(Workspace $workspace, PosOrder $order, DocumentFormat $format): array
    {
        $when   = $order->completed_at->setTimezone($this->calendar->timezone($workspace));
        $walkIn = $this->posSettings->forWorkspace($workspace)->walk_in_contact_id;

        return [
            'when'     => $format->date($when->toDateString()).' '.$when->format('H:i'),
            'register' => "{$order->register->name} ({$order->register->code})",
            'cashier'  => $order->cashier?->name,
            'customer' => $order->contact_id === $walkIn ? null : ($order->invoice->customer_snapshot['name'] ?? $order->contact?->name),
        ];
    }

    /** Each item sold - the Cash rounding line is not one. */
    public function lines(PosOrder $order, DocumentFormat $format): array
    {
        $money = fn ($amount) => $format->money((string) $amount, $order->currency);

        return $order->lines->map(fn (PosOrderLine $line) => $line->invoiceLine)->filter()->map(fn ($line) => [
            'name'     => $line->name,
            'quantity' => $format->quantity((string) $line->quantity),
            'unit'     => $line->unit,
            'price'    => $money($line->unit_price),
            'discount' => Decimal::isZero((string) $line->discount_amount) ? null : $money($line->discount_amount),
            'amount'   => $money($line->amount),
            'note'     => $line->description,
        ])->values()->all();
    }

    /**
     * The items' subtotal - the invoice's, without its Cash rounding line -
     * the sale's discount, each tax, whether prices include it, the cash
     * rounding (+ or −) and the total.
     */
    public function totals(PosOrder $order, DocumentFormat $format): array
    {
        $invoice  = $order->invoice;
        $money    = fn ($amount) => $format->money((string) $amount, $order->currency);
        $rounding = (string) ($order->cash_rounding ?? '0');

        return [
            'subtotal'  => $money(bcsub((string) $invoice->subtotal, $rounding, 2)),
            'discount'  => Decimal::isZero((string) $invoice->discount_total) ? null : $money($invoice->discount_total),
            'taxes'     => array_map(fn (array $tax) => [
                'name'   => $tax['name'],
                'rate'   => $this->documentSheet->rate((string) $tax['rate'], $tax['treatment'] ?? null),
                'amount' => $money($tax['tax_amount']),
            ], $invoice->taxes ?? []),
            'inclusive' => $invoice->price_tax_mode === 'inclusive',
            'rounding'  => Decimal::isZero($rounding) ? null : (bccomp($rounding, '0', 2) > 0 ? '+' : '').$money($rounding),
            'total'     => $money($order->grand_total),
            'change'    => Decimal::isZero((string) $order->change_given) ? null : $money($order->change_given),
            // Phase D3a: what was left on the customer's account, and when it is due.
            'onAccount' => Decimal::isZero((string) $order->on_account) ? null : $money($order->on_account),
            // Phase D3b: what the customer's own credit paid.
            'credit'    => Decimal::isZero((string) $order->credit_applied) ? null : $money($order->credit_applied),
            // Phase E3: what the exchange's return paid, and the difference paid back in cash.
            'exchange'  => $order->exchange_return_id === null ? null : ['number' => $order->exchangeReturn?->number, 'amount' => $money($order->exchange_credit)],
            'paidBack'  => Decimal::isZero((string) $order->exchange_refunded) ? null : $money($order->exchange_refunded),
            'due'       => $format->date((string) $invoice->due_date),
        ];
    }

    /**
     * A return's refund receipt (Phase E2), as the receipt's view takes it:
     * when it was made, where and by whom, for whom; each line brought back,
     * its credit note's totals, what was paid back - or waits to be - and
     * what was kept as credit. Expects it loaded for its receipt
     * (PosReturnRepository::loadForReceipt()).
     */
    public function refund(Workspace $workspace, PosReturn $return, DocumentFormat $format): array
    {
        $note   = $return->creditNote;
        $money  = fn ($amount) => $format->money((string) $amount, $return->currency);
        $when   = $return->created_at->setTimezone($this->calendar->timezone($workspace));
        $walkIn = $this->posSettings->forWorkspace($workspace)->walk_in_contact_id;
        $out    = bccomp((string) $return->refunded, '0', 2) > 0 ? $return->refunded : $return->refund_due;
        $kept   = bcsub(bcsub((string) $return->total, (string) $return->refunded, 2), (string) $return->refund_due, 2);

        return [
            'when'      => $format->date($when->toDateString()).' '.$when->format('H:i'),
            'register'  => "{$return->register->name} ({$return->register->code})",
            'cashier'   => $return->cashier?->name,
            'customer'  => $return->contact_id === $walkIn ? null : $return->contact?->name,
            'lines'     => $note->lines->map(fn ($line) => [
                'name' => $line->name, 'quantity' => $format->quantity((string) $line->quantity), 'unit' => $line->unit, 'price' => $money($line->unit_price),
                'discount' => Decimal::isZero((string) $line->discount_amount) ? null : $money($line->discount_amount), 'amount' => $money($line->amount), 'note' => null,
            ])->all(),
            'subtotal'  => $money($note->subtotal),
            'discount'  => Decimal::isZero((string) $note->discount_total) ? null : $money($note->discount_total),
            'taxes'     => array_map(fn (array $tax) => ['name' => $tax['name'], 'rate' => $this->documentSheet->rate((string) $tax['rate'], $tax['treatment'] ?? null), 'amount' => $money($tax['tax_amount'])], $note->taxes ?? []),
            'inclusive' => $note->price_tax_mode === 'inclusive',
            'rounding'  => null,
            'total'     => $money($return->total),
            'change'    => null,
            'onAccount' => null,
            'due'       => null,
            'credit'    => null,
            'exchange'  => null,
            'paidBack'  => null,
            'payments'  => Decimal::isZero((string) $out) ? [] : [[
                'method' => $return->refund_method_name, 'reference' => null, 'amount' => $money($out), 'tendered' => null,
                'status' => $return->refund_status === PosReturn::COMPLETED ? null : $return->refund_status,
            ]],
            'credited'  => Decimal::isZero($kept) || bccomp($kept, '0', 2) < 0 ? null : $money($kept),
            // Phase E3: made as an exchange, its credit pays the replacement.
            'creditedAs' => $return->refund_method === PosReturn::EXCHANGE ? 'Kept for the exchange' : 'Kept as credit',
        ];
    }

    /** Each payment - its method as it was named then, and reference - with what was handed over where it was more, and whether it waits to be confirmed or was declined (Phase D2). */
    public function payments(PosOrder $order, DocumentFormat $format): array
    {
        $money = fn ($amount) => $format->money((string) $amount, $order->currency);

        return $order->payments->map(fn (PosOrderPayment $payment) => [
            'method'    => $payment->method_name,
            'reference' => $payment->reference,
            'amount'    => $money($payment->amount),
            'tendered'  => bccomp((string) $payment->tendered, (string) $payment->amount, 2) > 0 ? $money($payment->tendered) : null,
            'status'    => $payment->status === PosOrderPayment::COMPLETED ? null : $payment->status,
        ])->all();
    }
}
