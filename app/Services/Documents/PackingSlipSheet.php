<?php

namespace App\Services\Documents;

use App\Models\User\EcommerceFulfilment;
use App\Models\User\EcommerceFulfilmentLine;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderLine;
use App\Models\User\Workspace;
use App\Services\Api\Ecommerce\EcommerceOrderProgress;
use App\Services\Api\Sales\SalesSnapshots;
use App\Services\Shared\BusinessCalendar;
use Illuminate\Support\Arr;

/**
 * What an online order's shipment's packing slip shows (Doc 19 sections 19.27
 * and 19.39 - Phase G2), written as its template writes dates and quantities:
 * the customer and where the parcel goes; the shipment, its day, the order's
 * day and shipping method, the carrier and tracking; each product in the
 * parcel with its options, SKU and quantity; and whether it holds only part
 * of the order. Never a price, nor the business's own notes: it goes in the
 * parcel. Expects the order with its lines, and the shipment with its lines.
 */
class PackingSlipSheet
{
    public function __construct(
        protected BusinessCalendar $calendar,
        protected SalesSnapshots $salesSnapshots,
        protected EcommerceOrderProgress $progress,
    ) {}

    /** The customer as the order has them - their name and contacts; no billing address, as nothing is billed. */
    public function customer(EcommerceOrder $order): array
    {
        return [
            'name'            => $order->customer['name'] ?? '',
            'email'           => $order->customer['email'] ?? null,
            'phone'           => $order->customer['phone'] ?? null,
            'billing_address' => [],
        ];
    }

    /** Where it goes: their company, if they gave one, then the order's shipping address as its country writes it. */
    public function shipTo(EcommerceOrder $order): array
    {
        $address = $order->shipping_address ?? [];

        return array_values(array_filter([
            $order->customer['company'] ?? null,
            ...($address === [] ? [] : $this->salesSnapshots->addressLines([...Arr::except($address, 'region'), 'state' => $address['region'] ?? null])),
        ]));
    }

    /** @return list<array{0: string, 1: string}> the shipment's facts, beside the customer */
    public function facts(Workspace $workspace, EcommerceOrder $order, EcommerceFulfilment $shipment, DocumentFormat $format): array
    {
        $method = $order->delivery_snapshot['name'] ?? null;

        return array_values(array_filter([
            ['Shipment', (string) $shipment->sequence],
            ['Shipped On', $format->date($shipment->fulfilled_on->toDateString())],
            ['Order Date', $format->date($order->placed_at->copy()->setTimezone($this->calendar->timezone($workspace))->toDateString())],
            $method ? ['Shipping Method', $method] : null,
            $shipment->carrier ? ['Carrier', $shipment->carrier] : null,
            $shipment->tracking_number ? ['Tracking No.', $shipment->tracking_number] : null,
            $shipment->completed_on ? ['Delivered On', $format->date($shipment->completed_on->toDateString())] : null,
        ]));
    }

    /**
     * Each product in the parcel - its title, options ("Size: L") and SKU as
     * the store showed them, and how many - with whether any has a SKU or a
     * unit, for the columns the slip shows.
     *
     * @return array{rows: list<array{name: string, options: string, sku: ?string, quantity: string, unit: ?string}>, skus: bool, units: bool}
     */
    public function lines(EcommerceOrder $order, EcommerceFulfilment $shipment, DocumentFormat $format): array
    {
        $lines = $order->lines->keyBy('id');
        $rows  = $shipment->lines->map(function (EcommerceFulfilmentLine $held) use ($lines, $format) {
            $line = $lines->get($held->ecommerce_order_line_id);

            return [
                'name'     => (string) $line?->title,
                'options'  => implode(', ', array_map(fn (array $option) => trim(($option['name'] ?? '').': '.($option['value'] ?? ''), ': '), $line?->options ?? [])),
                'sku'      => $line?->sku,
                'quantity' => $format->quantity((string) $held->quantity),
                'unit'     => $line?->unit,
            ];
        })->values();

        return [
            'rows'  => $rows->all(),
            'skus'  => $rows->contains(fn (array $row) => filled($row['sku'])),
            'units' => $rows->contains(fn (array $row) => filled($row['unit'])),
        ];
    }

    /** Whether the parcel holds less than all of the order's products that ship - one sent in parts. */
    public function part(EcommerceOrder $order, EcommerceFulfilment $shipment): bool
    {
        $held = [];

        foreach ($shipment->lines as $line) {
            $held[$line->ecommerce_order_line_id] = bcadd($held[$line->ecommerce_order_line_id] ?? '0', (string) $line->quantity, 3);
        }

        return $this->progress->shippable($order)->contains(fn (EcommerceOrderLine $line) => bccomp($held[$line->id] ?? '0', (string) $line->quantity, 3) < 0);
    }
}
