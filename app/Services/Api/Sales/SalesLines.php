<?php

namespace App\Services\Api\Sales;

use App\Models\User\Item;
use App\Models\User\ItemComponent;
use App\Models\User\TaxRate;
use App\Models\User\Workspace;
use App\Repositories\User\ItemRepository;
use App\Repositories\User\TaxRateRepository;

/**
 * A sales document's lines, from what the form sends to what is kept (see
 * SalesDocumentLine). The name, description, unit and price are the line's
 * own, as sent - a free description never becomes an item (Doc 08 section 4).
 * Its tax is kept as the rate is now. A combo shown with its components keeps
 * them, and a physical product with a warranty keeps that; a combo keeps the
 * warranties of its components that have one, each with its name (Doc 04
 * sections 14 and 15).
 *
 * The lines come validated (see SaveSalesDocumentRequest): their items and
 * rates belong to the business.
 */
class SalesLines
{
    public function __construct(
        protected ItemRepository $itemRepository,
        protected TaxRateRepository $taxRateRepository,
    ) {}

    /** @return list<array<string, mixed>> */
    public function rows(Workspace $workspace, array $lines): array
    {
        $lines = array_values($lines);
        $items = $this->itemRepository->findManyWithComponents($workspace->id, array_filter(array_column($lines, 'item_id')))->keyBy('id');
        $rates = $this->taxRateRepository->findManyFor($workspace, array_filter(array_column($lines, 'tax_rate_id')))->keyBy('id');

        return array_map(function (array $line, int $index) use ($items, $rates) {
            $item     = $items->get($line['item_id'] ?? 0);
            $rate     = $rates->get($line['tax_rate_id'] ?? 0);
            $discount = isset($line['discount_value']) && $line['discount_value'] !== '' && ($line['discount_type'] ?? null);

            return [
                'position'       => $index + 1,
                'item_id'        => $item?->id,
                'name'           => $line['name'] ?? '',
                'description'    => $line['description'] ?? null,
                'quantity'       => (string) ($line['quantity'] ?? '0'),
                'unit'           => $line['unit'] ?? null,
                'unit_price'     => (string) ($line['unit_price'] ?? '0'),
                'discount_type'  => $discount ? $line['discount_type'] : null,
                'discount_value' => $discount ? (string) $line['discount_value'] : null,
                ...$this->tax($rate),
                'components'     => $item ? $this->components($item) : null,
                'warranty'       => $item ? $this->warranty($item) : null,
            ];
        }, $lines, array_keys($lines));
    }

    /** A kept row as SalesCalculator takes it. */
    public function forCalculation(array $row): array
    {
        return [
            'quantity'       => (string) $row['quantity'],
            'unit_price'     => (string) $row['unit_price'],
            'discount_type'  => $row['discount_type'] ?? null,
            'discount_value' => isset($row['discount_value']) ? (string) $row['discount_value'] : null,
            'tax'            => $row['tax_rate'] !== null ? [
                'id'        => $row['tax_rate_id'],
                'name'      => $row['tax_name'],
                'code'      => $row['tax_code'],
                'rate'      => (string) $row['tax_rate'],
                'treatment' => $row['tax_treatment'],
            ] : null,
        ];
    }

    /** The tax as it is now - kept with the line, so changing the rate later never reaches it. */
    private function tax(?TaxRate $rate): array
    {
        return [
            'tax_rate_id'   => $rate?->id,
            'tax_name'      => $rate?->name,
            'tax_code'      => $rate?->code,
            'tax_rate'      => $rate ? (string) $rate->rate : null,
            'tax_treatment' => $rate?->treatment->value,
        ];
    }

    private function components(Item $item): ?array
    {
        if (! $item->isCombo() || $item->combo_display !== 'combo_and_components') {
            return null;
        }

        return $item->components->map(fn (ItemComponent $row) => [
            'name'     => $row->component?->name,
            'quantity' => (string) $row->quantity,
            'unit'     => $row->component?->unit,
        ])->values()->all();
    }

    /** A physical product's own warranty; for a combo, a list of its components' - or null when there is none. */
    private function warranty(Item $item): ?array
    {
        if ($item->isCombo()) {
            $parts = $item->components
                ->filter(fn (ItemComponent $row) => $row->component && $this->ownWarranty($row->component))
                ->map(fn (ItemComponent $row) => ['name' => $row->component->name, ...$this->ownWarranty($row->component)])
                ->values()
                ->all();

            return $parts ?: null;
        }

        return $this->ownWarranty($item);
    }

    private function ownWarranty(Item $item): ?array
    {
        if ($item->type !== Item::TYPE_PHYSICAL || ! $item->warranty_available) {
            return null;
        }

        return [
            'period'     => $item->warranty_period,
            'unit'       => $item->warranty_unit,
            'start_rule' => $item->warranty_start_rule,
            'terms'      => $item->warranty_terms,
        ];
    }
}
