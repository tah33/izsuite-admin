<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use App\Models\User\DocumentNumbering;
use App\Models\User\Workspace;
use App\Repositories\User\DocumentNumberingRepository;
use App\Repositories\User\LocationRepository;
use App\Services\Shared\BusinessCalendar;
use App\Services\Shared\WorkspaceAccess;
use App\Services\User\Workspaces\WorkspaceSequence;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

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
 *
 * A type numbered with the location's code (Doc 11 section 21) runs on per
 * location - INV-DHK-00001 and INV-CTG-00001 - each from its own counter. A
 * record with no location takes the Primary's code, as a preview does.
 */
class DocumentNumbers
{
    public function __construct(
        private DocumentNumberingRepository $documentNumberingRepository,
        private WorkspaceSequence $workspaceSequence,
        private BusinessCalendar $businessCalendar,
        private LocationRepository $locationRepository,
        private WorkspaceAccess $access,
    ) {}

    /**
     * The number typed for a document as it is issued or recorded (Doc 14
     * section 14.6), as it is to be kept: null when none was typed, and the
     * next number comes as usual. Typing one needs Enter Numbers by Hand
     * (documents.number), and the number must not be on another document of
     * its kind ($taken). A typed number that is taken is never put on, and
     * next() skips it later on.
     *
     * @param  callable(string): bool  $taken
     */
    public function typed(DocumentType $type, ?string $typed, callable $taken): ?string
    {
        $number = trim((string) $typed);

        if ($number === '') {
            return null;
        }

        if (! $this->access->can('documents.number')) {
            throw ValidationException::withMessages(['number' => 'Your role does not allow typing a number. Leave it empty for the next one.']);
        }

        if ($taken($number)) {
            $noun = strtolower($type->label());

            throw ValidationException::withMessages(['number' => "{$number} is on another {$noun} already. Choose another number."]);
        }

        return $number;
    }

    /** How $type is numbered: the business's own way, or the defaults. */
    public function scheme(Workspace $workspace, DocumentType $type): DocumentNumbering
    {
        return $this->documentNumberingRepository->findFor($workspace, $type) ?? DocumentNumbering::defaultsFor($type);
    }

    /**
     * The number the next document dated $date would get under $scheme - at
     * the location of $locationId, or the Primary. Nothing is taken.
     */
    public function preview(Workspace $workspace, DocumentNumbering $scheme, CarbonInterface $date, ?int $locationId = null): string
    {
        $period = $this->period($workspace, $scheme, $date);
        $code   = $this->code($workspace, $scheme, $locationId);

        return $this->format($scheme, $period, $this->workspaceSequence->peek($workspace->id, $this->counter($scheme, $period, $code), $scheme->starting_number), $code);
    }

    /**
     * Takes the next number for a $type dated $date. $taken is asked whether a
     * number is already on a document; one that is, is skipped. Call it inside
     * the transaction that saves the document, so a failed save gives the
     * number back. $locationId is the record's location.
     *
     * @param  (callable(string): bool)|null  $taken
     */
    public function next(Workspace $workspace, DocumentType $type, CarbonInterface $date, ?callable $taken = null, ?int $locationId = null): string
    {
        $scheme  = $this->scheme($workspace, $type);
        $period  = $this->period($workspace, $scheme, $date);
        $code    = $this->code($workspace, $scheme, $locationId);
        $counter = $this->counter($scheme, $period, $code);

        do {
            $number = $this->format($scheme, $period, $this->workspaceSequence->next($workspace->id, $counter, $scheme->starting_number), $code);
        } while ($taken && $taken($number));

        return $number;
    }

    /**
     * INV-00026, INV-2026-00026, INV-DHK-2026-00026: the prefix, the location's
     * code and the period if any, the padded number.
     */
    public function format(DocumentNumbering $scheme, ?string $period, int $value, ?string $code = null): string
    {
        $digits = str_pad((string) $value, $scheme->number_length, '0', STR_PAD_LEFT);

        return $scheme->prefix.($code !== null ? "{$code}-" : '').($period !== null ? "{$period}-" : '').$digits;
    }

    private function period(Workspace $workspace, DocumentNumbering $scheme, CarbonInterface $date): ?string
    {
        return $scheme->period->key($date, $this->businessCalendar->financialYearStart($workspace));
    }

    /** The location code a number of $scheme carries: its location's, else the Primary's - null when the scheme carries none. */
    private function code(Workspace $workspace, DocumentNumbering $scheme, ?int $locationId): ?string
    {
        if (! $scheme->location_code) {
            return null;
        }

        $location = $locationId ? $this->locationRepository->findFor($workspace, $locationId) : null;

        return ($location ?? $this->locationRepository->primaryFor($workspace))?->code;
    }

    /** A location's own run is counted apart: "invoice|INV-||DHK". */
    private function counter(DocumentNumbering $scheme, ?string $period, ?string $code = null): string
    {
        return implode('|', [$scheme->document_type->value, $scheme->prefix, $period ?? '', ...($code !== null ? [$code] : [])]);
    }
}
