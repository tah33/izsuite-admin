<?php

namespace App\Services\Api\Sales;

use App\Enums\DocumentType;
use App\Enums\SalesDocumentStatus;
use App\Models\User\SalesDocument;
use App\Models\User\SalesDocumentLine;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\DocumentLinkRepository;
use App\Repositories\User\LocationRepository;
use App\Repositories\User\SalesDocumentRepository;
use App\Services\Api\BusinessSetup\ExchangeRateService;
use App\Services\Documents\DocumentNumbers;
use App\Services\Documents\DocumentTemplates;
use App\Services\Sales\SalesCalculator;
use App\Services\Sales\SalesDocumentRules;
use App\Services\Sales\SalesOrderProgress;
use App\Services\Shared\ActivityLogService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
 * chosen again on purpose, never swapped in silently (Doc 08 section 11). So
 * is an exchange rate: an invoice whose own rate differs from the business's
 * for its date is issued at the one chosen.
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
        protected ExchangeRateService $exchangeRateService,
        protected SalesCalculator $salesCalculator,
        protected LocationRepository $locationRepository,
        protected SalesOrderProgress $salesOrderProgress,
        protected SalesOrderInvoicing $salesOrderInvoicing,
    ) {}

    /**
     * A draft sales order confirmed (Doc 16 section 16.9): numbered - the next
     * number, or one typed - with its business and customer kept as they are
     * now, and its terms locked: from here it changes only by Amend. Its
     * delivery status starts as Not Delivered, or Not Required when every line
     * is a service, and nothing is invoiced yet.
     */
    public function confirm(Workspace $workspace, DocumentType $type, int $id, User $user, ?string $number = null): SalesDocument
    {
        $document = $this->allowed($workspace, $type, $id, 'confirm', 'Only a draft sales order can be confirmed.');

        $this->checkReady($workspace, $document);

        $typed = $this->documentNumbers->typed($document->type, $number, fn (string $taken) => $this->salesDocumentRepository->numberTaken($workspace, $document->type, $taken));

        $this->salesDocumentRepository->loadLineItems($document);

        DB::transaction(fn () => $this->salesDocumentRepository->update($document, [
            ...$this->numbered($workspace, $document, $typed),
            'status'          => SalesDocumentStatus::Confirmed,
            'confirmed_at'    => now(),
            'confirmed_by'    => $user->id,
            'delivery_status' => $this->salesOrderProgress->deliveryStatus($document),
            'invoice_status'  => $this->salesOrderProgress->invoiceStatus($document),
            'updated_by'      => $user->id,
        ]));

        return $this->logged($document, 'Confirmed '.$this->salesDocumentService->describe($document));
    }

    /** A confirmed sales order put on hold, with an optional reason: nothing more is done on it until it is resumed. */
    public function hold(Workspace $workspace, DocumentType $type, int $id, User $user, ?string $reason): SalesDocument
    {
        return $this->move($workspace, $type, $id, $user, 'hold', SalesDocumentStatus::OnHold, $reason, 'Only a confirmed sales order can be put on hold.', 'Put %s on hold');
    }

    public function resume(Workspace $workspace, DocumentType $type, int $id, User $user): SalesDocument
    {
        return $this->move($workspace, $type, $id, $user, 'resume', SalesDocumentStatus::Confirmed, null, 'Only a sales order on hold can be resumed.', 'Resumed %s');
    }

    /** A confirmed sales order closed: done with, whatever is left undelivered or uninvoiced. */
    public function complete(Workspace $workspace, DocumentType $type, int $id, User $user): SalesDocument
    {
        return $this->move($workspace, $type, $id, $user, 'complete', SalesDocumentStatus::Completed, null, 'Only a confirmed sales order can be completed.', 'Completed %s');
    }

    /**
     * A credit note is issued against its invoice - see CreditNoteService. An
     * invoice in another currency is issued at its own exchange rate - with
     * $rate 'keep' - or at the business's rate for its date - 'current' - its
     * base figures then worked out again (Doc 08 sections 11 and 18).
     */
    public function issue(Workspace $workspace, DocumentType $type, int $id, User $user, ?string $rate = null, ?string $number = null): SalesDocument
    {
        if ($type === DocumentType::CreditNote) {
            return $this->creditNoteService->issue($workspace, $id, $user, $number);
        }

        $document = $this->allowed($workspace, $type, $id, 'issue', 'Only a draft invoice can be issued.');

        $this->checkReady($workspace, $document);
        // One made from a sales order still fits what the order has left (Doc 16 Phase B2).
        $this->salesOrderInvoicing->checkIssue($workspace, $document);

        $current = $this->rateToIssueAt($workspace, $document, $rate);
        $typed   = $this->documentNumbers->typed($document->type, $number, fn (string $taken) => $this->salesDocumentRepository->numberTaken($workspace, $document->type, $taken));

        DB::transaction(fn () => $this->salesDocumentRepository->update($document, [
            ...($current ? [
                'exchange_rate' => $current,
                ...$this->exchangeRateService->originOf($workspace, $document->currency, $document->document_date, $current),
                ...$this->salesCalculator->baseTotals((string) $document->grand_total, (string) $document->tax_total, $current),
            ] : []),
            ...$this->numbered($workspace, $document, $typed),
            'status'         => SalesDocumentStatus::Issued,
            'payment_status' => bccomp((string) $document->grand_total, '0', 2) > 0 ? SalesDocument::PAYMENT_UNPAID : 'paid',
            'balance_due'    => $document->grand_total,
            'updated_by'     => $user->id,
        ]));

        $this->refreshOrder($document);

        return $this->logged($document, 'Issued '.$this->salesDocumentService->describe($document));
    }

    public function send(Workspace $workspace, DocumentType $type, int $id, User $user): SalesDocument
    {
        $document = $this->allowed($workspace, $type, $id, 'send', 'This can no longer be sent.');
        $again    = $document->status === SalesDocumentStatus::Sent;

        $this->checkReady($workspace, $document);

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

    /**
     * A sales order with invoices still drafted or issued is not cancelled:
     * "Cancelling an order does not silently cancel an existing delivery,
     * invoice or payment" (Doc 16 section 16.9) - they are voided or deleted first.
     */
    public function cancel(Workspace $workspace, DocumentType $type, int $id, User $user, ?string $reason): SalesDocument
    {
        if ($type === DocumentType::SalesOrder && $this->salesDocumentRepository->hasOpenInvoicesFrom($this->salesDocumentService->find($workspace, $type, $id))) {
            $this->refuse('Cancelling the order leaves its invoices as they are. Void or delete them first.');
        }

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

        $this->refreshOrder($document);

        return $this->logged($document, 'Voided '.$this->salesDocumentService->describe($document));
    }

    /** The sales order an invoice was made from, its invoiced quantities counted again (Doc 16 Phase B2). */
    private function refreshOrder(SalesDocument $document): void
    {
        $order = $this->salesOrderInvoicing->orderOf($document);

        if ($order) {
            $this->salesOrderInvoicing->refresh($this->salesDocumentRepository->loadLineItems($order));
        }
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
     * Its number - the one it has, one typed as it is issued ($typed, checked
     * already: Doc 14 section 14.6), or the next - and the business and
     * customer as they are now. Called inside the transaction that saves it,
     * so a failed save gives the number back.
     */
    private function numbered(Workspace $workspace, SalesDocument $document, ?string $typed = null): array
    {
        return [
            'number'            => $document->number ?? $typed ?? $this->documentNumbers->next(
                $workspace,
                $document->type,
                CarbonImmutable::parse($document->document_date),
                fn (string $number) => $this->salesDocumentRepository->numberTaken($workspace, $document->type, $number),
                $document->location_id,
            ),
            'issued_at'         => $document->issued_at ?? now(),
            'business_snapshot' => $this->salesSnapshots->business($workspace, $document->location, $document->type->isPaidFrom() ? $document->currency : null),
            'customer_snapshot' => $this->salesSnapshots->customerOf($document),
            'template_snapshot' => $this->documentTemplates->snapshot($workspace, $document->type),
        ];
    }

    /** A draft may have been saved with any of it left out (see SaveSalesDocumentRequest). */
    private function checkReady(Workspace $workspace, SalesDocument $document): void
    {
        if (! $document->contact) {
            $this->refuse('Choose the customer first.');
        }

        if (! $document->contact->isCustomer()) {
            $this->refuse('Choose a customer first: this contact is not a customer any more.');
        }

        if (! $document->location_id && $this->locationRepository->existsFor($workspace)) {
            $this->refuse('Choose the location first.');
        }

        if ($document->type->isOffer() && ! $document->expiry_date) {
            $this->refuse('Choose until when it is valid first.');
        }

        if ($document->lines->isEmpty()) {
            $this->refuse('Add at least one line first.');
        }

        $unfinished = $document->lines->first(fn (SalesDocumentLine $line) => trim((string) $line->name) === '' || bccomp((string) $line->quantity, '0', 3) <= 0);

        if ($unfinished) {
            $this->refuse("Line {$unfinished->position} needs ".(trim((string) $unfinished->name) === '' ? 'an item or a description' : 'a quantity of more than zero').' first.');
        }

        $this->salesDocumentRepository->loadLineTaxes($document);

        $stale = $document->lines->first(fn (SalesDocumentLine $line) => $line->tax_rate_id && ! $this->taxInEffect($document, $line));

        if ($stale) {
            $this->refuse("{$stale->tax_name} on \"{$stale->name}\" is no longer in effect on {$document->document_date}. Edit it and choose the current rate first.");
        }
    }

    /**
     * The business's rate for a foreign-currency invoice's date, when it is to
     * be issued at that rather than its own: null when it keeps its own, the
     * two agree, or there is no rate for that day. A difference nobody chose
     * about is refused, saying both - a rate is never swapped in silently.
     */
    private function rateToIssueAt(Workspace $workspace, SalesDocument $document, ?string $choice): ?string
    {
        $base    = $workspace->base_currency;
        $current = $document->currency === $base ? null : $this->exchangeRateService->rateOn($workspace, $document->currency, $document->document_date);
        $kept    = (string) $document->exchange_rate;

        if ($current === null || $choice === 'keep' || bccomp($current, $kept, 8) === 0) {
            return null;
        }

        if ($choice !== 'current') {
            $plain = fn (string $rate) => str_contains($rate, '.') ? rtrim(rtrim($rate, '0'), '.') : $rate;
            $day   = date($workspace->date_format, strtotime($document->document_date));

            throw ValidationException::withMessages([
                'exchange_rate' => "This invoice is at 1 {$document->currency} = {$plain($kept)} {$base}, but the rate for {$day} is {$plain($current)} {$base}. Choose which rate to issue it at.",
            ]);
        }

        return $current;
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
