<?php

namespace App\Repositories\Admin;

use App\Models\Billing\SubscriptionInvoice;
use App\Models\Billing\SubscriptionPayment;
use App\Models\Billing\SubscriptionRefund;
use App\Models\Billing\WorkspaceSubscription;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceApp;
use App\Models\User\WorkspaceFile;
use App\Models\User\WorkspaceMember;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

/**
 * Admin > Overview's figures (Doc 15 section 15.4 - Phase D1), over the
 * businesses $filters pick - package (key), country, subscription and app,
 * as Admin > Businesses filters them: how many there are now, new businesses
 * and accounts and active users between two days, the apps most activated,
 * storage, izSuite's own billing, and the background jobs. Only sums and
 * counts: never one business's own transactions. Days are of the times as
 * stored (UTC).
 */
class OverviewRepository
{
    public function __construct(
        private BusinessRepository $businesses,
        private SubscriptionAdminRepository $subscriptions,
    ) {}

    /** @return array{all: int, by_status: array<string, int>, by_plan: array<int, int>, trialing: int, past_due: int, grace: int} the businesses as they are now: by status, by plan (its ID) and by subscription */
    public function businessCounts(array $filters): array
    {
        $subscribed = fn (string $status) => $this->matching($filters)
            ->whereHas('subscription', fn (Builder $subscription) => $subscription->where('status', $status))
            ->count();

        return [
            'all'       => $this->matching($filters)->count(),
            'by_status' => $this->countsBy($this->matching($filters), 'workspaces.status'),
            'by_plan'   => $this->countsBy($this->matching($filters), 'workspaces.package_id'),
            'trialing'  => $subscribed(WorkspaceSubscription::TRIALING),
            'grace'     => $subscribed(WorkspaceSubscription::GRACE),
            // Past Due as Admin > Subscriptions has it: running, its renewal's card refused.
            'past_due'  => $this->matching($filters)
                ->whereHas('subscription', fn (Builder $subscription) => $subscription
                    ->where('workspace_subscriptions.status', WorkspaceSubscription::ACTIVE)
                    ->where('cancels_at_period_end', false)
                    ->whereExists(fn (QueryBuilder $due) => $this->subscriptions->pastDue($due)))
                ->count(),
        ];
    }

    public function newBusinesses(array $filters, CarbonInterface $from, CarbonInterface $to): int
    {
        return $this->matching($filters)->whereBetween('workspaces.created_at', $this->between($from, $to))->count();
    }

