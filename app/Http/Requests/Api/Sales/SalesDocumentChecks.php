<?php

namespace App\Http\Requests\Api\Sales;

use App\Enums\DocumentType;
use App\Models\User\Item;
use App\Models\User\ItemComponent;
use App\Models\User\SalesDocument;
use App\Models\User\Workspace;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\ItemRepository;
use App\Repositories\User\SalesDocumentRepository;
use App\Repositories\User\SalesSettingRepository;
use App\Repositories\User\TaxRateRepository;
use Illuminate\Validation\Validator;

/**
 * What SaveSalesDocumentRequest checks with the database and the settings,
 * once its own rules have passed:
 *
 * - the customer is a customer (Doc 08 section 4) and active;
 * - each item is this business's, sold and active - a combo with all its
 *   components active (Doc 04 section 14);
 * - each tax is one of the business's sales taxes, active and in effect on the
 *   document's date;
 * - discounts are on in Settings > Sales, and a percentage is at most 100.
 *
 * What a document being edited already has may stay even if it has been
 * switched off since - its customer, items and taxes - so the rest of it can
 * still be changed.
 */
class SalesDocumentChecks
{
    public function __construct(
        protected ContactRepository $contactRepository,
        protected ItemRepository $itemRepository,
        protected TaxRateRepository $taxRateRepository,
        protected SalesDocumentRepository $salesDocumentRepository,
        protected SalesSettingRepository $salesSettingRepository,
    ) {}

    public function check(Validator $validator, Workspace $workspace, ?DocumentType $type, ?int $id, array $input): void
    {
        if ($validator->errors()->isNotEmpty() || ! $type) {
            return;
        }

        $existing = $id ? $this->salesDocumentRepository->findFor($workspace, $type, $id) : null;

        $this->checkCustomer($validator, $workspace, $input, $existing);
        $this->checkItems($validator, $workspace, $input['lines'], $existing);
        $this->checkTaxes($validator, $workspace, $input['lines'], $input['document_date'], $existing);
        $this->checkDiscounts($validator, $workspace, $input);
    }

    private function checkCustomer(Validator $validator, Workspace $workspace, array $input, ?SalesDocument $existing): void
    {
        $contact = $this->contactRepository->findFor($workspace, (int) $input['contact_id']);

        if ($contact && ! $contact->isCustomer()) {
            $validator->errors()->add('contact_id', "{$contact->name} is only a supplier. Choose a customer, or make them one on their contact page.");
        } elseif ($contact && ! $contact->is_active && $contact->id !== $existing?->contact_id) {
            $validator->errors()->add('contact_id', "{$contact->name} is inactive.");
        }
    }

    private function checkItems(Validator $validator, Workspace $workspace, array $lines, ?SalesDocument $existing): void
    {
        $items = $this->itemRepository->findManyWithComponents($workspace->id, array_filter(array_column($lines, 'item_id')))->keyBy('id');
        $kept  = $existing ? $existing->lines->pluck('item_id')->filter()->all() : [];

        foreach ($lines as $index => $line) {
            if (empty($line['item_id'])) {
                continue;
            }

            $item    = $items->get((int) $line['item_id']);
            $message = match (true) {
                $item === null                                          => 'Choose an item of this business.',
                ! $item->isSold()                                       => "{$item->name} is only purchased, so it cannot be sold.",
                ! $item->is_active && ! in_array($item->id, $kept, true) => "{$item->name} is inactive.",
                default                                                 => $this->comboProblem($item),
            };

            if ($message) {
                $validator->errors()->add("lines.{$index}.item_id", $message);
            }
        }
    }

    /** A combo cannot be sold while one of its components is inactive (Doc 04 section 14). */
    private function comboProblem(Item $item): ?string
    {
        if (! $item->isCombo()) {
            return null;
        }

        $inactive = $item->components->first(fn (ItemComponent $row) => ! $row->component?->is_active);

        return $inactive ? "{$item->name} cannot be sold while its component {$inactive->component?->name} is inactive." : null;
    }

    private function checkTaxes(Validator $validator, Workspace $workspace, array $lines, string $date, ?SalesDocument $existing): void
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
                $rate === null || ! $rate->for_sales                    => 'Choose one of your sales taxes.',
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

    private function checkDiscounts(Validator $validator, Workspace $workspace, array $input): void
    {
        $settings = $this->salesSettingRepository->forWorkspace($workspace);
        $given    = fn (array $from) => ($from['discount_type'] ?? null) && (float) ($from['discount_value'] ?? 0) > 0;

        foreach ($input['lines'] as $index => $line) {
            if (! $given($line)) {
                continue;
            }

            if (! $settings->line_discounts) {
                $validator->errors()->add("lines.{$index}.discount_value", 'Line discounts are turned off in Settings > Sales.');
            } elseif ($line['discount_type'] === 'percent' && (float) $line['discount_value'] > 100) {
                $validator->errors()->add("lines.{$index}.discount_value", 'A percentage discount is at most 100%.');
            }
        }

        if (! $given($input)) {
            return;
        }

        if (! $settings->document_discounts) {
            $validator->errors()->add('discount_value', 'Document discounts are turned off in Settings > Sales.');
        } elseif ($input['discount_type'] === 'percent' && (float) $input['discount_value'] > 100) {
            $validator->errors()->add('discount_value', 'A percentage discount is at most 100%.');
        }
    }
}
