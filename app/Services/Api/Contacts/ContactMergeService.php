<?php

namespace App\Services\Api\Contacts;

use App\Models\User\Contact;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\ContactMergeRepository;
use App\Repositories\User\ContactRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Merging one contact into another (Doc 17 section 17.21, Phase C3), with
 * Merge Contacts. One is kept, and the other:
 *
 * - fills only what the kept one lacks (see ContactMergeFields);
 * - gives it its leads, opportunities, attachments, places on price lists
 *   and the decisions about it as a lead's possible duplicate;
 * - keeps its financial records - sales documents and payments, advances,
 *   purchases, expenses and their payments - where they were made: nothing
 *   financial moves;
 * - is archived, merged into the kept one, never deleted, and no longer
 *   changes.
 *
 * Only two contacts of the same business merge, and the merge is logged on
 * both.
 */
class ContactMergeService
{
    public function __construct(
        protected ContactRepository $contactRepository,
        protected ContactMergeRepository $mergeRepository,
        protected ContactDuplicates $contactDuplicates,
        protected ContactService $contactService,
    ) {}

    /** The contacts $id may be another copy of (Doc 03 section 12): offered first to merge with it. */
    public function candidates(Workspace $workspace, int $id): array
    {
        $contact = $this->contactService->findChangeable($workspace, $id);

        return $this->contactDuplicates->find($workspace, $contact->only(ContactDuplicates::FIELDS), $contact);
    }

    /**
     * What merging $mergedId into $keptId would do: the kept one's changes,
     * what becomes of the other's notes, how many records move and how many
     * financial ones stay.
     *
     * @return array{kept: Contact, merged: Contact, changes: array<string, mixed>, notes: ?string, moving: array<string, int>, staying: array<string, int>}
     */
    public function preview(Workspace $workspace, int $keptId, int $mergedId): array
    {
        [$kept, $merged] = $this->checked($workspace, $keptId, $mergedId);

        return [
            'kept'    => $kept,
            'merged'  => $merged,
            ...ContactMergeFields::of($kept, $merged),
            'moving'  => $this->mergeRepository->moving($merged, $kept),
            'staying' => $this->mergeRepository->staying($merged),
        ];
    }

    /** Merges $mergedId into $keptId, and returns the kept one as its page shows it. */
    public function merge(Workspace $workspace, User $user, int $keptId, int $mergedId): Contact
    {
        $this->checked($workspace, $keptId, $mergedId);

        DB::transaction(function () use ($workspace, $user, $keptId, $mergedId) {
            // Locked, then checked again: two merges at once cannot both take the same contact.
            $locked          = $this->mergeRepository->lockPair($workspace, $keptId, $mergedId);
            [$kept, $merged] = $this->bothMergeable($locked->get($keptId), $locked->get($mergedId));
            $fields          = ContactMergeFields::of($kept, $merged);
            $moving          = array_filter($this->mergeRepository->moving($merged, $kept));

            $this->contactRepository->update($kept, $fields['changes']);
            $this->mergeRepository->move($merged, $kept);
            $this->mergeRepository->markMerged($merged, $kept, $user);

            $description = 'Merged contact '.$this->named($merged).' into '.$this->named($kept);

            ActivityLogService::record('updated', $description, $kept, [
                'source' => 'api',
                'merged' => ['id' => $merged->id, 'contact_number' => $merged->contact_number],
                'fields' => array_keys($fields['changes']),
                'moved'  => $moving,
            ]);
            ActivityLogService::record('updated', $description, $merged, [
                'source'      => 'api',
                'merged_into' => ['id' => $kept->id, 'contact_number' => $kept->contact_number],
            ]);
        });

        return $this->contactService->find($workspace, $keptId);
    }

    /** @return array{0: Contact, 1: Contact} The business's two contacts, when they may merge. */
    private function checked(Workspace $workspace, int $keptId, int $mergedId): array
    {
        $kept = $this->contactRepository->findFor($workspace, $keptId) ?? abort(404, 'Contact not found.');

        return $this->bothMergeable($kept, $this->contactRepository->findFor($workspace, $mergedId));
    }

    /** @return array{0: Contact, 1: Contact} */
    private function bothMergeable(?Contact $kept, ?Contact $merged): array
    {
        $kept ?? abort(404, 'Contact not found.');
        $this->contactService->ensureChangeable($kept);

        if (! $merged) {
            throw ValidationException::withMessages(['merged_id' => 'Choose one of your contacts.']);
        }

        if ($merged->is($kept)) {
            throw ValidationException::withMessages(['merged_id' => 'Choose another contact than this one.']);
        }

        if ($merged->isMerged()) {
            throw ValidationException::withMessages(['merged_id' => 'That contact was already merged into another.']);
        }

        return [$kept, $merged];
    }

    /** Its name, in quotes and cut down, and its number - so the log entry fits its 255-character column. */
    private function named(Contact $contact): string
    {
        return '"'.Str::limit($contact->name, 80).'" ('.$contact->contact_number.')';
    }
}
