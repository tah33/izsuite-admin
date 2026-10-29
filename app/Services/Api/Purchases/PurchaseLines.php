<?php

namespace App\Services\Api\Purchases;

use App\Models\User\TaxRate;
use App\Models\User\Workspace;
use App\Repositories\User\ItemRepository;
use App\Repositories\User\TaxRateRepository;

/**
 * A purchase's lines, from what the form sends to what is kept (see
 * PurchaseLine). The name, description, unit and price are the line's own, as
 * sent - a free description never becomes an item (Doc 09 section 6). Its tax
 * is kept as the rate is now, with whether it is recoverable.
 *
 * The lines come validated (see SavePurchaseRequest): their items and rates
 * belong to the business.
 */
class PurchaseLines
{
    public function __construct(
        protected ItemRepository $itemRepository,
        protected TaxRateRepository $taxRateRepository,
    ) {}

    /** @return list<array<string, mixed>> */
    public function rows(Workspace $workspace, array $lines): array
    {
        $lines = array_values($lines);
        $items = $this->itemRepository->findManyInWorkspace($workspace->id, array_filter(array_column($lines, 'item_id')))->keyBy('id');
        $rates = $this->taxRateRepository->findManyFor($workspace, array_filter(array_column($lines, 'tax_rate_id')))->keyBy('id');

        return array_map(function (array $line, int $index) use ($items, $rates) {
            $rate     = $rates->get($line['tax_rate_id'] ?? 0);
            $discount = isset($line['discount_value']) && $line['discount_value'] !== '' && ($line['discount_type'] ?? null);

            return [
                'position'       => $index + 1,
                'item_id'        => $items->get($line['item_id'] ?? 0)?->id,
                'name'           => $line['name'] ?? '',
                'description'    => $line['description'] ?? null,
                'quantity'       => (string) ($line['quantity'] ?? '0'),
                'unit'           => $line['unit'] ?? null,
                'unit_price'     => (string) ($line['unit_price'] ?? '0'),
                'discount_type'  => $discount ? $line['discount_type'] : null,
                'discount_value' => $discount ? (string) $line['discount_value'] : null,
                ...$this->tax($rate),
            ];
        }, $lines, array_keys($lines));
    }

    /** The tax as it is now - kept with the line, so changing the rate later never reaches it. */
    private function tax(?TaxRate $rate): array
    {
        return [
            'tax_rate_id'        => $rate?->id,
            'tax_name'           => $rate?->name,
            'tax_code'           => $rate?->code,
            'tax_rate'           => $rate ? (string) $rate->rate : null,
            'tax_treatment'      => $rate?->treatment->value,
            'tax_recoverability' => $rate?->recoverability,
        ];
    }
}
