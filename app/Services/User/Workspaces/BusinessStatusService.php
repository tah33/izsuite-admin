<?php

namespace App\Services\User\Workspaces;

use App\Mail\BusinessNoticeMail;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceStatusChange;
use App\Repositories\User\WorkspaceRepository;
use App\Repositories\User\WorkspaceStatusChangeRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\Notifications\Notice;
use App\Services\Shared\Notifications\Notifier;
use App\Support\BusinessStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * A business's status (Doc 15 section 15.6), apart from its subscription's:
 * changed in the admin panel with a reason, and what it means everywhere
 * else.
 *
 * - The API refuses what the status forbids (refusal(), from
 *   ResolveWorkspace): nothing for a suspended or closed business but its
 *   navigation, and no changes to a restricted one or one under review.
 * - The app hears it with the navigation (forNavigation()) and shows a
 *   banner, or the reason it cannot be opened. Only the owner sees the
 *   reason and what reactivates it.
 * - A scheduled closure closes on its day (closeDue(), hourly).
 */
class BusinessStatusService
{
    /** Open whatever the status: each member's own notifications, and the support cases that reach izSuite. */
    private const ALWAYS_OPEN = ['api.notifications.*', 'api.support-cases.*'];

    public function __construct(
        protected WorkspaceRepository $workspaces,
        protected WorkspaceStatusChangeRepository $changes,
        protected Notifier $notifier,
    ) {}

    /**
     * Sets $workspace's status, by $admin. $data: reason (for the owner),
     * reactivation, notes (internal), closes_on (a scheduled closure's day)
     * and notify (tell the owner).
     */
    public function change(Workspace $workspace, string $status, array $data, User $admin): WorkspaceStatusChange
    {
        if (! in_array($status, BusinessStatus::SETTABLE, true)) {
            throw ValidationException::withMessages(['status' => 'Choose a status the panel can set.']);
        }

        $closesOn = $status === BusinessStatus::CLOSURE_SCHEDULED ? Carbon::parse($data['closes_on'] ?? '') : null;

        if ($status === BusinessStatus::CLOSURE_SCHEDULED && $closesOn->lt(today())) {
            throw ValidationException::withMessages(['closes_on' => 'Choose today or a later day for the closure.']);
        }

        $previous = $workspace->status ?? BusinessStatus::ACTIVE;

        $change = DB::transaction(function () use ($workspace, $status, $data, $admin, $previous, $closesOn) {
            $this->workspaces->forceUpdate($workspace, ['status' => $status]);

            return $this->changes->create([
                'workspace_id'    => $workspace->id,
                'status'          => $status,
                'previous_status' => $previous,
                'reason'          => trim($data['reason']),
                'reactivation'    => ($data['reactivation'] ?? null) ?: null,
                'notes'           => ($data['notes'] ?? null) ?: null,
                'effective_on'    => today(),
                'closes_on'       => $closesOn,
                'user_id'         => $admin->id,
                'admin_name'      => $admin->name,
            ]);
        });

        ActivityLogService::record('updated', 'Set business "'.$workspace->name.'" to '.BusinessStatus::label($status), $workspace, [
            'changes' => [['setting' => 'status', 'from' => BusinessStatus::label($previous), 'to' => BusinessStatus::label($status)]],
            'reason'  => $change->reason,
            ...($closesOn ? ['closes_on' => $closesOn->toDateString()] : []),
        ], $admin);

        if (! empty($data['notify'])) {
            $this->tellOwner($workspace, $change);
            $this->changes->markOwnerNotified($change);
        }

        return $change;
    }

    /**
     * Closes each business whose scheduled closing day has come, for the
     * reason it was scheduled; its owner is told in the app and by e-mail.
     */
    public function closeDue(): int
    {
        $due = $this->changes->dueClosures(today()->toDateString());

        foreach ($due as $workspace) {
            $scheduled = $this->changes->latestFor($workspace);

            $change = DB::transaction(function () use ($workspace, $scheduled) {
                $this->workspaces->forceUpdate($workspace, ['status' => BusinessStatus::CLOSED]);

                return $this->changes->create([
                    'workspace_id' => $workspace->id, 'status' => BusinessStatus::CLOSED, 'previous_status' => BusinessStatus::CLOSURE_SCHEDULED,
                    'reason'       => $scheduled?->reason ?: 'Closed on its scheduled day.', 'reactivation' => $scheduled?->reactivation,
                    'effective_on' => today(), 'owner_notified' => (bool) $workspace->user,
                ]);
            });

            ActivityLogService::record('updated', 'The business was closed on its scheduled day', $workspace, ['source' => 'system']);

            $this->tellOwner($workspace, $change);
        }

        return $due->count();
    }

