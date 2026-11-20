<?php

namespace App\Services\Api\Pos;

use App\Models\User\Contact;
use App\Models\User\PosHeldOrder;
use App\Models\User\PosSession;
use App\Models\User\Workspace;
use App\Repositories\User\ItemRepository;
use App\Repositories\User\PosHeldOrderRepository;
use App\Repositories\User\PosSettingRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use App\Services\User\Workspaces\WorkspaceSequence;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Holding and resuming POS orders (Doc 18 section 18.22 - Phase D1), at the
 * user's own register, in their open session (PosCounter).
 *
 * A cart is held as it stands - its customer, lines with their notes,
 * prices typed over and discounts, the order's discount and note - priced
 * then, numbered H-00001, and kept for Settings → Point of Sale's hours,
 * or until it is resumed or cancelled. It is no sale: it reaches neither
 * Sales, stock nor the payment accounts.
 *
 * Resuming - at the register it was held at, by whoever may hold and
 * resume orders - loads it into the cart priced again now. What changed is
 * said before it is resumed: a price that is not what it was, an item no
 * longer sold - left out - a customer no longer sold to - back to the
 * walk-in one - and a price typed over or a discount the user may not give,
 * left out. Cancelling one needs Cancel Held Orders, and a reason. Each is
 * logged on the held order; one past its time expires by the scheduler.
 */
class PosHoldService
{
    use RefusesConflicts;

    public function __construct(
        protected PosCounter $counter,
        protected PosPricing $pricing,
        protected PosDiscounts $discounts,
        protected PosHeldOrderRepository $held,
        protected PosSettingRepository $settings,
        protected ItemRepository $items,
        protected WorkspaceSequence $sequence,
        protected WorkspaceAccess $access,
    ) {}

    /** The orders held at the user's register, still waiting, the latest first. */
    public function list(Workspace $workspace): Collection
    {
        return $this->held->waitingAt($this->counter->selling($workspace)->register);
    }

    /** $data is validated (see HoldPosOrderRequest): the cart, as a sale takes it. The order held. */
    public function hold(Workspace $workspace, array $data): PosHeldOrder
    {
        $session  = $this->counter->selling($workspace);
        $register = $session->register;
        $today    = $this->counter->today($workspace);
        $rate     = $this->counter->rate($workspace, $session, $today);
        $contact  = isset($data['contact_id']) ? $this->counter->customer($workspace, (int) $data['contact_id']) : null;
        $lines    = $this->counter->lines($workspace, $data['lines']);
        $discount = $this->counter->discount($data);
        $priced   = $this->pricing->priced($workspace, $register, $contact, $lines, $rate, $today, $discount);

        // What the user may not give is not held either.
        $this->discounts->check($workspace, $lines, $priced, $discount, false, $data['approvals'] ?? []);

        $hours = $this->settings->forWorkspace($workspace)->hold_hours;
        $held  = $this->held->create($workspace, [
            'number'          => 'H-'.sprintf('%05d', $this->sequence->next($workspace->id, 'pos_hold')),
            'pos_register_id' => $register->id,
            'pos_session_id'  => $session->id,
            'location_id'     => $register->location_id,
            'cashier_id'      => $this->access->user()->id,
            'contact_id'      => $contact?->id,
            'cart'            => [
                'lines'    => array_map(fn (array $line) => [
                    'item_id' => $line['item']->id, 'name' => $line['item']->name, 'unit' => $line['item']->unit,
                    ...collect($line)->only(['quantity', 'note', 'price', 'override_reason', 'discount_type', 'discount_value'])->all(),
                ], $lines),
                'discount' => $discount === null ? null : [...$discount, 'reason' => trim((string) ($data['discount_reason'] ?? '')) ?: null],
                'note'     => trim((string) ($data['note'] ?? '')) ?: null,
            ],
            'prices'          => array_map(fn (array $price) => $price['price'], $priced['prices']),
            'currency'        => $register->currency,
            'grand_total'     => $priced['totals']['grand_total'],
            'expires_at'      => $hours ? now()->addHours($hours) : null,
        ]);

        ActivityLogService::record('created', "Held the order {$held->number} at the register \"{$register->name}\" ({$register->code})"
            .($contact ? " for {$contact->name}" : '').': '.count($lines).' '.Str::plural('item', count($lines)).", {$register->currency} {$held->grand_total}", $held, ['source' => 'api']);

        return $held;
    }

    /** The held order as resuming it loads it into the cart - priced again now - and what changed since it was held. */
    public function preview(Workspace $workspace, int $id): array
    {
        $session = $this->counter->selling($workspace);

        return $this->resumable($workspace, $session, $this->waiting($workspace, $id, $session));
    }

    /** As preview(), the order resumed: it waits no longer. */
    public function resume(Workspace $workspace, int $id): array
    {
        $session = $this->counter->selling($workspace);
        $held    = $this->waiting($workspace, $id, $session);

        return DB::transaction(function () use ($workspace, $session, $held): array {
            $this->stillWaiting($this->held->lock($held));

            $resumed = $this->resumable($workspace, $session, $held);

            if ($resumed['lines'] === []) {
                $this->refuse("None of {$held->number}'s items is sold any longer: there is nothing to resume.");
            }

            $this->held->update($held, ['status' => PosHeldOrder::RESUMED, 'closed_by' => $this->access->user()->id, 'closed_at' => now()]);

            ActivityLogService::record('updated', "Resumed the order {$held->number} held at the register \"{$held->register->name}\" ({$held->register->code})", $held, ['source' => 'api']);

            return $resumed;
        });
    }

