<?php

namespace App\Services\Api\Sales;

use App\Enums\DocumentType;
use App\Enums\SalesDocumentStatus;
use App\Models\User\SalesDocument;
use App\Models\User\SalesDocumentLine;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\DocumentLinkRepository;
use App\Repositories\User\SalesDocumentRepository;
use App\Services\Documents\DocumentNumbers;
use App\Services\Documents\DocumentTemplates;
use App\Services\Sales\SalesDocumentRules;
use App\Services\Shared\ActivityLogService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * A sales document's status changes (Doc 08 sections 7 to 10), each allowed
 * only where SalesDocumentRules lists it:
 *
 * - issue: a draft invoice becomes issued - numbered, its business and
 *   customer kept as they are now, its amounts locked, and its grand total
 *   owed (Pay Later, Doc 08 section 3);
 * - send: a quotation or proforma is marked as sent - numbered the first time,
 *   and its business and customer kept as they are now (again, after a
 *   revision). Emailing it comes with Documents & Templates;
 * - accept, reject: a sent quotation's answer;
 * - cancel: a sent or accepted quotation or proforma is withdrawn;
 * - void: an issued invoice is cancelled. Once payments exist, they must be
 *   reversed first (Doc 08 section 8).
 *
 * Before a document is numbered, its customer must still be a customer and
 * each line's tax still in effect on its date - a rate replaced since is
 * chosen again on purpose, never swapped in silently (Doc 08 section 11).
 */
class SalesDocumentFlow
{
    use RefusesConflicts;

    public function __construct(
        protected SalesDocumentRepository $salesDocumentRepository,
        protected SalesDocumentService $salesDocumentService,
        protected SalesDocumentRules $salesDocumentRules,
        protected SalesSnapshots $salesSnapshots,
        protected DocumentNumbers $documentNumbers,
        protected CreditNoteService $creditNoteService,
        protected DocumentTemplates $documentTemplates,
        protected DocumentLinkRepository $documentLinkRepository,
    ) {}

    /** A credit note is issued against its invoice - see CreditNoteService. */
    public function issue(Workspace $workspace, DocumentType $type, int $id, User $user): SalesDocument
    {
        if ($type === DocumentType::CreditNote) {
            return $this->creditNoteService->issue($workspace, $id, $user);
        }

        $document = $this->allowed($workspace, $type, $id, 'issue', 'Only a draft invoice can be issued.');

        $this->checkReady($document);

        DB::transaction(fn () => $this->salesDocumentRepository->update($document, [
            ...$this->numbered($workspace, $document),
            'status'         => SalesDocumentStatus::Issued,
            'payment_status' => bccomp((string) $document->grand_total, '0', 2) > 0 ? SalesDocument::PAYMENT_UNPAID : 'paid',
            'balance_due'    => $document->grand_total,
            'updated_by'     => $user->id,
        ]));

        return $this->logged($document, 'Issued '.$this->salesDocumentService->describe($document));
    }

    public function send(Workspace $workspace, DocumentType $type, int $id, User $user): SalesDocument
    {
        $document = $this->allowed($workspace, $type, $id, 'send', 'This can no longer be sent.');
        $again    = $document->status === SalesDocumentStatus::Sent;

        $this->checkReady($document);

        DB::transaction(fn () => $this->salesDocumentRepository->update($document, [
            ...$this->numbered($workspace, $document),
            'status'     => SalesDocumentStatus::Sent,
            'updated_by' => $user->id,
        ]));

        return $this->logged($document, 'Marked '.$this->salesDocumentService->describe($document).' as sent'.($again ? ' again' : ''));
    }

    public function accept(Workspace $workspace, DocumentType $type, int $id, User $user): SalesDocument
    {
        return $this->move($workspace, $type, $id, $user, 'accept', SalesDocumentStatus::Accepted, null, 'Only a sent quotation that has not expired can be accepted.', 'Marked %s as accepted');
    }

    public function reject(Workspace $workspace, DocumentType $type, int $id, User $user, ?string $reason): SalesDocument
    {
        return $this->move($workspace, $type, $id, $user, 'reject', SalesDocumentStatus::Rejected, $reason, 'Only a sent quotation that has not expired can be rejected.', 'Marked %s as rejected');
    }

    public function cancel(Workspace $workspace, DocumentType $type, int $id, User $user, ?string $reason): SalesDocument
    {
        return $this->move($workspace, $type, $id, $user, 'cancel', SalesDocumentStatus::Cancelled, $reason, 'This cannot be cancelled. A draft is deleted instead.', 'Cancelled %s');
    }

