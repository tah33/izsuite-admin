<?php

namespace App\Services\Api\Crm;

use App\Enums\FileCollection;
use App\Enums\SalesDocumentStatus;
use App\Jobs\SendCrmEmail;
use App\Models\User\Contact;
use App\Models\User\CrmEmail;
use App\Models\User\CrmLead;
use App\Models\User\CrmOpportunity;
use App\Models\User\SalesDocument;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceFile;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\CrmEmailRepository;
use App\Repositories\User\EmailSettingRepository;
use App\Repositories\User\WorkspaceFileRepository;
use App\Services\Api\Documents\EmailSettingService;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Crm\OpportunityDocuments;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * E-mail from a lead, an opportunity or a contact (Doc 17 section 17.19 -
 * Phase F3a): to the record's address to start, with a greeting and the
 * sender's and business's names, carrying the record's sales documents as
 * PDFs - those past their draft, not void or cancelled, of the kinds the user
 * views - and its files, up to five of each and 15 MB of files. It goes out
 * from the queue, from the platform's address under the business's sender
 * name with replies to the business, as the document e-mails do, and stays
 * on the record's timeline with whether it went. Replies and
 * synchronisation wait for an e-mail integration.
 *
 * Only from a record the user sees, while it is in use: not a converted or
 * archived lead, an archived opportunity or a merged contact.
 */
class CrmEmailService
{
    use RefusesConflicts;

    public function __construct(
        protected CrmEmailRepository $emails,
        protected CrmLeadService $leads,
        protected CrmOpportunityService $opportunities,
        protected ContactRepository $contacts,
        protected OpportunityDocuments $opportunityDocuments,
        protected WorkspaceFileRepository $files,
        protected EmailSettingRepository $emailSettings,
        protected EmailSettingService $emailSettingService,
        protected WorkspaceAccess $access,
    ) {}

    /**
     * What the form starts with for the lead, opportunity or contact - $kind
     * - that $id is: its address, a message with a greeting and the user's
     * and business's names, and what it can carry.
     *
     * @return array{to: ?string, body: string, documents: Collection<int, SalesDocument>, files: Collection<int, WorkspaceFile>}
     */
    public function draft(Workspace $workspace, User $user, string $kind, int $id): array
    {
        $record = $this->record($workspace, $user, $kind, $id);
        [$address, $name] = $this->addressee($workspace, $record);
        $sender = $this->emailSettingService->senderName($workspace, $this->emailSettings->forWorkspace($workspace));

        return [
            'to'        => $address,
            'body'      => ($name ? "Dear {$name},\n\n" : '')."\n\nKind regards,\n{$user->name}\n{$sender}",
            'documents' => $this->attachableDocuments($workspace, $record),
            'files'     => $this->files->inCollection($record, FileCollection::Attachments),
        ];
    }

    /** $data is validated (see SendCrmEmailRequest): from exactly one record. */
    public function send(Workspace $workspace, User $user, array $data): CrmEmail
    {
        $kind   = collect(CrmEmail::FOR)->search(fn (string $column) => ! empty($data[$column]));
        $record = $this->record($workspace, $user, $kind, (int) $data[CrmEmail::FOR[$kind]]);

        $documents = $this->chosen($this->attachableDocuments($workspace, $record), $data['documents'] ?? [], 'documents', 'Choose among its sales documents that have gone out.');
        $files     = $this->chosen($this->files->inCollection($record, FileCollection::Attachments), $data['files'] ?? [], 'files', 'Choose among its own files.');

        if ($files->sum('size') > CrmEmail::MAX_FILE_BYTES) {
            throw ValidationException::withMessages(['files' => 'Together the files come to more than 15 MB. Attach fewer.']);
        }

        $email = $this->emails->create($workspace, [
            CrmEmail::FOR[$kind] => $record->getKey(),
            'to'                 => $data['to'],
            'cc'                 => $data['cc'] ?? [],
            'subject'            => $data['subject'],
            'body'               => $data['body'],
            'attachments'        => [
                ...$documents->map(fn (SalesDocument $document) => ['kind' => 'document', 'id' => $document->id, 'name' => $this->documentName($document)])->values()->all(),
                ...$files->map(fn (WorkspaceFile $file) => ['kind' => 'file', 'id' => $file->id, 'name' => $file->name])->values()->all(),
            ],
            'sent_by'            => $user->id,
        ]);

        // Its timeline shows the e-mail itself: this entry names it, so it is not shown twice.
        ActivityLogService::record('created', "E-mailed {$data['to']} from ".$this->describe($kind, $record).': "'.Str::limit($data['subject'], 100).'"', $record, ['source' => 'api', 'email_id' => $email->id]);

        SendCrmEmail::dispatch($email->id);

        return $email;
    }

