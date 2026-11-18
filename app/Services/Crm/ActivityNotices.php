<?php

namespace App\Services\Crm;

use App\Models\User\CrmActivity;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\CrmActivityRepository;
use App\Services\Shared\BusinessCalendar;
use App\Services\Shared\Notifications\Notice;
use App\Services\Shared\Notifications\Notifier;
use App\Services\User\Apps\WorkspaceApps;

/**
 * What CRM tells people about activities (Doc 17 section 17.17 - Phase E2),
 * in the bell and by e-mail under the Activities kind, as each user and the
 * business have chosen:
 *
 * - whom an activity is given to - unless they gave it to themselves - when
 *   it is planned, given to someone else, or planned as a follow-up;
 * - a planned activity's assignee, before it is due, as its reminder says -
 *   once, while CRM is on and they may still be given activities.
 *
 * A notice leads to the activity's record when its assignee sees it, else to
 * the Activities page. Its times are the business's, in its own formats.
 */
class ActivityNotices
{
    /** How many reminders one run sends at most; the next run, a minute on, takes the rest. */
    public const BATCH = 500;

    public function __construct(
        protected Notifier $notifier,
        protected ActivityAssignments $assignments,
        protected ActivityVisibility $visibility,
        protected CrmActivityRepository $activities,
        protected BusinessCalendar $calendar,
    ) {}

    public function assigned(Workspace $workspace, CrmActivity $activity, User $by): void
    {
        $assignee = $activity->assignee;

        if (! $assignee || $assignee->id === $by->id) {
            return;
        }

        $this->notifier->send($workspace, [$assignee], new Notice(
            trigger: 'activities.assigned',
            key: "activities.assigned:{$activity->id}:{$assignee->id}:".$activity->updated_at?->getTimestamp(),
            title: "{$by->name} gave you an activity: {$activity->label()}",
            body: 'Due '.$this->when($workspace, $activity).($activity->regarding() ? ', for '.$activity->regarding()->name : ''),
            link: $this->link($workspace, $assignee, $activity),
            subject: $activity,
            email: true,
        ), $by);
    }

    /** Each reminder whose moment has come, sent once (see the class): how many reached someone. */
    public function remindDue(): int
    {
        $sent   = 0;
        $crmOn  = [];
        $people = [];

        foreach ($this->activities->dueReminders(now(), self::BATCH) as $activity) {
            // Another run sent it already.
            if (! $this->activities->claimReminder($activity)) {
                continue;
            }

            $workspace = $activity->workspace;
            $assignee  = $activity->assignee;

            $crmOn[$workspace->id]  ??= WorkspaceApps::for($workspace)->has('crm');
            $people[$workspace->id] ??= array_column($this->assignments->assignees($workspace), 'id');

            if (! $assignee || ! $crmOn[$workspace->id] || ! in_array($assignee->id, $people[$workspace->id], true)) {
                continue;
            }

            $sent += $this->notifier->send($workspace, [$assignee], new Notice(
                trigger: 'activities.reminder',
                key: "activities.reminder:{$activity->id}:".$activity->remind_at->getTimestamp(),
                title: "Reminder: {$activity->label()}, due {$this->when($workspace, $activity)}",
                body: $activity->regarding() ? 'For '.$activity->regarding()->name : null,
                link: $this->link($workspace, $assignee, $activity),
                subject: $activity,
                email: true,
            ));
        }

        return $sent;
    }

    /** When it is due, in the business's time zone and formats: "16 Nov 2026, 17:00". */
    private function when(Workspace $workspace, CrmActivity $activity): string
    {
        $due = $activity->due_at->copy()->setTimezone($this->calendar->timezone($workspace));

        return $due->format($workspace->date_format ?: 'd M Y').', '.$due->format($workspace->time_format === '12h' ? 'g:i A' : 'H:i');
    }

    /** Its record's page, when $user sees the record; else the Activities page, where their own are. */
    private function link(Workspace $workspace, User $user, CrmActivity $activity): string
    {
        $paths = ['lead' => '/crm/leads/', 'opportunity' => '/crm/opportunities/', 'contact' => '/contacts/'];
        $kind  = $activity->regardingKind();

        return $kind && $this->visibility->seesRecord($workspace, $user, $activity) ? $paths[$kind].$activity->regarding()->id : '/crm/activities';
    }
}
