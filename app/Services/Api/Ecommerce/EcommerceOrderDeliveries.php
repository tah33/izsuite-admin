<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderDelivery;
use App\Models\User\EcommerceOrderEmail;
use App\Models\User\EcommerceOrderLine;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\EcommerceOrderDeliveryRepository;
use App\Repositories\User\EcommerceOrderRepository;
use App\Repositories\User\EcommerceSettingRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\BusinessCalendar;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An online order's digital products delivered and its services performed
 * (Doc 19 sections 19.28 and 19.29 - Phase G3a):
 *
 * - release(): once the order is paid in full, however the payment came in
 *   (EcommerceOrderPaymentStatus), each digital product as its item says - a
 *   download or an external link delivered at once, a license key or a
 *   delivery by hand (or none said) waiting for the business - and the
 *   customer e-mailed what was delivered;
 * - deliver(): under Manage Fulfilment, a waiting product's license keys
 *   given, or one delivered by hand with a note to the customer - or a
 *   revoked one's access given again - on an order paid in full, neither
 *   held nor cancelled (19.28: unpaid orders receive no protected content);
 * - perform(): a service performed on a day, with a note to the customer if
 *   wanted, once the order is confirmed - paid or not, as a shipment;
 * - revoke(): a delivered product's access withdrawn, with why, for the
 *   business alone; the customer is not e-mailed;
 * - renew() (Phase G3b): a delivered download's days and count afresh.
 *
 * A download is delivered with its terms (Phase G3b): until when it works and
 * how many times each file, as Settings → eCommerce says at the time.
 *
 * Each moves the order's fulfilment and status (EcommerceOrderProgress) and
 * is in its timeline. Nothing moves stock, and nothing is a sale of its own.
 */
class EcommerceOrderDeliveries
{
    use RefusesConflicts;

    public function __construct(
        protected EcommerceOrderService $orderService,
        protected EcommerceOrderRepository $orders,
        protected EcommerceOrderDeliveryRepository $deliveries,
        protected EcommerceOrderProgress $progress,
        protected StoreOrderMailer $mailer,
        protected BusinessCalendar $calendar,
        protected EcommerceSettingRepository $settings,
    ) {}

    /**
     * Inside the transaction that settled its invoice, $order paid in full:
     * each of its digital products not delivered yet, as its item says. The
     * order's fulfilment follows after (EcommerceOrderPaymentStatus).
     */
    public function release(EcommerceOrder $order): void
    {
        $order = $this->orders->forFulfilling($order, true);

        if ($order->status === EcommerceOrder::CANCELLED) {
            return;
        }

        $delivered = [];

        foreach ($this->progress->deliverable($order) as $line) {
            if ($line->isService() || $order->deliveries->contains('ecommerce_order_line_id', $line->id)) {
                continue;
            }

            $method = $line->deliveryMethod();
            $atOnce = in_array($method, EcommerceOrderDelivery::AT_ONCE, true);

            $this->deliveries->create($order, $line, [
                'kind'         => EcommerceOrderDelivery::DIGITAL,
                'method'       => $method,
                'status'       => $atOnce ? EcommerceOrderDelivery::DELIVERED : EcommerceOrderDelivery::WAITING,
                'delivered_at' => $atOnce ? now() : null,
                // Phase G3b: a download's days and count, as the settings say now.
                ...($method === EcommerceOrderDelivery::DOWNLOAD ? $this->downloadTerms($order) : []),
            ]);

            if ($atOnce) {
                $delivered[] = $line;
            }
        }

        if ($delivered !== []) {
            $this->mailer->queue($order, EcommerceOrderEmail::DIGITAL_DELIVERED, null, ['lines' => array_map(fn (EcommerceOrderLine $line) => $line->id, $delivered)]);

            ActivityLogService::record('updated', 'Delivered '.$this->names($delivered)." of {$this->describe($order)}, paid in full", $order, ['source' => 'api']);
        }
    }

