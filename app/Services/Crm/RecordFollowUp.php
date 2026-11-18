<?php

namespace App\Services\Crm;

use App\Models\User\CrmLead;
use App\Models\User\CrmOpportunity;
use App\Models\User\Workspace;
use App\Repositories\User\CrmActivityRepository;
use App\Repositories\User\CrmEmailRepository;
use Carbon\CarbonInterface;

/**
 * A lead's or an opportunity's response and follow-up (Doc 17 section 17.23
 * - Phase G3a), for its page:
 *
 * - a lead's first assignment and first contact, and its response time: how
 *   long after it was added the first contact came;
 * - its last contact - the latest activity done or e-mail sent, and for a
 *   lead its latest marking as contacted - and how;
 * - its next activity: the soonest planned one, overdue once its moment has
 *   passed, named for whoever may view activities;
 * - and while it is open, its full days without activity: since its last
 *   contact, or since it was added. A converted, unqualified or archived
 *   lead - or a won, lost or archived opportunity - counts none.
 *
 * Its time at its stage is the opportunity's own (Phase D3a).
 */
class RecordFollowUp
{
    public function __construct(
        protected CrmActivityRepository $activities,
        protected CrmEmailRepository $emails,
    ) {}

    /** @return array{first_assigned_at: ?string, first_contacted_at: ?string, response_seconds: ?int, last_contact: ?array, next_activity: ?array, days_without_activity: ?int} */
    public function lead(Workspace $workspace, CrmLead $lead, bool $namesActivities): array
    {
        $marked = $lead->contacted_at ? ['at' => $lead->contacted_at, 'by' => 'marked'] : null;

        return [
            'first_assigned_at'  => $lead->first_assigned_at?->toIso8601String(),
            'first_contacted_at' => $lead->first_contacted_at?->toIso8601String(),
            'response_seconds'   => $lead->first_contacted_at ? max(0, (int) $lead->created_at->diffInSeconds($lead->first_contacted_at)) : null,
            ...$this->since($workspace, 'lead_id', $lead->id, $lead->isOpen() ? $lead->created_at : null, $marked, $namesActivities),
        ];
    }

    /** @return array{last_contact: ?array, next_activity: ?array, days_without_activity: ?int} */
    public function opportunity(Workspace $workspace, CrmOpportunity $opportunity, bool $namesActivities): array
    {
        $open = $opportunity->status === CrmOpportunity::OPEN && ! $opportunity->isArchived();

        return $this->since($workspace, 'opportunity_id', $opportunity->id, $open ? $opportunity->created_at : null, null, $namesActivities);
    }

    /**
     * What a lead and an opportunity share: the record whose $column is $id,
     * $addedWhileOpen being when it was added while it is open, else null.
     *
     * @param  array{at: CarbonInterface, by: string}|null  $marked
     */
    private function since(Workspace $workspace, string $column, int $id, ?CarbonInterface $addedWhileOpen, ?array $marked, bool $namesActivities): array
    {
        $done = $this->activities->lastDoneAt($workspace, $column, $id);
        $sent = $this->emails->lastSentAt($workspace, $column, $id);
        $last = collect([$done ? ['at' => $done, 'by' => 'activity'] : null, $sent ? ['at' => $sent, 'by' => 'email'] : null, $marked])
            ->filter()
            ->sortByDesc(fn (array $contact) => $contact['at']->getTimestamp())
            ->first();
        $next = $this->activities->nextPlanned($workspace, $column, $id);
        $now  = now();

        return [
            'last_contact'          => $last ? ['at' => $last['at']->toIso8601String(), 'by' => $last['by']] : null,
            'next_activity'         => $next ? [
                'at'      => $next->due_at->toIso8601String(),
                'overdue' => $next->due_at->lt($now),
                'label'   => $namesActivities ? $next->label() : null,
            ] : null,
            'days_without_activity' => $addedWhileOpen ? (int) floor(max(0, ($last['at'] ?? $addedWhileOpen)->diffInSeconds($now)) / 86400) : null,
        ];
    }
}
