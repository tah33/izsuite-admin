<?php

namespace App\Services\Api\Crm;

use App\Enums\DocumentType;
use App\Models\Shared\ActivityLog;
use App\Models\Shared\RecordComment;
use App\Models\User\Contact;
use App\Models\User\CrmActivity;
use App\Models\User\CrmEmail;
use App\Models\User\CrmLead;
use App\Models\User\CrmOpportunity;
use App\Models\User\SalesDocument;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\CrmLeadRepository;
use App\Repositories\User\CrmOpportunityRepository;
use App\Repositories\User\CrmTimelineRepository;
use App\Services\Crm\LeadVisibility;
use App\Services\Crm\OpportunityDocuments;
use App\Services\Crm\OpportunityVisibility;
use App\Services\Sales\SalesDocumentRules;
use App\Services\Shared\BusinessCalendar;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Support\Collection;

/**
 * The timeline of a lead, an opportunity or a contact (Doc 17 section 17.18
 * - Phase E3): what happened with them, newest first, in one list -
 *
 * - the activities done or cancelled: calls with their direction and
 *   outcome, meetings and the rest, for whoever may view activities;
 * - notes: the comments on a lead or an opportunity, the team's own;
 * - the e-mails sent from it (Phase F3a), and whether each went;
 * - its changes, from the Activity Log: added, changed, given to someone,
 *   moved between stages, won, lost, reopened, archived, converted;
 * - its files, added and removed;
 * - and a contact's sales documents, or those made from an opportunity
 *   (Phase F1), of the kinds the user may view, at the locations they may
 *   use, with their totals for those who see Sales' values: each when it was
 *   made, and again when it was sent, issued or confirmed.
 *
 * A contact's gathers too what happened with its opportunities, and with the
 * leads converted into it, that the user sees. What is still planned is on
 * the Activities card, not here. All of it is the team's own: none of it
 * reaches the customer.
 */
class CrmTimelineService
{
    /** The newest this many, of each part and in all. */
    public const SHOWN = 100;

    /** What it can be narrowed to. */
    public const SHOWS = ['all', 'activities', 'notes', 'emails', 'changes', 'files', 'documents'];

    public function __construct(
        protected CrmTimelineRepository $timeline,
        protected CrmLeadService $leads,
        protected CrmOpportunityService $opportunities,
        protected CrmLeadRepository $leadRepository,
        protected CrmOpportunityRepository $opportunityRepository,
        protected ContactRepository $contacts,
        protected LeadVisibility $leadVisibility,
        protected OpportunityVisibility $opportunityVisibility,
        protected CrmSettingService $settings,
        protected OpportunityDocuments $opportunityDocuments,
        protected SalesDocumentRules $documentRules,
        protected BusinessCalendar $calendar,
        protected WorkspaceAccess $access,
    ) {}

