<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommerceFulfilment;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderEmail;
use App\Models\User\EcommerceOrderLine;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\EcommerceFulfilmentRepository;
use App\Repositories\User\EcommerceOrderRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\BusinessCalendar;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An online order's shipments and pickup (Doc 19 section 19.27 - Phase G1),
 * under Manage Fulfilment, for a confirmed order - or one being fulfilled -
 * with products that ship; never while it is pending, on hold or cancelled.
 * Payment is not needed first: cash on delivery ships unpaid.
 *
 * - review(): how it reaches its customer, and each product that ships with
 *   what is sent of it and what is left;
 * - ship(): a shipment of some or all of what is left - its day, carrier,
 *   tracking number and link, and notes for the business alone - Shipped;
 * - ready(): a pickup order ready to collect, the whole of it;
 * - update(): a shipment's carrier, tracking and notes corrected;
 * - complete(): a shipment Delivered, or a pickup Collected, on a day;
 * - cancel(): one not yet delivered or collected Cancelled, with why, its
 *   quantities to fulfil again.
 *
 * Each moves the order's fulfilment and status (EcommerceOrderProgress) and
 * is in its timeline; the customer is e-mailed what was sent, that it is
 * ready, and that it arrived - not a correction or a cancelling, which the
 * business tells them. No stock moves, and a fulfilment is never a sale.
 */
class EcommerceOrderFulfilments
{
    use RefusesConflicts;

    public function __construct(
        protected EcommerceOrderService $orderService,
        protected EcommerceOrderRepository $orders,
        protected EcommerceFulfilmentRepository $fulfilments,
        protected EcommerceOrderProgress $progress,
        protected StoreOrderMailer $mailer,
        protected BusinessCalendar $calendar,
    ) {}

    /**
     * What fulfilling order $id would do now.
     *
     * @return array{order: EcommerceOrder, lines: list<array{line: EcommerceOrderLine, sent: string, left: string}>, today: string}
     */
    public function review(Workspace $workspace, int $id): array
    {
        $order = $this->orders->forFulfilling($this->orderService->find($workspace, $id), false);

        $this->checkFulfillable($order);

        return ['order' => $order, 'lines' => $this->left($order), 'today' => $this->calendar->today($workspace)->toDateString()];
    }

    /** $data as ShipEcommerceOrderRequest takes it: lines [{id, quantity}], shipped_on, carrier, tracking_number, tracking_url and notes. */
    public function ship(Workspace $workspace, User $user, int $id, array $data): EcommerceOrder
    {
        $found = $this->orderService->find($workspace, $id);
        $today = $this->calendar->today($workspace)->toDateString();

        DB::transaction(function () use ($user, $found, $data, $today) {
            $order = $this->orders->forFulfilling($found, true);

            $this->checkFulfillable($order);

            if ($order->delivery !== EcommerceOrder::SHIPPING) {
                $this->refuse("Order {$order->number} is collected, not shipped: make it ready for pickup.");
            }

            $this->checkDay('shipped_on', $data['shipped_on'], $order, $today);

            $left = collect($this->left($order))->keyBy(fn (array $row) => $row['line']->id);
            $rows = [];

            foreach ($data['lines'] as $at => $row) {
                $line     = $left->get((int) $row['id']) ?? throw ValidationException::withMessages(["lines.{$at}.id" => 'Choose one of the order\'s products that ship.']);
                $quantity = bcadd((string) $row['quantity'], '0', 3);

                if (bccomp($quantity, $line['left'], 3) > 0) {
                    throw ValidationException::withMessages(["lines.{$at}.quantity" => 'Only '.$this->number($line['left'])." of {$line['line']->title} are left to ship."]);
                }

                if (bccomp($quantity, '0', 3) > 0) {
                    $rows[$line['line']->id] = bcadd($rows[$line['line']->id] ?? '0', $quantity, 3);
                }
            }

            if ($rows === []) {
                throw ValidationException::withMessages(['lines' => 'Ship at least one product.']);
            }

            $shipment = $this->fulfilments->create($order, [
                'kind'            => EcommerceFulfilment::SHIPMENT,
                'status'          => EcommerceFulfilment::SHIPPED,
                'location_id'     => $order->location_id,
                'fulfilled_on'    => $data['shipped_on'],
                'carrier'         => $this->text($data['carrier'] ?? null),
                'tracking_number' => $this->text($data['tracking_number'] ?? null),
                'tracking_url'    => $this->text($data['tracking_url'] ?? null),
                'notes'           => $this->text($data['notes'] ?? null),
                'created_by'      => $user->id,
            ], $rows);

            $this->progress->follow($order);

            $this->mailer->queue($order, EcommerceOrderEmail::ORDER_SHIPPED, $shipment->tracking_url, ['fulfilment' => $shipment->id, 'rest' => $order->fulfilment_status === EcommerceOrder::PARTIALLY_FULFILLED]);

            ActivityLogService::record('updated', 'Shipped '.$this->words($order, $rows)." of {$this->describe($order)} as shipment {$shipment->sequence}"
                .($shipment->carrier ? " by {$shipment->carrier}" : '').($shipment->tracking_number ? ", tracking {$shipment->tracking_number}" : ''), $order, ['source' => 'api']);
        });

        return $this->orderService->page($workspace, $id);
    }

