<?php

namespace App\Services\Api\Pos;

use App\Enums\Edition;
use App\Models\User\Contact;
use App\Models\User\CustomerAdvance;
use App\Models\User\SalesDocument;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\Shared\RowLockRepository;
use App\Repositories\User\CustomerAdvanceRepository;
use App\Repositories\User\SalesAllocationRepository;
use App\Services\Api\Sales\CustomerAdvanceService;
use App\Services\Api\Sales\InvoiceSettlement;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\User\Apps\WorkspaceApps;
use App\Support\Decimal;
use Illuminate\Support\Collection;

/**
 * The customer's own credit as a POS payment (Doc 18 section 18.16 - Phase
 * D3b), with the full Sales app: their advances and credit notes with credit
 * left in the register's currency, as Sales applies them (Doc 16 sections
 * 16.15 and 16.18).
 *
 * Applied to the sale's invoice, oldest first, each allocation no more than
 * its source has left - read again, locked, inside the sale's transaction.
 * It is no payment: nothing goes into an account.
 */
class PosCustomerCredit
{
    use RefusesConflicts;

    public function __construct(
        protected CustomerAdvanceRepository $advances,
        protected SalesAllocationRepository $allocations,
        protected CustomerAdvanceService $customerAdvanceService,
        protected InvoiceSettlement $settlement,
        protected RowLockRepository $rows,
    ) {}

    /** What $customer holds unapplied in $currency - nothing without the full Sales app. */
    public function available(Workspace $workspace, Contact $customer, string $currency): string
    {
        if (! WorkspaceApps::for($workspace)->has('sales', Edition::Full)) {
            return '0.00';
        }

        return Decimal::sum($this->sources($workspace, $customer->id, $currency)->pluck('left')->all());
    }

    /** $amount of the invoice's customer's credit applied to it, oldest first - all of it, or refused saying what is left. */
    public function apply(Workspace $workspace, User $user, SalesDocument $invoice, string $amount, string $today): void
    {
        $remaining = $amount;

        foreach ($this->sources($workspace, (int) $invoice->contact_id, $invoice->currency) as $source) {
            if (bccomp($remaining, '0', 2) <= 0) {
                break;
            }

            $locked = $this->rows->lock($source['record']);
            $left   = $locked instanceof CustomerAdvance ? (string) $locked->balance : (string) $locked->balance_due;
            $take   = Decimal::min($left, $remaining);

            if (bccomp($take, '0', 2) <= 0) {
                continue;
            }

            $this->allocations->create($workspace, [
                'invoice_id'          => $invoice->id,
                'customer_advance_id' => $locked instanceof CustomerAdvance ? $locked->id : null,
                'credit_note_id'      => $locked instanceof CustomerAdvance ? null : $locked->id,
                'amount'              => $take,
                'allocation_date'     => $today,
                'created_by'          => $user->id,
            ]);

            if ($locked instanceof CustomerAdvance) {
                $this->customerAdvanceService->refresh($locked);
            } elseif ($locked->source) {
                $this->settlement->settle($locked->source);
            }

            $remaining = bcsub($remaining, $take, 2);
        }

        if (bccomp($remaining, '0', 2) > 0) {
            $this->refuse("The customer's credit is now {$invoice->currency} ".bcsub($amount, $remaining, 2).': take the payment again.');
        }

        $this->settlement->settle($invoice);
    }

    /**
     * $amount of $note's credit applied to $invoice (Phase E3): an exchange's
     * return paying the sale of its replacement - read again and locked.
     */
    public function applyNote(Workspace $workspace, User $user, SalesDocument $invoice, SalesDocument $note, string $amount, string $today): void
    {
        $locked = $this->rows->lock($note);

        if (bccomp($amount, (string) $locked->balance_due, 2) > 0) {
            $this->refuse("{$note->number} has {$invoice->currency} {$locked->balance_due} of credit left now: take the payment again.");
        }

        $this->allocations->create($workspace, [
            'invoice_id' => $invoice->id, 'customer_advance_id' => null, 'credit_note_id' => $locked->id,
            'amount'     => $amount, 'allocation_date' => $today, 'created_by' => $user->id,
        ]);

        if ($locked->source) {
            $this->settlement->settle($locked->source);
        }

        $this->settlement->settle($invoice);
    }

    /**
     * The customer's advances, then credit notes, with credit left in
     * $currency, each oldest first, with what it has left.
     *
     * @return Collection<int, array{record: CustomerAdvance|SalesDocument, left: string}>
     */
    private function sources(Workspace $workspace, int $contactId, string $currency): Collection
    {
        return collect([
            ...$this->advances->openFor($workspace, $contactId, $currency)->map(fn (CustomerAdvance $advance) => ['record' => $advance, 'left' => (string) $advance->balance]),
            ...$this->allocations->openCreditNotesFor($workspace, $contactId, $currency)->map(fn (SalesDocument $note) => ['record' => $note, 'left' => (string) $note->balance_due]),
        ]);
    }
}
