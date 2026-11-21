<?php

namespace App\Services\Api\Ecommerce;

use App\Jobs\SendStoreOrderEmail;
use App\Mail\StoreOrderMail;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderEmail;
use App\Models\User\EcommerceOrderLine;
use App\Repositories\User\EcommerceOrderRepository;
use App\Services\Documents\DocumentFormat;
use App\Services\Shared\BusinessCalendar;
use App\Support\AddressFormat;
use App\Support\Countries;
use Illuminate\Support\Facades\Mail;

/**
 * An online order's e-mails to its customer (Doc 19 section 19.35 - Phase
 * D5): each kept with the order - its kind, to whom, and whether it was sent
 * or failed, so its history stays with it - and sent through the queue once
 * the order is saved. D5 sends the first, Order Received; the order's later
 * news come with the phases that make it (F, G and I).
 */
class StoreOrderMailer
{
    public function __construct(
        protected EcommerceOrderRepository $orders,
        protected BusinessCalendar $calendar,
    ) {}

    /** $order's e-mail of $kind, kept as queued and sent once the order is saved - with $link, its own page. */
    public function queue(EcommerceOrder $order, string $kind, string $link): EcommerceOrderEmail
    {
        $email = $this->orders->addEmail($order, ['kind' => $kind, 'to' => $order->customer['email'], 'status' => EcommerceOrderEmail::QUEUED]);

        SendStoreOrderEmail::dispatch($email->id, $link)->afterCommit();

        return $email;
    }

    /** Sends the e-mail $emailId, the queue job's work, and marks it sent. */
    public function deliver(int $emailId, string $link): void
    {
        $email = $this->orders->findEmail($emailId);

        if (! $email || $email->status === EcommerceOrderEmail::SENT) {
            return;
        }

        $order      = $email->order;
        $storefront = $order->storefront;

        Mail::to($email->to)->send(new StoreOrderMail(
            "Your order {$order->number} at {$storefront->name}",
            $storefront->name,
            $storefront->support_email,
            $storefront->support_phone,
            $this->shown($order, $link),
        ));

        $this->orders->updateEmail($email, ['status' => EcommerceOrderEmail::SENT, 'sent_at' => now(), 'error' => null]);
    }

    /** The e-mail $emailId as failed, after its last try. */
    public function failed(int $emailId, ?\Throwable $exception): void
    {
        $email = $this->orders->findEmail($emailId);

        if ($email && $email->status !== EcommerceOrderEmail::SENT) {
            $this->orders->updateEmail($email, ['status' => EcommerceOrderEmail::FAILED, 'error' => mb_substr($exception?->getMessage() ?? 'Not sent.', 0, 500)]);
        }
    }

    /** $order as its e-mail shows it, in its business's formats. */
    private function shown(EcommerceOrder $order, string $link): array
    {
        $workspace = $order->workspace;
        $format    = new DocumentFormat($workspace->date_format ?? 'd M Y', $workspace->number_format ?? 'comma_dot');
        $money     = fn ($amount) => $format->money((string) $amount, $order->currency);
        $delivery  = $order->delivery_snapshot ?? [];
        $payment   = $order->payment_snapshot ?? [];
        $paysAhead = in_array($payment['type'] ?? null, ['bank_transfer', 'mobile_money'], true);

        return [
            'number'         => $order->number,
            'customer'       => $order->customer['name'],
            'placed_on'      => $format->date($order->placed_at?->setTimezone($this->calendar->timezone($workspace))->toDateString()),
            'lines'          => $order->lines->where('kind', EcommerceOrderLine::PRODUCT)->map(fn (EcommerceOrderLine $line) => [
                'quantity' => $format->quantity((string) $line->quantity),
                'title'    => $line->title,
                'options'  => implode(', ', array_column($line->options ?? [], 'value')),
                'amount'   => $money($line->shown_amount ?? $line->amount),
            ])->values()->all(),
            'totals'         => array_filter([
                'Products'                                                    => $money($order->subtotal),
                'Delivery'                                                    => $order->delivery === EcommerceOrder::SHIPPING ? (($delivery['quoted'] ?? false) ? 'Quoted later' : $money($order->shipping_total)) : null,
                ($order->prices_include_tax ? 'Of which tax' : 'Tax')         => $order->prices_include_tax === null ? null : $money($order->tax_total),
                'Total'                                                       => $money($order->grand_total),
            ]),
            'delivery_title' => match ($order->delivery) {
                EcommerceOrder::SHIPPING => 'Delivery: '.($delivery['name'] ?? ''),
                EcommerceOrder::PICKUP   => 'Collect it at '.($delivery['name'] ?? 'the shop'),
                default                  => 'Delivery',
            },
            'delivery'       => match ($order->delivery) {
                EcommerceOrder::SHIPPING => implode("\n", array_filter([$this->address($order->shipping_address), ($delivery['quoted'] ?? false) ? 'We tell you its price before it ships.' : null])),
                EcommerceOrder::PICKUP   => implode("\n", array_filter([$delivery['ready_within'] ?? 'We tell you when it is ready.', $delivery['instructions'] ?? null])),
                default                  => 'There is nothing to deliver.',
            },
            'payment'        => $payment['name'] ?? '',
            'payment_note'   => match (true) {
                $paysAhead && $order->payment_reference !== null => "We will match your payment, reference {$order->payment_reference}, and confirm it.",
                $paysAhead                                       => "Pay {$money($order->grand_total)}, with your order number, {$order->number}, as the payment's reference.",
                ($payment['type'] ?? null) === 'pay_on_pickup'   => "You pay {$money($order->grand_total)} when you collect it.",
                default                                          => "You pay {$money($order->grand_total)} on delivery.",
            },
            'instructions'   => $payment['instructions'] ?? null,
            'link'           => $link,
        ];
    }

    /** An address on one line per part, as its country writes it. */
    private function address(?array $address): string
    {
        return $address ? implode("\n", AddressFormat::lines(Countries::addressFormat($address['country'] ?? null), [
            'line1' => $address['line1'] ?? null, 'line2' => $address['line2'] ?? null, 'city' => $address['city'] ?? null,
            'region' => $address['region'] ?? null, 'postal' => $address['postal_code'] ?? null, 'country' => Countries::name($address['country'] ?? null),
        ])) : '';
    }
}