    /**
     * What the app shows of the status: nothing while it is Active. The
     * reason and what reactivates it are the owner's alone.
     */
    public function forNavigation(Workspace $workspace, User $user): ?array
    {
        $status = $workspace->status ?? BusinessStatus::ACTIVE;

        if ($status === BusinessStatus::ACTIVE) {
            return null;
        }

        $change = $this->changes->latestFor($workspace);
        $owner  = $workspace->user_id === $user->id;

        return [
            'key'          => $status,
            'label'        => BusinessStatus::label($status),
            'read_only'    => BusinessStatus::isReadOnly($status),
            'shut'         => BusinessStatus::isShut($status),
            'is_owner'     => $owner,
            'reason'       => $owner ? $change?->reason : null,
            'reactivation' => $owner ? $change?->reactivation : null,
            'closes_on'    => $change?->closes_on?->toDateString(),
        ];
    }

    /**
     * What the API answers instead, when the status forbids $request: any
     * request for a suspended or closed business but its navigation, which
     * says why, its members' own notifications, where izSuite's notice of it
     * waits, and its support cases, where they write to izSuite about it;
     * any change to one that is restricted or under review, but marking
     * notifications read and writing to support.
     */
    public function refusal(Request $request, Workspace $workspace): ?JsonResponse
    {
        $status = $workspace->status ?? BusinessStatus::ACTIVE;

        if (BusinessStatus::isShut($status) && ! $request->routeIs('api.navigation', ...self::ALWAYS_OPEN)) {
            return response()->json([
                'message'         => $status === BusinessStatus::CLOSED
                    ? 'This business is closed. Its records are kept, but nobody can open it.'
                    : 'izSuite has suspended this business, so nobody can open it for now. Its owner can see why.',
                'business_status' => $status,
            ], 403);
        }

        if (BusinessStatus::isReadOnly($status) && ! $request->isMethodSafe() && ! $request->routeIs(...self::ALWAYS_OPEN)) {
            return response()->json([
                'message'         => $status === BusinessStatus::UNDER_REVIEW
                    ? 'This business is under review by izSuite: you can view its records, but not change them for now.'
                    : 'izSuite has restricted this business: you can view its records, but not change them until the restriction is lifted.',
                'business_status' => $status,
            ], 403);
        }

        return null;
    }

    /** The owner hears of $change: in the app (the bell works even while the business is shut) and by e-mail. */
    private function tellOwner(Workspace $workspace, WorkspaceStatusChange $change): void
    {
        $owner = $workspace->user;

        if (! $owner) {
            return;
        }

        [$title, $lines] = $this->words($workspace, $change);

        $this->notifier->send($workspace, [$owner], new Notice(
            trigger: 'izsuite.status',
            key: "izsuite.status.{$change->id}",
            title: $title,
            body: implode(' ', $lines),
            link: '/dashboard',
            subject: $workspace,
        ));

        Mail::to($owner->email)->queue(new BusinessNoticeMail($owner->first_name ?: $owner->name, $title, $lines));
    }

    /** @return array{0: string, 1: list<string>} the notice's title, and its paragraphs */
    private function words(Workspace $workspace, WorkspaceStatusChange $change): array
    {
        $name  = $workspace->name;
        $why   = $change->reason ? "Why: {$change->reason}" : null;
        $lifts = $change->reactivation ? "What reactivates it: {$change->reactivation}" : null;

        return match ($change->status) {
            BusinessStatus::RESTRICTED        => ["{$name} is restricted", array_values(array_filter(["izSuite has restricted {$name}: you and your team can view its records, but not change them.", $why, $lifts]))],
            BusinessStatus::UNDER_REVIEW      => ["{$name} is under review", array_values(array_filter(["izSuite is reviewing {$name}: you and your team can view its records, but not change them for now.", $why, $lifts]))],
            BusinessStatus::SUSPENDED         => ["{$name} is suspended", array_values(array_filter(["izSuite has suspended {$name}: nobody can open it for now. Its records are kept.", $why, $lifts]))],
            BusinessStatus::CLOSURE_SCHEDULED => ["{$name} closes on ".$change->closes_on->format('j M Y'), array_values(array_filter(['After that day nobody can open it. Its records are kept.', $why, 'To keep it open, write to izSuite support before then.']))],
            BusinessStatus::CLOSED            => ["{$name} is closed", ['It closed on its scheduled day. Nobody can open it, and its records are kept.']],
            default                           => ["{$name} is active again", array_values(array_filter(['Everyone can use it as before.', $why]))],
        };
    }
}