    /** $data as ReadyEcommerceOrderRequest takes it: ready_on and notes. The whole order, ready to collect. */
    public function ready(Workspace $workspace, User $user, int $id, array $data): EcommerceOrder
    {
        $found = $this->orderService->find($workspace, $id);
        $today = $this->calendar->today($workspace)->toDateString();

        DB::transaction(function () use ($user, $found, $data, $today) {
            $order = $this->orders->forFulfilling($found, true);

            $this->checkFulfillable($order);

            if ($order->delivery !== EcommerceOrder::PICKUP) {
                $this->refuse("Order {$order->number} is shipped, not collected: ship its products.");
            }

            $this->checkDay('ready_on', $data['ready_on'], $order, $today);

            $rows = collect($this->left($order))->filter(fn (array $row) => bccomp($row['left'], '0', 3) > 0)->mapWithKeys(fn (array $row) => [$row['line']->id => $row['left']])->all();

            if ($rows === []) {
                $this->refuse("Order {$order->number} is ready for pickup already.");
            }

            $this->fulfilments->create($order, [
                'kind'         => EcommerceFulfilment::PICKUP,
                'status'       => EcommerceFulfilment::READY,
                'location_id'  => $order->location_id,
                'fulfilled_on' => $data['ready_on'],
                'notes'        => $this->text($data['notes'] ?? null),
                'created_by'   => $user->id,
            ], $rows);

            $this->progress->follow($order);

            $this->mailer->queue($order, EcommerceOrderEmail::ORDER_READY, null, []);

            ActivityLogService::record('updated', "Made {$this->describe($order)} ready for pickup at ".($order->delivery_snapshot['name'] ?? $order->location?->name ?? 'the shop'), $order, ['source' => 'api']);
        });

        return $this->orderService->page($workspace, $id);
    }

    /** $data as UpdateEcommerceFulfilmentRequest takes it: a shipment's carrier, tracking_number, tracking_url and notes, corrected. */
    public function update(Workspace $workspace, int $id, int $fulfilmentId, array $data): EcommerceOrder
    {
        $found = $this->orderService->find($workspace, $id);

        DB::transaction(function () use ($found, $fulfilmentId, $data) {
            [$order, $fulfilment] = $this->locked($found, $fulfilmentId);

            if ($fulfilment->status === EcommerceFulfilment::CANCELLED) {
                $this->refuse(ucfirst($fulfilment->label())." of order {$order->number} is cancelled.");
            }

            $fields  = ['carrier' => 'carrier', 'tracking_number' => 'tracking number', 'tracking_url' => 'tracking link', 'notes' => 'notes'];
            $now     = array_map(fn (string $key) => $this->text($data[$key] ?? null), array_combine(array_keys($fields), array_keys($fields)));
            $changes = collect($fields)->filter(fn (string $label, string $key) => $now[$key] !== $fulfilment->{$key})
                ->map(fn (string $label, string $key) => ['setting' => $label, 'from' => $fulfilment->{$key}, 'to' => $now[$key]])->values()->all();

            if ($changes === []) {
                return;
            }

            $this->fulfilments->update($fulfilment, $now);

            ActivityLogService::record('updated', "Changed the details of {$fulfilment->label()} of {$this->describe($order)}", $order, ['source' => 'api', 'changes' => $changes]);
        });

        return $this->orderService->page($workspace, $id);
    }

    /** A shipment delivered, or a pickup collected, on $day. */
    public function complete(Workspace $workspace, int $id, int $fulfilmentId, string $day): EcommerceOrder
    {
        $found = $this->orderService->find($workspace, $id);
        $today = $this->calendar->today($workspace)->toDateString();

        DB::transaction(function () use ($found, $fulfilmentId, $day, $today) {
            [$order, $fulfilment] = $this->locked($found, $fulfilmentId);

            if (! $fulfilment->isOpen()) {
                $this->refuse(ucfirst($fulfilment->label())." of order {$order->number} is {$fulfilment->status} already.");
            }

            if ($day > $today || $day < $fulfilment->fulfilled_on->toDateString()) {
                throw ValidationException::withMessages(['completed_on' => 'Give a day from '.$fulfilment->fulfilled_on->toDateString().' to today.']);
            }

            $collected = $fulfilment->kind === EcommerceFulfilment::PICKUP;

            $this->fulfilments->update($fulfilment, ['status' => $collected ? EcommerceFulfilment::COLLECTED : EcommerceFulfilment::DELIVERED, 'completed_on' => $day]);

            $this->progress->follow($order);

            $this->mailer->queue($order, EcommerceOrderEmail::ORDER_DELIVERED, null, ['fulfilment' => $fulfilment->id]);

            ActivityLogService::record('updated', $collected ? "The customer collected {$this->describe($order)}" : "Delivered {$fulfilment->label()} of {$this->describe($order)}", $order, ['source' => 'api']);
        });

        return $this->orderService->page($workspace, $id);
    }