    /**
     * The lead, opportunity or contact - $kind - that $id is, if $user may
     * view its kind, sees it, and it is in use: else not found, forbidden or
     * refused.
     */
    private function record(Workspace $workspace, User $user, string $kind, int $id): Model
    {
        $permission = ['lead' => 'crm.leads.view', 'opportunity' => 'crm.opportunities.view', 'contact' => 'contacts.view'][$kind];

        if (! $this->access->allows($user, $workspace, $permission)) {
            abort(403, 'Your role does not allow this.');
        }

        $record = match ($kind) {
            'lead'        => $this->leads->find($workspace, $user, $id),
            'opportunity' => $this->opportunities->find($workspace, $user, $id),
            default       => $this->contacts->findFor($workspace, $id) ?? abort(404, 'Contact not found.'),
        };

        match (true) {
            $record instanceof CrmLead && $record->status === CrmLead::CONVERTED => $this->refuse('This lead was converted: e-mail its contact or opportunity instead.'),
            $record instanceof CrmLead && $record->status === CrmLead::ARCHIVED  => $this->refuse('This lead is archived: reopen it to e-mail from it.'),
            $record instanceof CrmOpportunity && $record->isArchived()           => $this->refuse('This opportunity is archived: restore it to e-mail from it.'),
            $record instanceof Contact && $record->isMerged()                    => $this->refuse('This contact was merged into another: e-mail from that one.'),
            default                                                              => null,
        };

        return $record;
    }

    /**
     * Whom it goes to, to start, and the name its greeting uses: the person,
     * else the business - an opportunity's contact's, read whole.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function addressee(Workspace $workspace, Model $record): array
    {
        if ($record instanceof CrmOpportunity) {
            $contact = $this->contacts->findFor($workspace, $record->contact_id);

            return [$contact?->email, $record->contact_person ?: $contact?->contact_person ?: $contact?->name];
        }

        return $record instanceof CrmLead ? [$record->email, $record->name] : [$record->email, $record->contact_person ?: $record->name];
    }

    /**
     * The record's sales documents an e-mail can carry: an opportunity's or a
     * contact's that the user sees, once out of draft and still standing -
     * not void or cancelled. A lead has none.
     *
     * @return Collection<int, SalesDocument>
     */
    private function attachableDocuments(Workspace $workspace, Model $record): Collection
    {
        if ($record instanceof CrmLead) {
            return collect();
        }

        $where = $record instanceof CrmOpportunity ? ['opportunity_id' => $record->id] : ['contact_id' => $record->id];

        return $this->opportunityDocuments->madeFor($workspace, $where)->toBase()
            ->filter(fn (SalesDocument $document) => ! in_array($document->status, [SalesDocumentStatus::Draft, SalesDocumentStatus::Void, SalesDocumentStatus::Cancelled], true))
            ->values();
    }

    /**
     * Of $available, those $ids name - all of them, or refused under $field.
     *
     * @param  list<int>  $ids
     */
    private function chosen(Collection $available, array $ids, string $field, string $refusal): Collection
    {
        $chosen = $available->whereIn('id', $ids)->values();

        if ($chosen->count() !== count(array_unique($ids))) {
            throw ValidationException::withMessages([$field => $refusal]);
        }

        return $chosen;
    }

    /** "Quotation QUO-00001": what its PDF is called in the timeline. */
    private function documentName(SalesDocument $document): string
    {
        return trim(($document->is_deposit ? 'Deposit Invoice' : $document->type->label()).' '.$document->number);
    }

    private function describe(string $kind, Model $record): string
    {
        return match ($kind) {
            'lead'        => $this->leads->describe($record),
            'opportunity' => $this->opportunities->describe($record),
            default       => 'contact "'.Str::limit($record->name, 100).'"',
        };
    }
}
