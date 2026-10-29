<?php

namespace App\Http\Requests\Api\Purchases;

use App\Models\User\Purchase;
use App\Models\User\Workspace;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\ItemRepository;
use App\Repositories\User\PurchaseRepository;
use App\Repositories\User\TaxRateRepository;
use Illuminate\Validation\Validator;

/**
 * What SavePurchaseRequest checks with the database, once its own rules have
 * passed:
 *
 * - the supplier is a supplier (Doc 09 section 6) and active;
 * - each item is this business's, bought and active;
 * - each tax is one of the business's purchase taxes, active and in effect on
 *   the purchase's date;
 * - a percentage discount is at most 100.
 *
 * What a draft being edited already has may stay even if it has been switched
 * off since - its supplier, items and taxes - so the rest of it can still be
 * changed.
 */
class PurchaseChecks
{
    public function __construct(
        protected ContactRepository $contactRepository,
        protected ItemRepository $itemRepository,
        protected TaxRateRepository $taxRateRepository,
        protected PurchaseRepository $purchaseRepository,
    ) {}

    public function check(Validator $validator, Workspace $workspace, ?int $id, array $input): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $existing = $id ? $this->purchaseRepository->findFor($workspace, $id) : null;

        $this->checkSupplier($validator, $workspace, $input, $existing);
        $this->checkItems($validator, $workspace, $input['lines'], $existing);
        $this->checkTaxes($validator, $workspace, $input['lines'], $input['document_date'], $existing);
        $this->checkDiscounts($validator, $input);
    }

    private function checkSupplier(Validator $validator, Workspace $workspace, array $input, ?Purchase $existing): void
    {
        $contact = $this->contactRepository->findFor($workspace, (int) $input['contact_id']);

        if ($contact && ! $contact->isSupplier()) {
            $validator->errors()->add('contact_id', "{$contact->name} is only a customer. Choose a supplier, or make them one on their contact page.");
        } elseif ($contact && ! $contact->is_active && $contact->id !== $existing?->contact_id) {
            $validator->errors()->add('contact_id', "{$contact->name} is inactive.");
        }
    }

    private function checkItems(Validator $validator, Workspace $workspace, array $lines, ?Purchase $existing): void
    {
        $items = $this->itemRepository->findManyInWorkspace($workspace->id, array_filter(array_column($lines, 'item_id')))->keyBy('id');
        $kept  = $existing ? $existing->lines->pluck('item_id')->filter()->all() : [];

        foreach ($lines as $index => $line) {
            if (empty($line['item_id'])) {
                continue;
            }

            $item    = $items->get((int) $line['item_id']);
            $message = match (true) {
                $item === null                                          => 'Choose an item of this business.',
                ! $item->isPurchased()                                  => "{$item->name} is only sold, so it cannot be bought.",
                ! $item->is_active && ! in_array($item->id, $kept, true) => "{$item->name} is inactive.",
                default                                                 => null,
            };

            if ($message) {
                $validator->errors()->add("lines.{$index}.item_id", $message);
            }
        }
    }

    private function checkTaxes(Validator $validator, Workspace $workspace, array $lines, string $date, ?Purchase $existing): void
    {
        $rates = $this->taxRateRepository->findManyFor($workspace, array_filter(array_column($lines, 'tax_rate_id')))->keyBy('id');
        $kept  = $existing ? $existing->lines->pluck('tax_rate_id')->filter()->all() : [];

        foreach ($lines as $index => $line) {
            if (empty($line['tax_rate_id'])) {
                continue;
            }

            $rate    = $rates->get((int) $line['tax_rate_id']);
            $isKept  = $rate && in_array($rate->id, $kept, true);
            $message = match (true) {
                $rate === null || ! $rate->for_purchases                => 'Choose one of your purchase taxes.',
                $isKept                                                 => null,
                ! $rate->is_active                                      => "{$rate->name} is inactive.",
                $rate->effective_from > $date || ($rate->effective_to && $rate->effective_to < $date) => "{$rate->name} is not in effect on this date.",
                default                                                 => null,
            };

            if ($message) {
                $validator->errors()->add("lines.{$index}.tax_rate_id", $message);
            }
        }
    }

    private function checkDiscounts(Validator $validator, array $input): void
    {
        foreach ($input['lines'] as $index => $line) {
            if (($line['discount_type'] ?? null) === 'percent' && (float) ($line['discount_value'] ?? 0) > 100) {
                $validator->errors()->add("lines.{$index}.discount_value", 'A percentage discount is at most 100%.');
            }
        }

        if (($input['discount_type'] ?? null) === 'percent' && (float) ($input['discount_value'] ?? 0) > 100) {
            $validator->errors()->add('discount_value', 'A percentage discount is at most 100%.');
        }
    }
}