    /** A shipment not yet delivered, or a pickup not yet collected, cancelled with $reason. */
    public function cancel(Workspace $workspace, int $id, int $fulfilmentId, string $reason): EcommerceOrder
    {
        $found = $this->orderService->find($workspace, $id);

        DB::transaction(function () use ($found, $fulfilmentId, $reason) {
            [$order, $fulfilment] = $this->locked($found, $fulfilmentId);

            if (! $fulfilment->isOpen()) {
                $this->refuse(ucfirst($fulfilment->label())." of order {$order->number} is {$fulfilment->status} already: it is not cancelled.");
            }

            $this->fulfilments->update($fulfilment, ['status' => EcommerceFulfilment::CANCELLED, 'cancel_reason' => $reason]);

            $this->progress->follow($order);

            ActivityLogService::record('updated', "Cancelled {$fulfilment->label()} of {$this->describe($order)}: {$reason}", $order, ['source' => 'api']);
        });

        return $this->orderService->page($workspace, $id);
    }

    /** Each of $order's products that ship, with what is sent of it and what is left. */
    private function left(EcommerceOrder $order): array
    {
        $sent = $this->progress->sent($order, $order->fulfilments);

        return $this->progress->shippable($order)->map(fn (EcommerceOrderLine $line) => [
            'line' => $line,
            'sent' => $sent[$line->id] ?? '0.000',
            'left' => bcsub((string) $line->quantity, $sent[$line->id] ?? '0', 3),
        ])->values()->all();
    }

    /** Confirmed, or being fulfilled - not pending, held, cancelled or completed - with products that ship. */
    private function checkFulfillable(EcommerceOrder $order): void
    {
        match ($order->status) {
            EcommerceOrder::PENDING   => $this->refuse("Order {$order->number} is pending: confirm it first."),
            EcommerceOrder::ON_HOLD   => $this->refuse("Order {$order->number} is on hold: release it first."),
            EcommerceOrder::CANCELLED => $this->refuse("Order {$order->number} is cancelled."),
            EcommerceOrder::COMPLETED => $this->refuse("Order {$order->number} is completed."),
            default                   => null,
        };

        if ($this->progress->shippable($order)->isEmpty()) {
            $this->refuse("Order {$order->number} has nothing to ship or collect: its digital products and services are delivered apart.");
        }
    }

    /** A shipment's or readiness's day: today or before, and not before the order was placed. */
    private function checkDay(string $field, string $day, EcommerceOrder $order, string $today): void
    {
        $placed = $order->placed_at->copy()->setTimezone($this->calendar->timezone($order->workspace))->toDateString();

        if ($day > $today || $day < $placed) {
            throw ValidationException::withMessages([$field => "Give a day from {$placed} to today."]);
        }
    }

    /** $order and its fulfilment $id, both locked: the order neither cancelled nor on hold. */
    private function locked(EcommerceOrder $found, int $fulfilmentId): array
    {
        $order      = $this->orders->forFulfilling($found, true);
        $fulfilment = $this->fulfilments->findFor($order, $fulfilmentId) ?? abort(404, 'There is no such shipment or pickup.');

        match ($order->status) {
            EcommerceOrder::CANCELLED => $this->refuse("Order {$order->number} is cancelled."),
            EcommerceOrder::ON_HOLD   => $this->refuse("Order {$order->number} is on hold: release it first."),
            default                   => null,
        };

        return [$order, $fulfilment];
    }

    /** $rows ([order line id => quantity]) in words: "2 × Ceramic Mug and 1 × Tea Towel". */
    private function words(EcommerceOrder $order, array $rows): string
    {
        $lines = $order->lines->keyBy('id');
        $parts = array_map(fn (int $id, string $quantity) => $this->number($quantity).' × '.$lines->get($id)?->title, array_keys($rows), $rows);

        return count($parts) > 1 ? implode(', ', array_slice($parts, 0, -1)).' and '.end($parts) : $parts[0];
    }

    /** A quantity without its trailing zeros: 2, 1.5. */
    private function number(string $quantity): string
    {
        return rtrim(rtrim($quantity, '0'), '.') ?: '0';
    }

    private function text(mixed $value): ?string
    {
        return trim((string) $value) ?: null;
    }

    private function describe(EcommerceOrder $order): string
    {
        return $this->orderService->describe($order);
    }
}
