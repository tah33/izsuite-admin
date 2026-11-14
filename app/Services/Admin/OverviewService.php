<?php

namespace App\Services\Admin;

use App\Models\User\User;
use App\Repositories\Admin\AppRepository;
use App\Repositories\Admin\BusinessRepository;
use App\Repositories\Admin\CurrencyRepository;
use App\Repositories\Admin\OverviewRepository;
use App\Repositories\Billing\PackageRepository;
use App\Services\Shared\ActivityLogService;
use App\Support\BusinessStatus;
use App\Support\Countries;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;

/**
 * Admin > Overview (Doc 15 section 15.4 - Phase D1): izSuite's businesses as
 * they are now - by status, plan and subscription - and, between two days,
 * new businesses and accounts, active users, izSuite's own billing and the
 * background jobs; with the apps most activated and the storage used. The
 * filters pick the businesses (plan, country, subscription, app) and the
 * billing's currency. Billing shows only to an admin who may open Billing
 * Reports; the platform's alerts (Phase D3a) to one who may open Service
 * Health.
 */
class OverviewService
{
    /** Days the Overview covers when none are chosen: the last 30, today included. */
    public const DEFAULT_DAYS = 30;

    /** Longest stretch shown day by day; longer ones go by month. */
    public const DAYS_BY_DAY = 92;

    /** How many of the most activated apps it lists. */
    public const TOP_APPS = 5;

    public function __construct(
        protected OverviewRepository $overview,
        protected BusinessRepository $businesses,
        protected PackageRepository $packages,
        protected AppRepository $apps,
        protected CurrencyRepository $currencies,
        protected ServiceHealthService $health,
        protected IncidentService $incidents,
    ) {}

    /** Everything the page shows for $filters (from, to, package, country, subscription, app, currency). */
    public function dashboard(array $filters, User $admin): array
    {
        $to       = CarbonImmutable::parse($filters['to'] ?? today());
        $from     = isset($filters['from']) ? CarbonImmutable::parse($filters['from']) : $to->subDays(self::DEFAULT_DAYS - 1);
        $chosen   = Arr::only($filters, ['package', 'country', 'subscription', 'app']);
        $currency = $filters['currency'] ?? null;
        $plans    = $this->packages->allByRank();
        $counts   = $this->overview->businessCounts($chosen);

        return [
            'filters'    => [...$filters, 'from' => $from->toDateString(), 'to' => $to->toDateString()],
            'options'    => $this->options($plans),
            'businesses' => [
                'all'       => $counts['all'],
                'active'    => $counts['by_status'][BusinessStatus::ACTIVE] ?? 0,
                'suspended' => $counts['by_status'][BusinessStatus::SUSPENDED] ?? 0,
                'trialing'  => $counts['trialing'],
                'past_due'  => $counts['past_due'],
                'grace'     => $counts['grace'],
                // Every plan, lowest first, with how many businesses are on it.
                'plans'     => $plans->map(fn ($plan) => ['name' => $plan->name, 'count' => $counts['by_plan'][$plan->id] ?? 0])->all(),
            ],
            'period'     => [
                'new_businesses' => $this->overview->newBusinesses($chosen, $from, $to),
                'new_accounts'   => $this->overview->newAccounts($chosen, $from, $to),
                'active_users'   => $this->overview->activeUsers($chosen, $from, $to),
                'chart'          => $this->chart($chosen, $from, $to),
            ],
            'apps'       => $this->overview->topApps($chosen, self::TOP_APPS),
            'storage'    => [...$this->overview->storage($chosen, (int) config('files.warning_percent', 80)), 'warn_percent' => (int) config('files.warning_percent', 80)],
            'billing'    => $admin->role?->hasPermission('admin.billing-reports.index') ? $this->billing($chosen, $from, $to, $currency) : null,
            'jobs'       => $this->overview->jobs($from, $to),
            // The platform's alerts (Phase D3a): services warning or failing, to admins who may open Service Health.
            'alerts'     => $admin->role?->hasPermission('admin.service-health.index') ? $this->health->alerts() : null,
            // And the incidents not resolved yet (Phase D3b).
            'incidents'  => $admin->role?->hasPermission('admin.incidents.index') ? $this->incidents->unresolved() : null,
        ];
    }

