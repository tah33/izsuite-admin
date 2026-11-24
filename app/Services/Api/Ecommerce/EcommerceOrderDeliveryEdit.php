<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderLine;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\EcommerceOrderRepository;
use App\Repositories\User\ItemRepository;
use App\Repositories\User\ShippingRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Api\Sales\SalesLines;
use App\Services\Sales\SalesCalculator;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\BusinessCalendar;
use App\Support\Countries;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * An online order's addresses and delivery, changed before payment (Doc 19
 * section 19.21 - Phase F5a), under Edit Orders, worked out as checkout
 * works them out (StorefrontCheckout) for the order's own products:
 *
 * - quote(): for an address, the shipping methods there that take the
 *   order's parcel - each at its price now, as the order shows prices - its
 *   pickup points, and whether the store sells there;
 * - save(): the billing address; the shipping one, when shipped, in a
 *   country the store sells to; shipping by a method offered for it - the
 *   shipping line re-priced at its price now, at the order's rate - or pickup
 *   at a point. The payment method must still be offered for a changed
 *   delivery or country (19.22). The totals are worked out again, and an
 *   issued invoice with them (EcommerceOrderEdits::retotal()).
 *
 * An order with nothing to deliver changes its billing address alone.
 */
class EcommerceOrderDeliveryEdit
{
    use RefusesConflicts;

    public function __construct(
        protected EcommerceOrderService $orderService,
        protected EcommerceOrderEdits $edits,
        protected EcommerceOrderRepository $orders,
        protected StorefrontCheckout $checkout,
        protected StorefrontOrderPlacement $placement,
        protected StorefrontPricing $pricing,
        protected ShippingRepository $shipping,
        protected EcommercePaymentOptions $paymentOptions,
        protected ItemRepository $items,
        protected SalesLines $salesLines,
        protected SalesCalculator $calculator,
        protected BusinessCalendar $calendar,
    ) {}

    /**
     * What order $id could be delivered by, to the address $data gives.
     *
     * @return array{order: EcommerceOrder, needs_delivery: bool, sells_to: bool, methods: list<array>, points: \Illuminate\Support\Collection}
     */
    public function quote(Workspace $workspace, int $id, array $data): array
    {
        $order = $this->orders->forConfirming($this->orderService->find($workspace, $id), false);

        $this->edits->checkEditable($order);

        $needs   = $order->delivery !== EcommerceOrder::NONE;
        $address = $this->deliveredTo($data);
        $sells   = in_array(strtoupper((string) ($address['country'] ?? '')), $order->storefront->countries ?? [], true);
        $terms   = $this->terms($workspace, $order);

        return [
            'order'          => $order,
            'needs_delivery' => $needs,
            'sells_to'       => $sells,
            'methods'        => $needs && $sells ? $this->checkout->offers($workspace, Arr::only($address, ['country', 'region', 'city', 'postal_code']), $this->parcel($workspace, $order, $terms), $terms) : [],
            'points'         => $needs ? $this->shipping->offeredPickupPoints($workspace) : collect(),
        ];
    }