    /**
     * The entries of the lead, opportunity or contact - $kind - that $id is,
     * newest first, only those $show names unless it is "all". Each is its
     * kind (activity, note, change, file or document), when, its record (a
     * CrmActivity, RecordComment, ActivityLog or SalesDocument) and, on a
     * contact's, the lead or opportunity it is about. A document's has its
     * status as shown and whether its total shows.
     *
     * @return Collection<int, array{kind: string, at: \DateTimeInterface, record: \Illuminate\Database\Eloquent\Model, for: array{kind: string, id: int, name: string}|null, values?: bool}>
     */
    public function timeline(Workspace $workspace, User $user, string $kind, int $id, string $show): Collection
    {
        [$leads, $opportunities, $contact] = $this->records($workspace, $user, $kind, $id);

        $wants = fn (string $part) => $show === 'all' || $show === $part;
        // On a contact's, what else an entry is about: one of its leads or opportunities.
        $named = function (string $what, ?int $recordId) use ($kind, $leads, $opportunities): ?array {
            $record = $kind === 'contact' && $recordId ? ($what === 'lead' ? $leads : $opportunities)->get($recordId) : null;

            return $record ? ['kind' => $what, 'id' => $record->id, 'name' => $record->name] : null;
        };
        $about = [CrmLead::class => $leads->keys()->all(), CrmOpportunity::class => $opportunities->keys()->all()];

        $entries = collect();

        if ($wants('changes') || $wants('files')) {
            $logged  = $this->timeline->logEntries($workspace->id, [...$about, Contact::class => $contact ? [$contact->id] : []], $show === 'all' ? null : $show === 'files', self::SHOWN);
            $entries = $entries->concat($logged->map(fn (ActivityLog $entry) => [
                'kind' => isset($entry->properties['file']) ? 'file' : 'change', 'at' => $entry->created_at, 'record' => $entry, 'for' => null,
            ]));
        }

        if ($wants('activities') && $this->access->allows($user, $workspace, 'crm.activities.view')) {
            $ended   = $this->timeline->endedActivities($workspace, ['lead_id' => $about[CrmLead::class], 'opportunity_id' => $about[CrmOpportunity::class], 'contact_id' => $contact ? [$contact->id] : []], self::SHOWN);
            $entries = $entries->concat($ended->map(fn (CrmActivity $activity) => [
                'kind' => 'activity', 'at' => $activity->completed_at ?? $activity->cancelled_at, 'record' => $activity,
                'for'  => $named('opportunity', $activity->opportunity_id) ?? $named('lead', $activity->lead_id),
            ]));
        }

        // Phase F3a: the e-mails sent from them.
        if ($wants('emails')) {
            $sent    = $this->timeline->emails($workspace, ['lead_id' => $about[CrmLead::class], 'opportunity_id' => $about[CrmOpportunity::class], 'contact_id' => $contact ? [$contact->id] : []], self::SHOWN);
            $entries = $entries->concat($sent->map(fn (CrmEmail $email) => [
                'kind' => 'email', 'at' => $email->created_at, 'record' => $email,
                'for'  => $named('opportunity', $email->opportunity_id) ?? $named('lead', $email->lead_id),
            ]));
        }

        if ($wants('notes')) {
            $comments = $this->timeline->comments($workspace->id, $about, self::SHOWN);
            $entries  = $entries->concat($comments->map(fn (RecordComment $comment) => [
                'kind' => 'note', 'at' => $comment->created_at, 'record' => $comment,
                'for'  => $named($comment->commentable_type === CrmLead::class ? 'lead' : 'opportunity', $comment->commentable_id),
            ]));
        }

        if ($kind !== 'lead' && $wants('documents')) {
            $entries = $entries->concat($this->documents($workspace, $contact ? ['contact_id' => $contact->id] : ['opportunity_id' => $id]));
        }

        return $entries
            // A document sent, issued or confirmed as it was made comes after - above - its making.
            ->sort(fn (array $a, array $b) => [$b['at']->getTimestamp(), $b['record']->getKey(), $b['later'] ?? false] <=> [$a['at']->getTimestamp(), $a['record']->getKey(), $a['later'] ?? false])
            ->take(self::SHOWN)
            ->values();
    }

    /**
     * The leads and the opportunities whose timeline it is - by id, with
     * their names - and the contact, when it is a contact's: a lead or an
     * opportunity the user sees, or a contact of the business with the
     * leads converted into it and the opportunities that they see.
     *
     * @return array{0: Collection<int, CrmLead>, 1: Collection<int, CrmOpportunity>, 2: Contact|null}
     */
    private function records(Workspace $workspace, User $user, string $kind, int $id): array
    {
        if ($kind === 'lead') {
            $lead = $this->leads->find($workspace, $user, $id);

            return [collect([$lead->id => $lead]), collect(), null];
        }

        if ($kind === 'opportunity') {
            $opportunity = $this->opportunities->find($workspace, $user, $id);

            return [collect(), collect([$opportunity->id => $opportunity]), null];
        }

        $contact = $this->contacts->findFor($workspace, $id) ?? abort(404, 'Contact not found.');

        $leads = $this->settings->usesLeads($workspace) && $this->access->allows($user, $workspace, 'crm.leads.view')
            ? $this->leadRepository->ofContact($workspace, $this->leadVisibility->scopeFor($workspace, $user), $contact->id)
            : collect();
        $opportunities = $this->access->allows($user, $workspace, 'crm.opportunities.view')
            ? $this->opportunityRepository->ofContact($workspace, $this->opportunityVisibility->scopeFor($workspace, $user), $contact->id)
            : collect();

        return [$leads, $opportunities, $contact];
    }

    /**
     * The sales documents of a contact, or made from an opportunity - $where
     * - that the user may see (OpportunityDocuments::madeFor()), as entries:
     * each made, then, once numbered, sent (a quotation or proforma), issued
     * (an invoice or credit note) or confirmed (a sales order).
     */
    private function documents(Workspace $workspace, array $where): Collection
    {
        $today  = $this->calendar->today($workspace)->toDateString();
        $values = $this->access->seesValues('sales');

        return $this->opportunityDocuments->madeFor($workspace, $where)->toBase()->flatMap(function (SalesDocument $document) use ($today, $values) {
            $entry = ['kind' => 'document', 'for' => null, 'values' => $values, 'record' => $document->setAttribute('shown_status', $this->documentRules->status($document, $today))];
            $steps = [[...$entry, 'at' => $document->created_at, 'step' => 'made']];

            if ($document->issued_at) {
                $steps[] = [...$entry, 'later' => true, 'at' => $document->confirmed_at ?? $document->issued_at, 'step' => match ($document->type) {
                    DocumentType::Quotation, DocumentType::Proforma => 'sent',
                    DocumentType::SalesOrder                        => 'confirmed',
                    default                                         => 'issued',
                }];
            }

            return $steps;
        });
    }
}