    /** One of the orders waiting at the user's register, cancelled - and why. */
    public function cancel(Workspace $workspace, int $id, string $reason): PosHeldOrder
    {
        $held = $this->waiting($workspace, $id, $this->counter->selling($workspace));

        DB::transaction(function () use ($held, $reason): void {
            $this->stillWaiting($this->held->lock($held));

            $this->held->update($held, ['status' => PosHeldOrder::CANCELLED, 'closed_by' => $this->access->user()->id, 'closed_at' => now(), 'cancel_reason' => $reason]);
        });

        ActivityLogService::record('updated', "Cancelled the order {$held->number} held at the register \"{$held->register->name}\" ({$held->register->code}): {$reason}", $held, ['source' => 'api']);

        return $held;
    }

    /** Every order still held past its time, expired - by the scheduler. How many. */
    public function expire(): int
    {
        $due = $this->held->pastTheirTime();

        foreach ($due as $held) {
            $this->held->update($held, ['status' => PosHeldOrder::EXPIRED, 'closed_at' => now()]);

            ActivityLogService::record('updated', "The order {$held->number} held at the register \"{$held->register->name}\" ({$held->register->code}) expired", $held, ['source' => 'schedule']);
        }

        return $due->count();
    }

    /**
     * $held as the user's cart takes it now: its customer, while a sale can
     * be made to them; its items still sold, each with its price typed over
     * and discount where the user may give them; the order's discount
     * likewise; priced again at the register - and what changed, in words.
     *
     * @return array{held: PosHeldOrder, contact: ?Contact, lines: list<array>, discount: ?array, note: ?string, changes: list<string>, total: string}
     */
    private function resumable(Workspace $workspace, PosSession $session, PosHeldOrder $held): array
    {
        $cart     = $held->cart;
        $register = $session->register;
        $currency = $register->currency;
        $changes  = [];
        $contact  = $held->contact_id ? $this->counter->usableCustomer($workspace, $held->contact_id) : null;

        if ($held->contact_id && ! $contact) {
            $changes[] = 'Its customer can no longer be sold to: the sale is back to the walk-in customer.';
        }

        $overrides = $this->access->can('pos.selling.override');
        $discounts = $this->access->can('pos.selling.discount');
        $items     = $this->items->forCart($workspace, array_column($cart['lines'], 'item_id'));
        $lines     = [];
        $was       = [];

        foreach ($cart['lines'] as $index => $line) {
            $item = $items->get((int) $line['item_id']);

            if ($this->counter->unsold($item) !== null) {
                $changes[] = "{$line['name']} is no longer sold: it is left out.";

                continue;
            }

            if ($line['price'] !== null && ! $overrides) {
                $changes[] = "The price typed over {$item->name} needs Override Prices: it is left out.";
                $line = [...$line, 'price' => null, 'override_reason' => null];
            }

            if ($line['discount_type'] !== null && ! $discounts) {
                $changes[] = "{$item->name}'s discount needs Apply Discounts: it is left out.";
                $line = [...$line, 'discount_type' => null, 'discount_value' => null];
            }

            $lines[] = [...$line, 'item' => $item];
            $was[]   = $held->prices[$index] ?? null;
        }

        $discount = $cart['discount'];

        if ($discount !== null && ! $discounts) {
            $changes[] = 'The sale\'s discount needs Apply Discounts: it is left out.';
            $discount  = null;
        }

        if ($lines === []) {
            return ['held' => $held, 'contact' => $contact, 'lines' => [], 'discount' => null, 'note' => $cart['note'], 'changes' => $changes, 'total' => '0.00'];
        }

        $today  = $this->counter->today($workspace);
        $priced = $this->pricing->priced($workspace, $register, $contact, $lines, $this->counter->rate($workspace, $session, $today), $today, $discount);

        foreach ($priced['prices'] as $index => $price) {
            $name = $lines[$index]['item']->name;

            $changes = [...$changes, ...match (true) {
                $price['price'] === null                                                      => ["{$name} has no price now: set one on the item, or take it out of the sale."],
                $was[$index] !== null && bccomp((string) $was[$index], $price['price'], 2) !== 0 => ["{$name}: {$currency} {$was[$index]} when it was held, {$currency} {$price['price']} now."],
                default                                                                       => [],
            }];
        }

        return ['held' => $held, 'contact' => $contact, 'lines' => $lines, 'discount' => $discount, 'note' => $cart['note'], 'changes' => $changes, 'total' => $priced['totals']['grand_total']];
    }

    /** One of the orders waiting at the user's register - else a 404, or a 409 saying why it waits no longer. */
    private function waiting(Workspace $workspace, int $id, PosSession $session): PosHeldOrder
    {
        $held = $this->held->findFor($workspace, $id, $this->access->locations()) ?? abort(404, 'There is no such held order.');

        if ($held->pos_register_id !== $session->pos_register_id) {
            $this->refuse("{$held->number} is held at the register \"{$held->register->name}\": it is resumed there.");
        }

        return $this->stillWaiting($held);
    }

    private function stillWaiting(PosHeldOrder $held): PosHeldOrder
    {
        if (! $held->isWaiting()) {
            $this->refuse(match ($held->status) {
                PosHeldOrder::RESUMED   => "{$held->number} was resumed already.",
                PosHeldOrder::CANCELLED => "{$held->number} was cancelled.",
                default                 => "{$held->number} expired: it can no longer be resumed.",
            });
        }

        return $held;
    }
}
