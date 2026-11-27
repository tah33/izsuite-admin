<?php

namespace App\Services\Api\Ecommerce;

use App\Jobs\SendStoreOrderEmail;
use App\Mail\StoreOrderMail;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderEmail;
use App\Models\User\EcommerceOrderLine;
use App\Models\User\EcommerceOrderDelivery;
use App\Repositories\User\EcommerceFulfilmentRepository;
use App\Repositories\User\EcommerceOrderDeliveryRepository;
use App\Repositories\User\EcommerceOrderRepository;
use App\Services\Documents\DocumentFormat;
use App\Services\Shared\BusinessCalendar;
use App\Support\AddressFormat;
use App\Support\Countries;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

/**
 * An online order's e-mails to its customer (Doc 19 section 19.35 - Phase
 * D5): each kept with the order - its kind, to whom, and whether it was sent
 * or failed, so its history stays with it - and sent through the queue once
 * the order is saved. D5 sends the first, Order Received, with the order's
 * own page; F2 Order Confirmed, with its invoice's secure link; F3 Payment
 * Received and Payment Failed, with the invoice's link once it has one; F4
 * Order Cancelled, with none, and never why; F6a a Payment Link, with the
 * link's own page; G1 a shipment On Its Way, with its tracking link, Ready to
 * Collect, and Delivered (or collected); G3a Digital Products Delivered, with
 * their keys, links and notes, and a Service Performed. The order's later
 * news come with the phases that make it (I).
 */
class StoreOrderMailer
{
    public function __construct(
        protected EcommerceOrderRepository $orders,
        protected BusinessCalendar $calendar,
        protected EcommerceFulfilmentRepository $fulfilments,
        protected EcommerceOrderDeliveryRepository $deliveries,
    ) {}

    /**
     * $order's e-mail of $kind, kept as queued and sent once the order is
     * saved - with $link: its own page, or its invoice once it has one (none
     * for a payment failed before), or a payment link's page - and, for a
     * payment's (Phase F3), its $details: the amount and reference received,
     * and what is left to pay; for a payment link's (Phase F6a), the amount it
     * asks, when it expires and its methods' names.
     */
    public function queue(EcommerceOrder $order, string $kind, ?string $link, array $details = []): EcommerceOrderEmail
    {
        $email = $this->orders->addEmail($order, ['kind' => $kind, 'to' => $order->customer['email'], 'status' => EcommerceOrderEmail::QUEUED]);

        SendStoreOrderEmail::dispatch($email->id, $link, $details)->afterCommit();

        return $email;
    }