    /** @return array<string, int> new businesses by day ("2026-11-14") - or by month ("2026-11") */
    public function newBusinessesBy(array $filters, CarbonInterface $from, CarbonInterface $to, bool $byMonth): array
    {
        $period = $byMonth ? $this->month('workspaces.created_at') : 'date(workspaces.created_at)';

        return $this->matching($filters)
            ->whereBetween('workspaces.created_at', $this->between($from, $to))
            ->toBase()
            ->selectRaw("{$period} as period, count(*) as total")
            ->groupByRaw($period)
            ->pluck('total', 'period')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /** Website accounts made between the days. */
    public function newAccounts(array $filters, CarbonInterface $from, CarbonInterface $to): int
    {
        return $this->accounts($filters)->whereBetween('users.created_at', $this->between($from, $to))->count();
    }

    /** Website accounts that signed in, used the app or did anything a business's Activity Log records between the days. */
    public function activeUsers(array $filters, CarbonInterface $from, CarbonInterface $to): int
    {
        $between = $this->between($from, $to);

        return $this->accounts($filters)
            ->where(fn (Builder $query) => $query
                ->whereBetween('users.last_login_at', $between)
                ->orWhereHas('tokens', fn (Builder $token) => $token->whereBetween('last_used_at', $between))
                ->orWhereExists(fn (QueryBuilder $done) => $done->selectRaw('1')->from('activity_logs')
                    ->whereColumn('activity_logs.user_id', 'users.id')
                    ->whereBetween('activity_logs.created_at', $between)))
            ->count();
    }

    /** @return Collection<int, object{name: string, businesses: int}> the apps most businesses have active, most first */
    public function topApps(array $filters, int $limit): Collection
    {
        return $this->ofBusinesses(WorkspaceApp::query(), $filters, 'workspace_apps.workspace_id')
            ->join('apps', 'apps.id', '=', 'workspace_apps.app_id')
            ->where('workspace_apps.status', WorkspaceApp::STATUS_ACTIVE)
            ->toBase()
            ->selectRaw('apps.id, apps.name, count(*) as businesses')
            ->groupBy('apps.id', 'apps.name')
            ->orderByDesc('businesses')
            ->orderBy('apps.name')
            ->limit($limit)
            ->get();
    }

    /** @return array{bytes: int, near_limit: int} what the businesses' files take, and how many businesses use $warnPercent of their plan's storage or more */
    public function storage(array $filters, int $warnPercent): array
    {
        $used = WorkspaceFile::query()->selectRaw('coalesce(sum(size), 0) * 100')->whereColumn('workspace_files.workspace_id', 'workspaces.id');

        return [
            'bytes'      => (int) $this->ofBusinesses(WorkspaceFile::query(), $filters, 'workspace_files.workspace_id')->sum('size'),
            'near_limit' => $this->matching($filters)
                ->join('packages', 'packages.id', '=', 'workspaces.package_id')
                ->whereNotNull('packages.storage_limit_mb')
                ->where($used, '>=', DB::raw('packages.storage_limit_mb * 1048576 * '.$warnPercent))
                ->count(),
        ];
    }

    /** @return Collection<int, object{currency: string, invoices: int, collected: string, tax: string}> invoices paid between the days, by currency: what they came to, tax included */
    public function collected(array $filters, CarbonInterface $from, CarbonInterface $to, ?string $currency): Collection
    {
        return $this->ofBusinesses(SubscriptionInvoice::query(), $filters, 'subscription_invoices.workspace_id')
            ->where('status', SubscriptionInvoice::PAID)
            ->whereBetween('paid_at', $this->between($from, $to))
            ->when($currency, fn (Builder $query) => $query->where('currency', $currency))
            ->toBase()
            ->selectRaw('currency, count(*) as invoices, sum(total) as collected, sum(tax) as tax')
            ->groupBy('currency')
            ->get();
    }

    /** @return array<string, string> refunds confirmed between the days, by currency */
    public function refunded(array $filters, CarbonInterface $from, CarbonInterface $to, ?string $currency): array
    {
        return $this->ofBusinesses(SubscriptionRefund::query(), $filters, 'subscription_refunds.workspace_id')
            ->where('status', SubscriptionRefund::SUCCEEDED)
            ->whereBetween('confirmed_at', $this->between($from, $to))
            ->when($currency, fn (Builder $query) => $query->where('currency', $currency))
            ->toBase()
            ->selectRaw('currency, sum(amount) as refunded')
            ->groupBy('currency')
            ->pluck('refunded', 'currency')
            ->all();
    }

    /** @return Collection<int, object{currency: string, tries: int, businesses: int, amount: string}> card tries that did not go through between the days, by currency */
    public function failedPayments(array $filters, CarbonInterface $from, CarbonInterface $to, ?string $currency): Collection
    {
        return $this->ofBusinesses(SubscriptionPayment::query(), $filters, 'subscription_payments.workspace_id')
            ->whereIn('status', [SubscriptionPayment::FAILED, SubscriptionPayment::ACTION_REQUIRED])
            ->whereBetween('created_at', $this->between($from, $to))
            ->when($currency, fn (Builder $query) => $query->where('currency', $currency))
            ->toBase()
            ->selectRaw('currency, count(*) as tries, count(distinct workspace_id) as businesses, sum(amount) as amount')
            ->groupBy('currency')
            ->get();
    }

    /** @return array{failed: int, kept: int, waiting: ?int} jobs that failed between the days, failed ones kept in all, and jobs waiting on the queue now - null when it cannot be reached */
    public function jobs(CarbonInterface $from, CarbonInterface $to): array
    {
        try {
            $waiting = Queue::size();
        } catch (\Throwable $e) {
            report($e);
            $waiting = null;
        }

        return [
            'failed'  => DB::table('failed_jobs')->whereBetween('failed_at', $this->between($from, $to))->count(),
            'kept'    => DB::table('failed_jobs')->count(),
            'waiting' => $waiting,
        ];
    }

    /** The businesses $filters pick. */
    private function matching(array $filters): Builder
    {
        return $this->businesses->narrow(Workspace::query(), $filters);
    }

    /** $query's rows of the businesses $filters pick - all of them when the filters pick none out. */
    private function ofBusinesses(Builder $query, array $filters, string $column): Builder
    {
        return $this->narrows($filters) ? $query->whereIn($column, $this->matching($filters)->select('workspaces.id')) : $query;
    }

    /** Website accounts: the owners and users of the businesses $filters pick, when they pick any out. */
    private function accounts(array $filters): Builder
    {
        $accounts = User::query()->whereHas('role', fn (Builder $role) => $role->where('slug', ClientRepository::ROLE));

        if (! $this->narrows($filters)) {
            return $accounts;
        }

        return $accounts->where(fn (Builder $query) => $query
            ->whereIn('users.id', WorkspaceMember::query()->select('user_id')->whereIn('status', WorkspaceMember::COUNTED)->whereIn('workspace_id', $this->matching($filters)->select('workspaces.id')))
            ->orWhereIn('users.id', $this->matching($filters)->select('workspaces.user_id')));
    }

    private function narrows(array $filters): bool
    {
        return array_filter(array_intersect_key($filters, array_flip(['status', 'package', 'country', 'subscription', 'app']))) !== [];
    }

    /** @return array<string, int> $query's rows counted by $column */
    private function countsBy(Builder $query, string $column): array
    {
        return $query->toBase()
            ->selectRaw("{$column} as value, count(*) as total")
            ->groupBy($column)
            ->pluck('total', 'value')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /** @return array{0: CarbonInterface, 1: CarbonInterface} from the first day's start to the last day's end */
    private function between(CarbonInterface $from, CarbonInterface $to): array
    {
        return [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
    }

    /** "2026-11" of $column, in this database's own words. */
    private function month(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m', {$column})" : "DATE_FORMAT({$column}, '%Y-%m')";
    }
}
