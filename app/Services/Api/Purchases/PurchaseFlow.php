<?php

namespace App\Services\Api\Purchases;

use App\Enums\DocumentType;
use App\Enums\PurchaseStatus;
use App\Models\User\Purchase;
use App\Models\User\PurchaseLine;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\PurchaseRepository;
use App\Repositories\User\PurchaseSettingRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Api\Sales\SalesSnapshots;
use App\Services\Documents\DocumentNumbers;
use App\Services\Purchases\PurchaseRules;
use App\Services\Shared\ActivityLogService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A purchase's status changes (Doc 09 sections 9 and 10), each allowed only
 * where PurchaseRules lists it:
 *
 * - record: a draft becomes recorded - numbered, its business and supplier
 *   kept as they are now, its amounts locked, and its grand total owed to the
 *   supplier until it is paid (Pay Later);
 * - void: a recorded purchase is cancelled, while nothing has paid it.
 *
 * Before it is recorded, its supplier must still be a supplier and each
 * line's tax still in effect on its date. A supplier invoice recorded before
 * (section 11) - the same supplier and invoice number - is recorded again only
 * once confirmed; an exact duplicate - the same date, total and currency too -
 * only with a reason, which it keeps.
 */
class PurchaseFlow
{
    use RefusesConflicts;

    public function __construct(
        protected PurchaseRepository $purchaseRepository,
        protected PurchaseSettingRepository $purchaseSettingRepository,
        protected PurchaseService $purchaseService,
        protected PurchaseRules $purchaseRules,
        protected SalesSnapshots $salesSnapshots,
        protected DocumentNumbers $documentNumbers,
    ) {}

    /** $duplicate is validated (see RecordPurchaseRequest): confirmed, and a reason. */
    public function record(Workspace $workspace, int $id, User $user, array $duplicate = []): Purchase
    {
        $purchase = $this->allowed($workspace, $id, 'record', 'Only a draft can be recorded.');

        $this->checkReady($purchase);
        $this->checkDuplicates($workspace, $purchase, $duplicate);

        DB::transaction(fn () => $this->purchaseRepository->update($purchase, [
            'number'            => $this->documentNumbers->next(
                $workspace,
                DocumentType::Purchase,
                CarbonImmutable::parse($purchase->document_date),
                fn (string $number) => $this->purchaseRepository->numberTaken($workspace, $number),
            ),
            'status'            => PurchaseStatus::Recorded,
            'payment_status'    => bccomp((string) $purchase->grand_total, '0', 2) > 0 ? Purchase::PAYMENT_UNPAID : Purchase::PAYMENT_PAID,
            'balance_due'       => $purchase->grand_total,
            'business_snapshot' => $this->salesSnapshots->business($workspace, $purchase->location),
            'supplier_snapshot' => $this->salesSnapshots->customer($purchase->contact),
            'duplicate_reason'  => $duplicate['reason'] ?? null,
            'recorded_at'       => now(),
            'updated_by'        => $user->id,
        ]));

        return $this->logged($purchase, 'Recorded '.$this->purchaseService->describe($purchase));
    }

    public function void(Workspace $workspace, int $id, User $user, ?string $reason): Purchase
    {
        $purchase = $this->allowed($workspace, $id, 'void', $this->purchaseService->find($workspace, $id)->status === PurchaseStatus::Recorded
            ? 'Payments have settled some of this purchase. Reverse them first.'
            : 'Only a recorded purchase can be voided. A draft is deleted instead.');

        $this->purchaseRepository->update($purchase, [
            'status'         => PurchaseStatus::Void,
            'payment_status' => Purchase::PAYMENT_NOT_APPLICABLE,
            'balance_due'    => '0',
            'status_reason'  => $reason,
            'updated_by'     => $user->id,
        ]);

        return $this->logged($purchase, 'Voided '.$this->purchaseService->describe($purchase));
    }

    private function allowed(Workspace $workspace, int $id, string $action, string $refusal): Purchase
    {
        $purchase = $this->purchaseService->find($workspace, $id);

        if (! $this->purchaseRules->allows($purchase, $action)) {
            $this->refuse($refusal);
        }

        return $purchase;
    }

    private function checkReady(Purchase $purchase): void
    {
        if (! $purchase->contact?->isSupplier()) {
            $this->refuse('Choose a supplier first: this contact is not a supplier any more.');
        }

        if ($purchase->lines->isEmpty()) {
            $this->refuse('Add at least one line first.');
        }

        $this->purchaseRepository->loadLineTaxes($purchase);

        $stale = $purchase->lines->first(fn (PurchaseLine $line) => $line->tax_rate_id && ! $this->taxInEffect($purchase, $line));

        if ($stale) {
            $this->refuse("{$stale->tax_name} on \"{$stale->name}\" is no longer in effect on {$purchase->document_date}. Edit it and choose the current rate first.");
        }
    }

    private function taxInEffect(Purchase $purchase, PurchaseLine $line): bool
    {
        $rate = $line->taxRate;

        return $rate && $rate->is_active && $rate->effective_from <= $purchase->document_date
            && (! $rate->effective_to || $rate->effective_to >= $purchase->document_date);
    }

    /**
     * A supplier invoice recorded before - when Settings > Purchases checks
     * for one - is refused (a validation error, naming the earlier purchase)
     * until the duplicate is confirmed, or, for an exact one, given a reason.
     */
    private function checkDuplicates(Workspace $workspace, Purchase $purchase, array $duplicate): void
    {
        if (! $purchase->supplier_invoice_number || ! $this->purchaseSettingRepository->forWorkspace($workspace)->duplicate_check) {
            return;
        }

        $earlier = $this->purchaseRepository->duplicatesOf($purchase);
        $exact   = $earlier->first(fn (Purchase $other) => $other->supplier_invoice_date === $purchase->supplier_invoice_date
            && $other->currency === $purchase->currency
            && bccomp((string) $other->grand_total, (string) $purchase->grand_total, 2) === 0);

        if ($exact && empty($duplicate['reason'])) {
            throw ValidationException::withMessages([
                'duplicate_reason' => "{$exact->number} has the same supplier invoice - number {$purchase->supplier_invoice_number}, its date, total and currency. Give a reason to record this one too.",
            ]);
        }

        if (! $exact && $earlier->isNotEmpty() && empty($duplicate['confirmed']) && empty($duplicate['reason'])) {
            throw ValidationException::withMessages([
                'duplicate' => $this->numbers($earlier)." already has supplier invoice number {$purchase->supplier_invoice_number} from this supplier. Record this one anyway?",
            ]);
        }
    }

    /** "PUR-00003" or "PUR-00003 and PUR-00007". */
    private function numbers(Collection $purchases): string
    {
        $numbers = $purchases->pluck('number')->all();

        return count($numbers) > 1 ? implode(', ', array_slice($numbers, 0, -1)).' and '.end($numbers) : $numbers[0];
    }

    private function logged(Purchase $purchase, string $description): Purchase
    {
        // Logged here, by what happened, so LogApiWriteActivity does not add
        // its own vaguer entry.
        ActivityLogService::record('updated', $description, $purchase, ['source' => 'api', 'status' => $purchase->status->value]);

        return $this->purchaseRepository->loadShown($purchase);
    }
}