    /**
     * A void invoice owes nothing and counts as no sale (Doc 08 section 24).
     * Only one nothing has settled yet: payments are reversed and credit
     * notes voided first (section 8). A credit note is voided by
     * CreditNoteService.
     */
    public function void(Workspace $workspace, DocumentType $type, int $id, User $user, ?string $reason): SalesDocument
    {
        if ($type === DocumentType::CreditNote) {
            return $this->creditNoteService->void($workspace, $id, $user, $reason);
        }

        $document = $this->allowed($workspace, $type, $id, 'void', $this->salesDocumentService->find($workspace, $type, $id)->status === SalesDocumentStatus::Issued
            ? 'Payments or credit notes have settled some of this invoice. Reverse its payments and void its credit notes first.'
            : 'Only an issued invoice can be voided. A draft is deleted instead.');

        DB::transaction(function () use ($document, $user, $reason) {
            $this->salesDocumentRepository->update($document, [
                'status'         => SalesDocumentStatus::Void,
                'payment_status' => SalesDocument::PAYMENT_NOT_APPLICABLE,
                'balance_due'    => '0',
                'status_reason'  => $reason,
                'updated_by'     => $user->id,
            ]);

            // Its secure links stop working with it.
            $this->documentLinkRepository->revokeFor($document);
        });

        return $this->logged($document, 'Voided '.$this->salesDocumentService->describe($document));
    }

    /** A status change with nothing more to it than the status and an optional reason. */
    private function move(Workspace $workspace, DocumentType $type, int $id, User $user, string $action, SalesDocumentStatus $status, ?string $reason, string $refusal, string $log): SalesDocument
    {
        $document = $this->allowed($workspace, $type, $id, $action, $refusal);

        DB::transaction(function () use ($document, $user, $status, $reason) {
            $this->salesDocumentRepository->update($document, ['status' => $status, 'status_reason' => $reason, 'updated_by' => $user->id]);

            // A cancelled document's secure links stop working with it.
            if ($status === SalesDocumentStatus::Cancelled) {
                $this->documentLinkRepository->revokeFor($document);
            }
        });

        return $this->logged($document, sprintf($log, $this->salesDocumentService->describe($document)));
    }

    private function allowed(Workspace $workspace, DocumentType $type, int $id, string $action, string $refusal): SalesDocument
    {
        $document = $this->salesDocumentService->find($workspace, $type, $id);

        if (! $this->salesDocumentRules->allows($document, $action, $this->salesDocumentService->today($workspace))) {
            $this->refuse($refusal);
        }

        return $document;
    }

    /**
     * Its number - the one it has, or the next - and the business and customer
     * as they are now. Called inside the transaction that saves it, so a
     * failed save gives the number back.
     */
    private function numbered(Workspace $workspace, SalesDocument $document): array
    {
        return [
            'number'            => $document->number ?? $this->documentNumbers->next(
                $workspace,
                $document->type,
                CarbonImmutable::parse($document->document_date),
                fn (string $number) => $this->salesDocumentRepository->numberTaken($workspace, $document->type, $number),
            ),
            'issued_at'         => $document->issued_at ?? now(),
            'business_snapshot' => $this->salesSnapshots->business($workspace, $document->location, $document->type->isPaidFrom() ? $document->currency : null),
            'customer_snapshot' => $this->salesSnapshots->customer($document->contact),
            'template_snapshot' => $this->documentTemplates->snapshot($workspace, $document->type),
        ];
    }

    private function checkReady(SalesDocument $document): void
    {
        if (! $document->contact?->isCustomer()) {
            $this->refuse('Choose a customer first: this contact is not a customer any more.');
        }

        if ($document->lines->isEmpty()) {
            $this->refuse('Add at least one line first.');
        }

        $this->salesDocumentRepository->loadLineTaxes($document);

        $stale = $document->lines->first(fn (SalesDocumentLine $line) => $line->tax_rate_id && ! $this->taxInEffect($document, $line));

        if ($stale) {
            $this->refuse("{$stale->tax_name} on \"{$stale->name}\" is no longer in effect on {$document->document_date}. Edit it and choose the current rate first.");
        }
    }

    private function taxInEffect(SalesDocument $document, SalesDocumentLine $line): bool
    {
        $rate = $line->taxRate;

        return $rate && $rate->is_active && $rate->effective_from <= $document->document_date
            && (! $rate->effective_to || $rate->effective_to >= $document->document_date);
    }

    private function logged(SalesDocument $document, string $description): SalesDocument
    {
        // Logged here, by what happened, so LogApiWriteActivity does not add
        // its own vaguer entry.
        ActivityLogService::record('updated', $description, $document, ['source' => 'api', 'status' => $document->status->value]);

        return $this->salesDocumentRepository->loadShown($document);
    }
}