    /** Sends the e-mail $emailId, the queue job's work, and marks it sent. */
    public function deliver(int $emailId, ?string $link, array $details = []): void
    {
        $email = $this->orders->findEmail($emailId);

        if (! $email || $email->status === EcommerceOrderEmail::SENT) {
            return;
        }

        $order      = $email->order;
        $storefront = $order->storefront;
        $about      = "order {$order->number} at {$storefront->name}";

        Mail::to($email->to)->send(new StoreOrderMail(
            match ($email->kind) {
                EcommerceOrderEmail::ORDER_CONFIRMED  => "Your {$about} is confirmed",
                EcommerceOrderEmail::PAYMENT_RECEIVED => "Payment received for your {$about}",
                EcommerceOrderEmail::PAYMENT_FAILED   => "Your payment for {$about} was not received",
                EcommerceOrderEmail::ORDER_CANCELLED  => "Your {$about} is cancelled",
                EcommerceOrderEmail::PAYMENT_LINK     => "Pay for your {$about}",
                EcommerceOrderEmail::ORDER_SHIPPED    => "Your {$about} is on its way",
                EcommerceOrderEmail::ORDER_READY      => "Your {$about} is ready to collect",
                EcommerceOrderEmail::ORDER_DELIVERED  => $order->delivery === EcommerceOrder::PICKUP ? "You collected your {$about}" : "Your {$about} was delivered",
                // Phase G3a: one product by its title, several together.
                EcommerceOrderEmail::DIGITAL_DELIVERED => count($details['lines'] ?? []) === 1
                    ? ($order->lines->firstWhere('id', $details['lines'][0])?->title ?? 'Your digital product')." from your {$about} is ready"
                    : "Your digital products from {$about} are ready",
                EcommerceOrderEmail::SERVICE_PERFORMED => ($order->lines->firstWhere('id', $details['line'] ?? 0)?->title ?? 'Your service')." from your {$about} is done",
                default                               => "Your {$about}",
            },
            $storefront->name,
            $storefront->support_email,
            $storefront->support_phone,
            [...$this->shown($order, $link, $email->kind, $details), ...$this->fulfilled($order, $email->kind, $details), ...$this->delivered($order, $email->kind, $details), 'kind' => $email->kind, 'invoice' => $order->invoice?->number],
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

    /** $order as its e-mail of $kind shows it, in its business's formats. */
    private function shown(EcommerceOrder $order, ?string $link, string $kind, array $details): array
    {
        $workspace = $order->workspace;
        $format    = new DocumentFormat($workspace->date_format ?? 'd M Y', $workspace->number_format ?? 'comma_dot');
        $money     = fn ($amount) => $format->money((string) $amount, $order->currency);
        $delivery  = $order->delivery_snapshot ?? [];
        $payment   = $order->payment_snapshot ?? [];
        $settled   = $kind === EcommerceOrderEmail::PAYMENT_RECEIVED && bccomp((string) ($details['balance'] ?? '0'), '0', 2) <= 0;
        // Phase F4: a cancelled order is neither sent nor paid.
        $cancelled = $kind === EcommerceOrderEmail::ORDER_CANCELLED;
        // Phase F6a: a payment link - what it asks, the business's last day it works, and its methods.
        $linked    = $kind === EcommerceOrderEmail::PAYMENT_LINK;
        $until     = $linked ? $format->date(CarbonImmutable::parse($details['expires'])->setTimezone($this->calendar->timezone($workspace))->toDateString()) : null;

        return [
            // Phase F3: a payment received - how much, and its reference. Phase F6a: what a link asks, and until when.
            'paid'           => ! $linked && isset($details['amount']) ? $money($details['amount']) : null,
            'reference'      => $details['reference'] ?? null,
            'asked'          => $linked ? $money($details['amount']) : null,
            'until'          => $until,
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
                // Phase F5b: a discount the business gave - never why.
                'Discount'                                                    => bccomp((string) $order->discount_total, '0', 2) > 0 ? '−'.$money($order->discount_total) : null,
                ($order->prices_include_tax ? 'Of which tax' : 'Tax')         => $order->prices_include_tax === null ? null : $money($order->tax_total),
                'Total'                                                       => $money($order->grand_total),
            ]),
            'delivery_title' => match ($order->delivery) {
                EcommerceOrder::SHIPPING => 'Delivery: '.($delivery['name'] ?? ''),
                EcommerceOrder::PICKUP   => 'Collect it at '.($delivery['name'] ?? 'the shop'),
                default                  => 'Delivery',
            },
            'delivery'       => match (true) {
                $cancelled && $order->delivery === EcommerceOrder::SHIPPING => 'It will not be sent.',
                $cancelled && $order->delivery === EcommerceOrder::PICKUP   => 'It will not be ready to collect.',
                $order->delivery === EcommerceOrder::SHIPPING               => implode("\n", array_filter([$this->address($order->shipping_address), ($delivery['quoted'] ?? false) ? 'We tell you its price before it ships.' : null])),
                $order->delivery === EcommerceOrder::PICKUP                 => implode("\n", array_filter([$delivery['ready_within'] ?? 'We tell you when it is ready.', $delivery['instructions'] ?? null])),
                default                                                     => 'There is nothing to deliver.',
            },
            'payment'        => $linked ? implode(' or ', $details['methods'] ?? []) : ($payment['name'] ?? ''),
            'payment_note'   => match ($kind) {
                // A link: each method's instructions are on its page.
                EcommerceOrderEmail::PAYMENT_LINK     => "Pay {$money($details['amount'])} by {$until}, with your order number, {$order->number}, as the payment's reference: the link below shows how.",
                EcommerceOrderEmail::PAYMENT_RECEIVED => $settled ? 'Nothing is left to pay.' : "{$money($details['balance'])} is still to pay.",
                // Failed: what is owed, to pay again - not the reference that did not match.
                EcommerceOrderEmail::PAYMENT_FAILED   => $this->howToPay($order, $money($details['balance'] ?? $order->grand_total), null),
                // Cancelled: nothing to pay - and, the customer saying they paid, the business is in touch.
                EcommerceOrderEmail::ORDER_CANCELLED  => in_array($order->payment_status, [EcommerceOrder::PAYMENT_PENDING, EcommerceOrder::PAYMENT_FAILED], true)
                    ? 'If you have paid for it, we will be in touch about your money.'
                    : 'There is nothing to pay for it.',
                default                               => $this->howToPay($order, $money($order->grand_total), $order->payment_reference),
            },
            'instructions'   => $settled || $cancelled || $linked ? null : ($payment['instructions'] ?? null),
            'link'           => $link,
        ];
    }

    /**
     * Phase G1: what a shipment's, readiness's or delivery's e-mail shows
     * over the order's - the shipment's own products, without prices; its
     * carrier and tracking; where to collect it; the day it arrived - and its
     * payment only while the order owes it before it reaches them (cash on
     * delivery, paying on pickup). Nothing for other e-mails.
     */
    private function fulfilled(EcommerceOrder $order, string $kind, array $details): array
    {
        if (! in_array($kind, [EcommerceOrderEmail::ORDER_SHIPPED, EcommerceOrderEmail::ORDER_READY, EcommerceOrderEmail::ORDER_DELIVERED], true)) {
            return [];
        }

        $workspace  = $order->workspace;
        $format     = new DocumentFormat($workspace->date_format ?? 'd M Y', $workspace->number_format ?? 'comma_dot');
        $fulfilment = isset($details['fulfilment']) ? $this->fulfilments->find((int) $details['fulfilment']) : null;
        $place      = $order->delivery_snapshot['name'] ?? $fulfilment?->location?->name ?? 'the shop';
        $owing      = $kind !== EcommerceOrderEmail::ORDER_DELIVERED && $order->payment_status !== EcommerceOrder::PAID;

        return [
            'rest'           => (bool) ($details['rest'] ?? false),
            'place'          => $place,
            'completed_on'   => $fulfilment?->completed_on ? $format->date($fulfilment->completed_on->toDateString()) : null,
            ...($fulfilment ? [
                'lines'  => $fulfilment->lines->map(fn ($line) => [
                    'quantity' => $format->quantity((string) $line->quantity),
                    'title'    => $line->orderLine?->title,
                    'options'  => implode(', ', array_column($line->orderLine?->options ?? [], 'value')),
                    'amount'   => '',
                ])->values()->all(),
                'totals' => [],
            ] : []),
            'delivery_title' => match ($kind) {
                EcommerceOrderEmail::ORDER_SHIPPED => ucfirst($fulfilment?->label() ?? 'shipment').($fulfilment?->carrier ? ", by {$fulfilment->carrier}" : ''),
                EcommerceOrderEmail::ORDER_READY   => "Collect it at {$place}",
                default                            => $order->delivery === EcommerceOrder::PICKUP ? 'Collected' : 'Delivered',
            },
            'delivery'       => match ($kind) {
                EcommerceOrderEmail::ORDER_SHIPPED => implode("\n", array_filter([$fulfilment?->tracking_number ? "Tracking number: {$fulfilment->tracking_number}" : null, $this->address($order->shipping_address)])),
                EcommerceOrderEmail::ORDER_READY   => implode("\n", array_filter(['It is ready now.', $order->delivery_snapshot['instructions'] ?? null])),
                default                            => $fulfilment?->completed_on ? 'On '.$format->date($fulfilment->completed_on->toDateString()).'.' : '',
            },
            'payment_shown'  => $owing,
            'instructions'   => null,
        ];
    }

    /**
     * Phase G3a: what a digital delivery's or a performed service's e-mail
     * shows over the order's - the products delivered, without prices, each
     * with what reaches the customer (a download waiting on their order page,
     * its license keys, the note, its item's instructions - an external
     * link's address among them); or the service, its day and the note - and,
     * for a service, its payment while the order owes it. Nothing for other
     * e-mails.
     */
    private function delivered(EcommerceOrder $order, string $kind, array $details): array
    {
        if (! in_array($kind, [EcommerceOrderEmail::DIGITAL_DELIVERED, EcommerceOrderEmail::SERVICE_PERFORMED], true)) {
            return [];
        }

        $workspace  = $order->workspace;
        $format     = new DocumentFormat($workspace->date_format ?? 'd M Y', $workspace->number_format ?? 'comma_dot');
        $deliveries = $this->deliveries->ofOrder($order);
        $service    = $kind === EcommerceOrderEmail::SERVICE_PERFORMED;
        $lines      = $order->lines->whereIn('id', $service ? [$details['line'] ?? 0] : ($details['lines'] ?? []))->values();
        $performed  = $service ? $deliveries->get($lines->first()?->id)?->performed_on : null;
        $day        = $performed ? $format->date($performed->toDateString()) : null;

        return [
            'lines'          => $lines->map(fn (EcommerceOrderLine $line) => [
                'quantity' => $format->quantity((string) $line->quantity),
                'title'    => $line->title,
                'options'  => implode(', ', array_column($line->options ?? [], 'value')),
                'amount'   => '',
            ])->all(),
            'totals'         => [],
            'performed_on'   => $day,
            'delivery_title' => $service ? "Performed on {$day}" : ($lines->count() > 1 ? 'How you get them' : 'How you get it'),
            'delivery'       => $service
                ? (string) ($deliveries->get($lines->first()?->id)?->note ?? '')
                : implode("\n\n", $lines->map(fn (EcommerceOrderLine $line) => $this->handedOver($line, $deliveries->get($line->id)))->all()),
            'payment_shown'  => $service && $order->payment_status !== EcommerceOrder::PAID,
            'instructions'   => null,
        ];
    }

    /** Phase G3a: what a delivered digital product gives its customer, in words - nothing once its access is revoked. */
    private function handedOver(EcommerceOrderLine $line, ?EcommerceOrderDelivery $delivery): string
    {
        if ($delivery?->status !== EcommerceOrderDelivery::DELIVERED) {
            return "{$line->title}: no longer available.";
        }

        $keys = $delivery->license_keys ?? [];

        return implode("\n", array_filter([
            match ($delivery->method) {
                EcommerceOrderDelivery::DOWNLOAD    => "{$line->title}: ready to download from your order's page.",
                EcommerceOrderDelivery::LICENSE_KEY => "{$line->title} - your license ".(count($keys) > 1 ? 'keys' : 'key').":\n".implode("\n", $keys),
                default                             => "{$line->title}:",
            },
            $delivery->note,
            $line->item?->delivery_instructions,
        ]));
    }

    /** How the order is paid: matched by $reference when the customer gave one, else $owed paid ahead, on pickup or on delivery. */
    private function howToPay(EcommerceOrder $order, string $owed, ?string $reference): string
    {
        $type = $order->payment_snapshot['type'] ?? null;

        return match (true) {
            in_array($type, ['bank_transfer', 'mobile_money'], true) && $reference !== null => "We will match your payment, reference {$reference}, and confirm it.",
            in_array($type, ['bank_transfer', 'mobile_money'], true)                       => "Pay {$owed}, with your order number, {$order->number}, as the payment's reference.",
            $type === 'pay_on_pickup'                                                       => "You pay {$owed} when you collect it.",
            default                                                                         => "You pay {$owed} on delivery.",
        };
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