    /**
     * Clears the application's caches: what went wrong, when something did.
     */
    public function clearCache(): ?string
    {
        try {
            Artisan::call('optimize:clear');
        } catch (\Exception $e) {
            app(AdminAuditLogService::class)->record('cleared', 'Tried to clear the application cache', null, ['error' => $e->getMessage()], null, 'failed');

            return 'Failed to clear cache: '.$e->getMessage();
        }

        ActivityLogService::record('cleared', 'Cleared the application cache');

        return null;
    }

    /** What the filters offer. */
    private function options(Collection $plans): array
    {
        return [
            'plans'         => $plans->pluck('name', 'key')->all(),
            'countries'     => collect($this->businesses->countries())->mapWithKeys(fn (string $code) => [$code => Countries::name($code) ?? $code])->all(),
            'subscriptions' => BusinessService::SUBSCRIPTION_LABELS,
            'apps'          => $this->apps->allInCatalogOrder()->pluck('name', 'id')->all(),
            'currencies'    => $this->currencies->activeByName()->pluck('code')->all(),
        ];
    }

    /**
     * New businesses for each day from $from to $to - or each month, over
     * DAYS_BY_DAY days - none missed.
     *
     * @return array{by_month: bool, labels: list<string>, values: list<int>}
     */
    private function chart(array $chosen, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $byMonth = $from->diffInDays($to) >= self::DAYS_BY_DAY;
        $counts  = $this->overview->newBusinessesBy($chosen, $from, $to, $byMonth);
        $labels  = [];
        $values  = [];

        for ($day = $byMonth ? $from->startOfMonth() : $from; $day->lte($to); $day = $byMonth ? $day->addMonth() : $day->addDay()) {
            $labels[] = $byMonth ? $day->format('M Y') : $day->format('M j');
            $values[] = $counts[$day->format($byMonth ? 'Y-m' : 'Y-m-d')] ?? 0;
        }

        return ['by_month' => $byMonth, 'labels' => $labels, 'values' => $values];
    }

    /**
     * izSuite's billing from $from to $to, by currency - in $currency alone
     * when given: invoices paid and what they came to (tax included), refunds,
     * what is left, and card payments that did not go through.
     *
     * @return list<array{currency: string, invoices: int, collected: string, tax: string, refunded: string, net: string, failed_tries: int, failed_businesses: int, failed_amount: string}>
     */
    private function billing(array $chosen, CarbonInterface $from, CarbonInterface $to, ?string $currency): array
    {
        $collected = $this->overview->collected($chosen, $from, $to, $currency)->keyBy('currency');
        $refunded  = $this->overview->refunded($chosen, $from, $to, $currency);
        $failed    = $this->overview->failedPayments($chosen, $from, $to, $currency)->keyBy('currency');
        $money     = fn ($amount) => bcadd((string) ($amount ?? '0'), '0', 2);
        $codes     = collect([...$collected->keys(), ...array_keys($refunded), ...$failed->keys()])->unique()->sort()->values();

        return $codes->map(fn (string $code) => [
            'currency'          => $code,
            'invoices'          => (int) ($collected[$code]->invoices ?? 0),
            'collected'         => $money($collected[$code]->collected ?? null),
            'tax'               => $money($collected[$code]->tax ?? null),
            'refunded'          => $money($refunded[$code] ?? null),
            'net'               => bcsub($money($collected[$code]->collected ?? null), $money($refunded[$code] ?? null), 2),
            'failed_tries'      => (int) ($failed[$code]->tries ?? 0),
            'failed_businesses' => (int) ($failed[$code]->businesses ?? 0),
            'failed_amount'     => $money($failed[$code]->amount ?? null),
        ])->all();
    }
}
