<?php

namespace App\Services\Admin;

use App\Mail\BusinessNoticeMail;
use App\Models\Billing\WorkspaceSubscription;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceStatusChange;
use App\Repositories\Admin\BusinessRepository;
use App\Repositories\Billing\PackageRepository;
use App\Repositories\User\SupportSessionRepository;
use App\Repositories\User\WorkspaceStatusChangeRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\Notifications\Notice;
use App\Services\Shared\Notifications\NotificationAudience;
use App\Services\Shared\Notifications\Notifier;
use App\Services\User\Workspaces\BusinessStatusService;
use App\Support\BusinessStatus;
use App\Support\Countries;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Admin > Businesses (Doc 15 section 15.5): every business with its owner,
 * plan, subscription, status and usage; one business's page; changing its
 * status (BusinessStatusService); and a message to it from izSuite.
 */
class BusinessService
{
    /** A subscription as the list names it, by WorkspaceSubscription status or "free" and "cancellation_scheduled". */
    public const SUBSCRIPTION_LABELS = [
        'free'                   => 'Free',
        'trialing'               => 'Trial',
        'awaiting_payment'       => 'Awaiting Payment',
        'active'                 => 'Active',
        'cancellation_scheduled' => 'Cancellation Scheduled',
        'grace'                  => 'Grace Period',
    ];

    public function __construct(
        protected BusinessRepository $businesses,
        protected PackageRepository $packages,
        protected WorkspaceStatusChangeRepository $statusChanges,
        protected BusinessStatusService $statuses,
        protected Notifier $notifier,
        protected NotificationAudience $audience,
        protected SupportSessionRepository $supportSessions,
    ) {}

    public function page(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->businesses->page($filters, $perPage);
    }

    /** What the list's filters offer. */
    public function filterOptions(): array
    {
        return [
            'packages'      => $this->packages->allByRank(),
            'countries'     => collect($this->businesses->countries())->mapWithKeys(fn (string $code) => [$code => Countries::name($code) ?? $code])->all(),
            'statuses'      => BusinessStatus::LABELS,
            'subscriptions' => self::SUBSCRIPTION_LABELS,
        ];
    }

    /** Everything a business's page shows. */
    public function details(int $id): array
    {
        $workspace = $this->businesses->findForPage($id) ?? abort(404);

        return [
            'business'           => $workspace,
            'subscriptionStatus' => self::subscriptionStatus($workspace),
            'statusHistory'      => $this->statusChanges->historyFor($workspace),
            'supportCases'       => $this->businesses->supportCases($workspace),
            // Support sessions (Doc 15 Phase B4): its recent ones, and the cases one can be for.
            'supportSessions'    => $this->supportSessions->recentFor($workspace),
            'openCases'          => $this->supportSessions->casesFor($workspace),
            'activity'           => $this->businesses->adminActivity($workspace),
            'packages'           => $this->packages->allByRank(),
        ];
    }

    /** Sets its status, by $admin (BusinessStatusService::change). */
    public function changeStatus(int $id, array $data, User $admin): WorkspaceStatusChange
    {
        $workspace = $this->businesses->findForPage($id) ?? abort(404);

        return $this->statuses->change($workspace, $data['status'], $data, $admin);
    }

    /**
     * A message from izSuite: in the app to its owner, or to everyone in
     * it, and by e-mail to the owner. Answers how many it reached in the app.
     */
    public function notify(int $id, array $data, User $admin): int
    {
        $workspace = $this->businesses->findForPage($id) ?? abort(404);
        $users     = $data['audience'] === 'everyone' ? $this->audience->everyone($workspace) : collect([$workspace->user])->filter();

        $reached = $this->notifier->send($workspace, $users, new Notice(
            trigger: 'izsuite.message',
            key: 'izsuite.message.'.Str::uuid(),
            title: $data['title'],
            body: $data['message'],
            link: '/dashboard',
            subject: $workspace,
        ));

        if ($workspace->user) {
            Mail::to($workspace->user->email)->queue(new BusinessNoticeMail($workspace->user->first_name ?: $workspace->user->name, $data['title'], [$data['message']]));
        }

        ActivityLogService::record('notified', "Sent \"{$data['title']}\" to business \"{$workspace->name}\"", $workspace, [
            'audience' => $data['audience'],
            'reached'  => $reached,
        ], $admin);

        return $reached;
    }

    /** Its subscription as the list names it: "free" with none running. */
    public static function subscriptionStatus(Workspace $workspace): string
    {
        $subscription = $workspace->subscription;

        if (! $subscription || ! in_array($subscription->status, WorkspaceSubscription::CURRENT, true)) {
            return 'free';
        }

        return $subscription->status === WorkspaceSubscription::ACTIVE && $subscription->cancels_at_period_end ? 'cancellation_scheduled' : $subscription->status;
    }
}
