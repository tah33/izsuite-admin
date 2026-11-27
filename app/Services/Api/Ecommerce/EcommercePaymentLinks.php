<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderEmail;
use App\Models\User\EcommercePaymentLink;
use App\Models\User\EcommercePaymentMethod;
use App\Models\User\Storefront;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\EcommerceOrderRepository;
use App\Repositories\User\EcommercePaymentLinkRepository;
use App\Repositories\User\EcommerceSettingRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\BusinessCalendar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Payment links for online orders (Doc 19 section 19.23 - Phase F6a), under
 * Confirm Orders, for an order that takes a payment - neither cancelled nor
 * on hold, and owing something (EcommerceOrderPayments::checkPayable()):
 *
 * - review(): what sending one would ask - what the order owes (the amount,
 *   or with Settings → eCommerce's paying in part on, the most), the store's
 *   methods it may offer, the days it works, and to whom it goes;
 * - send(): the link - its amount, methods and days as given, working to
 *   the end of its last day in the business's time zone - made with a token
 *   of 64 random characters, kept only hashed, replacing one still active;
 *   e-mailed to the customer with its page on the store; in the order's
 *   timeline;
 * - revoke(): an active link closed by hand. Cancelling the order revokes
 *   its links (EcommerceOrderCancellation), and so does an edit that changes
 *   its total (EcommerceOrderEdits::retotal()); paying it in full marks them
 *   Paid (EcommerceOrderPaymentStatus).
 *
 * A link may offer the methods paid from afar - bank transfer and mobile
 * money - as checkout offers them for this order. It is never a sale of its
 * own: what it brings in is the order's payment, recorded as F3 records one.
 */
class EcommercePaymentLinks
{
    use RefusesConflicts;

    public function __construct(
        protected EcommerceOrderService $orderService,
        protected EcommerceOrderRepository $orders,
        protected EcommercePaymentLinkRepository $links,
        protected EcommerceOrderPayments $payments,
        protected EcommercePaymentOptions $paymentOptions,
        protected EcommerceSettingRepository $settings,
        protected StoreOrderMailer $mailer,
        protected BusinessCalendar $calendar,
    ) {}

    /**
     * What sending order $id a payment link would ask - and the business's
     * today, the day its days count from.
     *
     * @return array{order: EcommerceOrder, owed: string, partial: bool, days: int, today: string, methods: Collection, to: string}
     */
    public function review(Workspace $workspace, int $id): array
    {
        $order    = $this->orders->forConfirming($this->orderService->find($workspace, $id), false);
        $settings = $this->settings->forWorkspace($workspace);

        $this->payments->checkPayable($order);

        return [
            'order'   => $order,
            'owed'    => $this->payments->owed($order),
            'partial' => (bool) $settings->partial_payments,
            'days'    => (int) $settings->payment_link_days,
            'today'   => $this->calendar->today($workspace)->toDateString(),
            'methods' => $this->methods($workspace, $order),
            'to'      => $order->customer['email'],
        ];
    }

    /** $data as SendEcommercePaymentLinkRequest takes it: amount, days and payment_method_ids. The order, as its page shows it. */
    public function send(Workspace $workspace, User $user, int $id, array $data): EcommerceOrder
    {
        $found    = $this->orderService->find($workspace, $id);
        $settings = $this->settings->forWorkspace($workspace);

        DB::transaction(function () use ($workspace, $user, $found, $data, $settings) {
            $order = $this->orders->forConfirming($found, true);

            $this->payments->checkPayable($order);

            $owed   = $this->payments->owed($order);
            $amount = bcadd((string) $data['amount'], '0', 2);

            match (true) {
                bccomp($amount, $owed, 2) > 0                                     => throw ValidationException::withMessages(['amount' => "Order {$order->number} owes {$order->currency} {$owed}: ask that much at most."]),
                ! $settings->partial_payments && bccomp($amount, $owed, 2) !== 0 => throw ValidationException::withMessages(['amount' => "A link asks all that order {$order->number} owes, {$order->currency} {$owed}, while paying in part is off in Settings → eCommerce."]),
                default                                                           => null,
            };

            $wanted = array_values(array_unique(array_map('intval', $data['payment_method_ids'])));
            $chosen = $this->methods($workspace, $order)->filter(fn (EcommercePaymentMethod $method) => in_array($method->id, $wanted, true))->values();

            if ($chosen->count() !== count($wanted)) {
                throw ValidationException::withMessages(['payment_method_ids' => 'Choose among the ways to pay a link may offer this order.']);
            }

            // One link at a time: a newer one replaces the one still active.
            $this->links->revokeActive($order, $user->id, EcommercePaymentLink::REPLACED);

            $token = bin2hex(random_bytes(32));
            $link  = $this->links->create($order, [
                'token_hash'      => hash('sha256', $token),
                'amount'          => $amount,
                'currency'        => $order->currency,
                'payment_methods' => $chosen->map(fn (EcommercePaymentMethod $method) => ['id' => $method->id, 'name' => $method->name])->all(),
                // To the end of its last day where the business is, kept in the app's time zone as every moment is.
                'expires_at'      => $this->calendar->today($workspace)->addDays((int) $data['days'])->endOfDay()->setTimezone(config('app.timezone')),
                'status'          => EcommercePaymentLink::ACTIVE,
                'sent_to'         => $order->customer['email'],
                'created_by'      => $user->id,
            ]);

            $this->mailer->queue($order, EcommerceOrderEmail::PAYMENT_LINK, self::url($order->storefront, $token), [
                'amount'  => (string) $link->amount,
                'expires' => $link->expires_at->toIso8601String(),
                'methods' => $chosen->pluck('name')->all(),
            ]);

            // Logged here, by what happened, so LogApiWriteActivity does not add its own vaguer entry.
            ActivityLogService::record('updated', "Sent a payment link of {$link->currency} {$link->amount} for ".$this->orderService->describe($order)." to {$link->sent_to}", $order, ['source' => 'api']);
        });

        return $this->orderService->page($workspace, $id);
    }

    /** Order $id's payment link $linkId, active, revoked by $user. The order, as its page shows it. */
    public function revoke(Workspace $workspace, User $user, int $id, int $linkId): EcommerceOrder
    {
        $found = $this->orderService->find($workspace, $id);

        DB::transaction(function () use ($user, $found, $linkId) {
            $order = $this->orders->forConfirming($found, true);
            $link  = $this->links->findFor($order, $linkId) ?? abort(404, 'There is no such payment link.');

            if ($link->state() !== EcommercePaymentLink::ACTIVE) {
                $this->refuse("The payment link of {$link->currency} {$link->amount} is {$link->state()} already.");
            }

            $this->links->update($link, ['status' => EcommercePaymentLink::REVOKED, 'revoked_at' => now(), 'revoked_by' => $user->id, 'revoked_why' => EcommercePaymentLink::BY_HAND]);

            ActivityLogService::record('updated', "Revoked the payment link of {$link->currency} {$link->amount} for ".$this->orderService->describe($order), $order, ['source' => 'api']);
        });

        return $this->orderService->page($workspace, $id);
    }

    /**
     * The store's methods a link may offer $order: as checkout offers them
     * for it - in its currency, to its country, for its total and delivery -
     * those paid from afar, its own method first.
     */
    public function methods(Workspace $workspace, EcommerceOrder $order): Collection
    {
        $country = $order->delivery === EcommerceOrder::SHIPPING ? ($order->shipping_address['country'] ?? null) : ($order->billing_address['country'] ?? null);
        $offered = $this->paymentOptions->for($workspace, $order->currency, $country, (string) $order->grand_total, match ($order->delivery) {
            EcommerceOrder::SHIPPING => 'shipped',
            EcommerceOrder::PICKUP   => 'pickup',
            default                  => EcommercePaymentOptions::NOTHING,
        });

        return $offered->filter(fn (EcommercePaymentMethod $method) => in_array($method->type, EcommercePaymentLink::METHOD_TYPES, true))
            ->sortBy(fn (EcommercePaymentMethod $method) => $method->id === $order->ecommerce_payment_method_id ? 0 : 1)
            ->values();
    }

    /** A link's page on its order's store, on izSuite's frontend - as an order's own page is (StorefrontOrderPlacement::linkOf()). */
    public static function url(Storefront $storefront, string $token): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/store/'.$storefront->code.'/pay/'.$token;
    }
}