    /** $data as DeliverEcommerceProductRequest takes it: license_keys and note. */
    public function deliver(Workspace $workspace, User $user, int $id, int $lineId, array $data): EcommerceOrder
    {
        $found = $this->orderService->find($workspace, $id);

        DB::transaction(function () use ($user, $found, $lineId, $data) {
            [$order, $line, $delivery] = $this->locked($found, $lineId);

            if ($line->isService()) {
                $this->refuse("{$line->title} is a service: mark it performed.");
            }

            if ($order->payment_status !== EcommerceOrder::PAID) {
                $this->refuse("Order {$order->number} is not paid in full: its digital products are delivered once it is.");
            }

            if ($delivery?->status === EcommerceOrderDelivery::DELIVERED) {
                $this->refuse("{$line->title} of order {$order->number} is delivered already.");
            }

            $method = $delivery?->method ?? $line->deliveryMethod();
            $keys   = array_values(array_filter(array_map(fn ($key) => trim((string) $key), $data['license_keys'] ?? []), fn (string $key) => $key !== ''));
            $note   = trim((string) ($data['note'] ?? '')) ?: null;

            if ($method === EcommerceOrderDelivery::LICENSE_KEY && $keys === []) {
                throw ValidationException::withMessages(['license_keys' => 'Give its license key: one for each one bought, usually.']);
            }

            if ($method === EcommerceOrderDelivery::MANUAL && $note === null) {
                throw ValidationException::withMessages(['note' => 'Say how it reached the customer: they are e-mailed this note.']);
            }

            $again  = $delivery?->status === EcommerceOrderDelivery::REVOKED;
            $fields = [
                'status'        => EcommerceOrderDelivery::DELIVERED,
                'license_keys'  => $method === EcommerceOrderDelivery::LICENSE_KEY ? $keys : null,
                'note'          => in_array($method, [EcommerceOrderDelivery::LICENSE_KEY, EcommerceOrderDelivery::MANUAL], true) ? $note : null,
                'delivered_at'  => now(),
                'delivered_by'  => $user->id,
                'revoked_at'    => null,
                'revoked_by'    => null,
                'revoke_reason' => null,
                // Phase G3b: a download given again starts afresh, as the settings say now.
                ...($method === EcommerceOrderDelivery::DOWNLOAD ? $this->downloadTerms($order) : []),
            ];

            $delivery
                ? $this->deliveries->update($delivery, $fields)
                : $this->deliveries->create($order, $line, ['kind' => EcommerceOrderDelivery::DIGITAL, 'method' => $method, ...$fields]);

            $this->progress->follow($order);

            $this->mailer->queue($order, EcommerceOrderEmail::DIGITAL_DELIVERED, null, ['lines' => [$line->id]]);

            ActivityLogService::record('updated', match (true) {
                $again                                          => "Gave access to {$line->title} of {$this->describe($order)} again",
                $method === EcommerceOrderDelivery::LICENSE_KEY => 'Gave the license '.(count($keys) > 1 ? 'keys' : 'key')." of {$line->title} of {$this->describe($order)}",
                $method === EcommerceOrderDelivery::MANUAL      => "Delivered {$line->title} of {$this->describe($order)} by hand",
                default                                         => "Delivered {$line->title} of {$this->describe($order)}",
            }, $order, ['source' => 'api']);
        });

        return $this->orderService->page($workspace, $id);
    }

    /** $data as PerformEcommerceServiceRequest takes it: performed_on and note. */
    public function perform(Workspace $workspace, User $user, int $id, int $lineId, array $data): EcommerceOrder
    {
        $found = $this->orderService->find($workspace, $id);
        $today = $this->calendar->today($workspace)->toDateString();

        DB::transaction(function () use ($user, $found, $lineId, $data, $today) {
            [$order, $line, $delivery] = $this->locked($found, $lineId);

            if (! $line->isService()) {
                $this->refuse("{$line->title} is a digital product: it is delivered, not performed.");
            }

            if ($order->status === EcommerceOrder::PENDING) {
                $this->refuse("Order {$order->number} is pending: confirm it first.");
            }

            if ($delivery !== null) {
                $this->refuse("{$line->title} of order {$order->number} is performed already.");
            }

            $placed = $order->placed_at->copy()->setTimezone($this->calendar->timezone($order->workspace))->toDateString();

            if ($data['performed_on'] > $today || $data['performed_on'] < $placed) {
                throw ValidationException::withMessages(['performed_on' => "Give a day from {$placed} to today."]);
            }

            $this->deliveries->create($order, $line, [
                'kind'         => EcommerceOrderDelivery::SERVICE,
                'status'       => EcommerceOrderDelivery::PERFORMED,
                'performed_on' => $data['performed_on'],
                'note'         => trim((string) ($data['note'] ?? '')) ?: null,
                'delivered_at' => now(),
                'delivered_by' => $user->id,
            ]);

            $this->progress->follow($order);

            $this->mailer->queue($order, EcommerceOrderEmail::SERVICE_PERFORMED, null, ['line' => $line->id]);

            ActivityLogService::record('updated', "Performed {$line->title} of {$this->describe($order)} on {$data['performed_on']}", $order, ['source' => 'api']);
        });

        return $this->orderService->page($workspace, $id);
    }

