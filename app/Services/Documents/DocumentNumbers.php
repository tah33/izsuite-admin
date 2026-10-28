<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Models\User\DocumentNumbering;
use App\Models\User\Workspace;
use App\Repositories\User\DocumentNumberingRepository;
use App\Services\Shared\BusinessCalendar;
use App\Services\User\Workspaces\WorkspaceSequence;
use Carbon\CarbonInterface;

/**
 * The numbering service (Doc 14 section 14.6): the permanent number a document
 * gets when it is issued, sent or recorded, and a preview of the next one.
 *
 * Each document type is numbered its own way (DocumentNumbering), from a
 * workspace_sequences counter per type, prefix and period: "invoice|INV-|2026".
 * A new prefix starts a run of its own and going back to an old one carries on
 * where it stopped, so a prefix never repeats a number. The number never goes
 * below the starting number: raising that jumps ahead, lowering it never goes
 * back. The period is that of the document's own date, in the business's
 * financial year where it counts in those.
 *
 * Numbers can still meet across runs - INV-2026-00001 numbered by year, then
 * again by a prefix "INV-2026-" - so next() asks the caller which numbers are
 * already taken, and skips them. Gaps are allowed (Doc 14 section 14.6);
 * repeats never are.
 */
class DocumentNumbers
{
    public function __construct(
        private DocumentNumberingRepository $documentNumberingRepository,
        private WorkspaceSequence $workspaceSequence,
        private BusinessCalendar $businessCalendar,
    ) {}

    /** How $type is numbered: the business's own way, or the defaults. */
    public function scheme(Workspace $workspace, DocumentType $type): DocumentNumbering
    {
        return $this->documentNumberingRepository->findFor($workspace, $type) ?? DocumentNumbering::defaultsFor($type);
    }

    /** The number the next document dated $date would get under $scheme - nothing is taken. */
    public function preview(Workspace $workspace, DocumentNumbering $scheme, CarbonInterface $date): string
    {
        $period = $this->period($workspace, $scheme, $date);

        return $this->format($scheme, $period, $this->workspaceSequence->peek($workspace->id, $this->counter($scheme, $period), $scheme->starting_number));
    }

    /**
     * Takes the next number for a $type dated $date. $taken is asked whether a
     * number is already on a document; one that is, is skipped. Call it inside
     * the transaction that saves the document, so a failed save gives the
     * number back.
     *
     * @param  (callable(string): bool)|null  $taken
     */
    public function next(Workspace $workspace, DocumentType $type, CarbonInterface $date, ?callable $taken = null): string
    {
        $scheme  = $this->scheme($workspace, $type);
        $period  = $this->period($workspace, $scheme, $date);
        $counter = $this->counter($scheme, $period);

        do {
            $number = $this->format($scheme, $period, $this->workspaceSequence->next($workspace->id, $counter, $scheme->starting_number));
        } while ($taken && $taken($number));

        return $number;
    }

    /** INV-00026, INV-2026-00026, INV-2026-27-00026: the prefix, the period if any, the padded number. */
    public function format(DocumentNumbering $scheme, ?string $period, int $value): string
    {
        $digits = str_pad((string) $value, $scheme->number_length, '0', STR_PAD_LEFT);

        return $scheme->prefix.($period !== null ? "{$period}-" : '').$digits;
    }

    private function period(Workspace $workspace, DocumentNumbering $scheme, CarbonInterface $date): ?string
    {
        return $scheme->period->key($date, $this->businessCalendar->financialYearStart($workspace));
    }

    private function counter(DocumentNumbering $scheme, ?string $period): string
    {
        return implode('|', [$scheme->document_type->value, $scheme->prefix, $period ?? '']);
    }
}