    /** $data as UpdateEcommerceOrderDeliveryRequest takes it. The order, as its page shows it. */
    public function save(Workspace $workspace, User $user, int $id, array $data): EcommerceOrder
    {
        $found = $this->orderService->find($workspace, $id);

        DB::transaction(function () use ($workspace, $user, $found, $data) {
            $order    = $this->edits->locked($found);
            $terms    = $this->terms($workspace, $order);
            $billing  = $this->address($data['billing']);
            $delivery = $order->delivery === EcommerceOrder::NONE ? EcommerceOrder::NONE : ($data['delivery'] ?? null);
            $method   = null;
            $point    = null;

            if (! in_array($delivery, [EcommerceOrder::SHIPPING, EcommerceOrder::PICKUP, EcommerceOrder::NONE], true)) {
                throw ValidationException::withMessages(['delivery' => 'Choose delivery or pickup.']);
            }

            $shippingTo = $delivery === EcommerceOrder::SHIPPING ? (($data['shipping_same'] ?? true) ? $billing : $this->address($data['shipping'] ?? [])) : null;

            if ($delivery === EcommerceOrder::SHIPPING) {
                if (! in_array($shippingTo['country'], $order->storefront->countries ?? [], true)) {
                    throw ValidationException::withMessages([($data['shipping_same'] ?? true) ? 'billing.country' : 'shipping.country' => $order->storefront->name.' does not deliver to '.(Countries::name($shippingTo['country']) ?? 'this country').'.']);
                }

                $offers = $this->checkout->offers($workspace, Arr::only($shippingTo, ['country', 'region', 'city', 'postal_code']), $this->parcel($workspace, $order, $terms), $terms);
                $method = collect($offers)->first(fn (array $offer) => $offer['method']->id === (int) ($data['shipping_method_id'] ?? 0))
                    ?? throw ValidationException::withMessages(['shipping_method_id' => 'Choose one of the delivery options offered for this address.']);
            }

            if ($delivery === EcommerceOrder::PICKUP) {
                $point = $this->shipping->offeredPickupPoints($workspace)->firstWhere('id', (int) ($data['pickup_point_id'] ?? 0))
                    ?? throw ValidationException::withMessages(['pickup_point_id' => 'Choose where the order is collected.']);
            }

            $before = ['billing' => $this->line($order->billing_address), 'shipping' => $this->line($order->shipping_address), 'delivery' => $this->deliveryWords($order), 'total' => "{$order->currency} {$order->grand_total}"];
            $total  = (string) $order->grand_total;
            $kind   = $order->delivery;
            $place  = $order->delivery === EcommerceOrder::SHIPPING ? ($order->shipping_address['country'] ?? null) : ($order->billing_address['country'] ?? null);

            // Shipped at the method's price now - none while it is quoted after the order, or picked up.
            $this->orders->replaceShipping($order, $method && $method['price'] !== null ? $this->shippingLine($workspace, $method, $terms) : null);
            $this->orders->update($order, ['billing_address' => $billing, 'shipping_address' => $shippingTo, ...$this->placement->delivery(['delivery' => $delivery, 'method' => $method, 'point' => $point])]);

            $order   = $this->edits->retotal($workspace, $user, $order);
            $country = $delivery === EcommerceOrder::SHIPPING ? $shippingTo['country'] : $billing['country'];

            // Its payment method, still offered for a changed delivery or country (19.22).
            if (($kind !== $delivery || $place !== $country) && ! $this->offered($workspace, $order, $country, $delivery)) {
                $this->refuse(($order->payment_snapshot['name'] ?? 'Its payment method').' is not offered for this delivery or country: keep them, or cancel the order for the customer to place it again.');
            }

            // Phase F5b: its shipping re-priced, the total is still one its payment method takes.
            $this->edits->checkPaymentLimits($workspace, $order, $total);

            $after   = ['billing' => $this->line($order->billing_address), 'shipping' => $this->line($order->shipping_address), 'delivery' => $this->deliveryWords($order), 'total' => "{$order->currency} {$order->grand_total}"];
            $changes = $this->edits->changes($before, $after, ['billing' => 'billing_address', 'shipping' => 'shipping_address', 'delivery' => 'delivery', 'total' => 'total']);

            if ($changes !== []) {
                // Logged here, by what happened, so LogApiWriteActivity does not add its own vaguer entry - what each was, in its changes.
                ActivityLogService::record('updated', 'Changed the addresses and delivery of '.$this->orderService->describe($order), $order, ['source' => 'api', 'changes' => $changes]);
            }
        });

        return $this->orderService->page($workspace, $id);
    }

    /**
     * Phase F5b: inside an edit's transaction, a shipped order's shipping
     * re-priced for its products as they are now - $order read with them - by
     * its own method at its price now, as checkout prices it; refused when the
     * method takes them no more. A method quoted after the order stays so;
     * a pickup, or nothing to deliver, has no shipping to re-price.
     */
    public function reprice(Workspace $workspace, EcommerceOrder $order): void
    {
        if ($order->delivery !== EcommerceOrder::SHIPPING) {
            return;
        }

        $terms  = $this->terms($workspace, $order);
        $offers = $this->checkout->offers($workspace, Arr::only($order->shipping_address ?? [], ['country', 'region', 'city', 'postal_code']), $this->parcel($workspace, $order, $terms), $terms);
        $method = collect($offers)->first(fn (array $offer) => $offer['method']->id === $order->shipping_method_id);

        if (! $method) {
            $this->refuse(($order->delivery_snapshot['name'] ?? 'Its shipping method')." would not take order {$order->number}'s products: change its delivery first.");
        }

        $this->orders->replaceShipping($order, $method['price'] !== null ? $this->shippingLine($workspace, $method, $terms) : null);
        $this->orders->update($order, $this->placement->delivery(['delivery' => EcommerceOrder::SHIPPING, 'method' => $method, 'point' => null]));
    }

    /** How the order prices delivery: the store's terms now, at the order's own currency, rate and way of showing prices. */
    private function terms(Workspace $workspace, EcommerceOrder $order): array
    {
        return [
            ...$this->pricing->terms($workspace, $order->storefront, $this->calendar->today($workspace)->toDateString()),
            'currency' => $order->currency,
            'rate'     => (string) $order->exchange_rate,
            'with_tax' => $order->prices_include_tax,
        ];
    }