    /** A delivered digital product's access withdrawn, with $reason - the customer not e-mailed. */
    public function revoke(Workspace $workspace, User $user, int $id, int $lineId, string $reason): EcommerceOrder
    {
        $found = $this->orderService->find($workspace, $id);

        DB::transaction(function () use ($user, $found, $lineId, $reason) {
            [$order, $line, $delivery] = $this->locked($found, $lineId);

            if ($line->isService()) {
                $this->refuse("{$line->title} is a service: there is no access to revoke.");
            }

            if ($delivery?->status !== EcommerceOrderDelivery::DELIVERED) {
                $this->refuse("{$line->title} of order {$order->number} is not delivered: there is no access to revoke.");
            }

            $this->deliveries->update($delivery, ['status' => EcommerceOrderDelivery::REVOKED, 'revoked_at' => now(), 'revoked_by' => $user->id, 'revoke_reason' => $reason]);

            $this->progress->follow($order);

            ActivityLogService::record('updated', "Revoked access to {$line->title} of {$this->describe($order)}: {$reason}", $order, ['source' => 'api']);
        });

        return $this->orderService->page($workspace, $id);
    }

    /**
     * Phase G3b: a delivered download given more - its days and its count
     * afresh, as Settings → eCommerce says now; the downloads before stay on
     * record but no longer count. The customer is e-mailed it is ready again.
     */
    public function renew(Workspace $workspace, int $id, int $lineId): EcommerceOrder
    {
        $found = $this->orderService->find($workspace, $id);

        DB::transaction(function () use ($found, $lineId) {
            [$order, $line, $delivery] = $this->locked($found, $lineId);

            if ($delivery?->method !== EcommerceOrderDelivery::DOWNLOAD || $delivery->status !== EcommerceOrderDelivery::DELIVERED) {
                $this->refuse("{$line->title} of order {$order->number} is not a delivered download: there are no downloads to give.");
            }

            $this->deliveries->update($delivery, $this->downloadTerms($order));

            $this->mailer->queue($order, EcommerceOrderEmail::DIGITAL_DELIVERED, null, ['lines' => [$line->id]]);

            ActivityLogService::record('updated', "Gave more downloads of {$line->title} of {$this->describe($order)}", $order, ['source' => 'api']);
        });

        return $this->orderService->page($workspace, $id);
    }

    /**
     * Phase G3b: a download's terms from now, as Settings → eCommerce says:
     * it works to the end of its last day where the business is - none for no
     * limit - each file so many times, its count starting now.
     */
    private function downloadTerms(EcommerceOrder $order): array
    {
        $settings = $this->settings->forWorkspace($order->workspace);

        return [
            'expires_at'     => $settings->download_days ? $this->calendar->today($order->workspace)->addDays($settings->download_days)->endOfDay()->setTimezone(config('app.timezone')) : null,
            'download_limit' => $settings->download_limit,
            'counted_from'   => now(),
        ];
    }

    /** $order locked, its product $lineId that does not ship, and that product's delivery - the order neither cancelled nor on hold. */
    private function locked(EcommerceOrder $found, int $lineId): array
    {
        $order = $this->orders->forFulfilling($found, true);
        $line  = $this->progress->deliverable($order)->get($lineId) ?? abort(404, 'There is no such digital product or service on the order.');

        match ($order->status) {
            EcommerceOrder::CANCELLED => $this->refuse("Order {$order->number} is cancelled."),
            EcommerceOrder::ON_HOLD   => $this->refuse("Order {$order->number} is on hold: release it first."),
            default                   => null,
        };

        return [$order, $line, $order->deliveries->firstWhere('ecommerce_order_line_id', $line->id)];
    }

    /** $lines' titles in words: "E-Book", "E-Book and Course", "E-Book, Course and Photos". */
    private function names(array $lines): string
    {
        $titles = array_map(fn (EcommerceOrderLine $line) => $line->title, $lines);

        return count($titles) > 1 ? implode(', ', array_slice($titles, 0, -1)).' and '.end($titles) : $titles[0];
    }

    private function describe(EcommerceOrder $order): string
    {
        return $this->orderService->describe($order);
    }
}