    /**
     * What ShippingRates weighs the order by, as checkout weighs a cart: what
     * its products come to in the base currency, how many of them are
     * delivered, their weight in kg and their categories.
     */
    private function parcel(Workspace $workspace, EcommerceOrder $order, array $terms): array
    {
        $products = $order->lines->where('kind', EcommerceOrderLine::PRODUCT)->values();
        $shipped  = $products->where('shipped', true)->values();
        $total    = $this->calculator->calculate($products->map(fn (EcommerceOrderLine $line) => $this->salesLines->forCalculation([
            'quantity' => (string) $line->quantity, 'unit_price' => (string) $line->unit_price, 'tax_rate_id' => $line->tax_rate_id, 'tax_name' => $line->tax_name,
            'tax_code' => $line->tax_code, 'tax_rate' => $line->tax_rate === null ? null : (string) $line->tax_rate, 'tax_treatment' => $line->tax_treatment,
        ]))->all(), null, null, $order->price_tax_mode, $order->tax_rounding, $terms['rate'])['grand_total'];
        $items    = $this->items->findManyWithComponents($workspace->id, $shipped->pluck('item_id')->filter()->unique()->values())->keyBy('id');

        return [
            'subtotal'   => bcmul($total, $terms['rate'], 2),
            'quantity'   => $shipped->reduce(fn (string $sum, EcommerceOrderLine $line) => bcadd($sum, (string) $line->quantity, 3), '0'),
            'weight'     => $shipped->reduce(fn (string $sum, EcommerceOrderLine $line) => bcadd($sum, (string) ($line->weight ?? '0'), 3), '0'),
            'categories' => $shipped->map(fn (EcommerceOrderLine $line) => $items->get($line->item_id)?->category_id)->all(),
        ];
    }

    /** The shipping as an order line, from checkout's invoice row of it (its amounts worked out after). */
    private function shippingLine(Workspace $workspace, array $offer, array $terms): array
    {
        $row = $this->checkout->shippingRow($workspace, $offer, $terms);

        return [
            ...Arr::only($row, ['name', 'unit', 'quantity', 'unit_price', 'tax_rate_id', 'tax_name', 'tax_code', 'tax_rate', 'tax_treatment']),
            'title'      => $row['name'],
            'amount'     => '0.00',
            'tax_amount' => '0.00',
        ];
    }

    private function offered(Workspace $workspace, EcommerceOrder $order, ?string $country, string $delivery): bool
    {
        return $this->paymentOptions->for($workspace, $order->currency, $country, (string) $order->grand_total, match ($delivery) {
            EcommerceOrder::SHIPPING => 'shipped',
            EcommerceOrder::PICKUP   => 'pickup',
            default                  => EcommercePaymentOptions::NOTHING,
        })->contains('id', $order->ecommerce_payment_method_id);
    }

    /** The address $data would deliver to: its own shipping one, else the billing one. */
    private function deliveredTo(array $data): array
    {
        return ($data['delivery'] ?? null) === EcommerceOrder::SHIPPING && ! ($data['shipping_same'] ?? true) ? ($data['shipping'] ?? []) : ($data['billing'] ?? []);
    }

    private function address(array $address): array
    {
        return array_map(fn ($value) => trim((string) $value) ?: null, [
            'line1' => $address['line1'] ?? null, 'line2' => $address['line2'] ?? null, 'city' => $address['city'] ?? null,
            'region' => $address['region'] ?? null, 'postal_code' => $address['postal_code'] ?? null, 'country' => strtoupper((string) ($address['country'] ?? '')),
        ]);
    }

    /** An address on one line, for the timeline: "House 12, Road 5, Dhaka, 1207, Bangladesh" - none for none. */
    private function line(?array $address): ?string
    {
        return $address ? implode(', ', array_filter([$address['line1'] ?? null, $address['line2'] ?? null, $address['city'] ?? null, $address['region'] ?? null, $address['postal_code'] ?? null, Countries::name($address['country'] ?? null)])) : null;
    }

    /** How the order is delivered, in words, for the timeline. */
    private function deliveryWords(EcommerceOrder $order): string
    {
        $snapshot = $order->delivery_snapshot ?? [];

        return match ($order->delivery) {
            EcommerceOrder::SHIPPING => ($snapshot['name'] ?? 'shipping').(($snapshot['quoted'] ?? false) ? ', price quoted after the order' : ", {$order->currency} {$order->shipping_total}"),
            EcommerceOrder::PICKUP   => 'pickup at '.($snapshot['name'] ?? 'a pickup point'),
            default                  => 'nothing to deliver',
        };
    }
}
