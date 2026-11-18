<?php

namespace App\Services\Admin;

use App\Mail\SubscriptionNoticeMail;
use App\Models\Billing\Package;
use App\Models\Billing\PackageVersion;
use App\Models\Billing\WorkspaceSubscription;
use App\Models\User\User;
use App\Repositories\Admin\CurrencyRepository;
use App\Repositories\Billing\PackageRepository;
use App\Repositories\Billing\WorkspaceSubscriptionRepository;
use App\Services\Billing\PackagePricing;
use App\Services\Billing\PlanVersions;
use App\Services\Billing\SubscriptionBilling;
use App\Services\Shared\ActivityLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Billing Starter, Standard and Premium. Their keys and ranks are fixed (the
 * code relies on them); names, descriptions, storage limits and whether they
 * are offered can change.
 */
class PackageService
{
    /** Days a subscriber is told before a new price reaches them (Doc 15 Phase C3a). */
    public const NOTICE_DAYS = 30;

    public function __construct(
        protected PackageRepository $packageRepository,
        protected CurrencyRepository $currencyRepository,
        protected PackagePricing $pricing,
        protected WorkspaceSubscriptionRepository $subscriptions,
        protected SubscriptionBilling $billing,
        protected CriticalActionService $critical,
        protected PlanVersions $versions,
    ) {}

    /**
     * The plan form: the plan, and the currencies its prices may be in - those
     * the admin keeps active (Doc 13 section 16). Its prices, past, present
     * and to come, and how many subscribers pay each - to apply a new one to
     * them (Doc 15 Phase C3a).
     *
     * Its versions, with the businesses on each (Phase E3b).
     *
     * @return array{package: Package, currencies: Collection, prices: Collection, paid: Collection, versions: \Illuminate\Support\Collection}
     */
    public function editPage(int $id): array
    {
        $package = $this->packageRepository->findOrFail($id);

        return [
            'package'    => $package,
            'currencies' => $this->currencyRepository->activeByName(),
            'prices'     => $this->pricing->history($package),
            'paid'       => $this->subscriptions->pricesPaid($package),
            'versions'   => $this->versions->history($package),
        ];
    }

    /** $amount as the plan's $cycle price in $currency from $startsOn (Doc 15 Phase C3a). */
    public function addPrice(int $id, User $admin, string $currency, string $cycle, string $amount, string $startsOn): string
    {
        $package = $this->packageRepository->findOrFail($id);
        $price   = $this->pricing->add($package, $currency, $cycle, $amount, Carbon::parse($startsOn), $admin);
        $what    = "{$currency} {$price->amount} a ".self::per($cycle);

        ActivityLogService::record('created', "Set plan \"{$package->name}\"'s {$cycle} price at {$what} from {$price->starts_on->format('j M Y')}", $package, null, $admin);

        return "{$package->name} costs {$what} from {$price->starts_on->format('j M Y')} for new subscribers. Subscribers keep their price until you apply it to them.";
    }

    /** A price not started yet taken away; the one before carries on. */
    public function removePrice(int $id, int $priceId, User $admin): string
    {
        $package = $this->packageRepository->findOrFail($id);
        $price   = $this->pricing->find($package, $priceId) ?? abort(404);

        $this->pricing->remove($price);

        ActivityLogService::record('deleted', "Removed plan \"{$package->name}\"'s {$price->cycle} price of {$price->currency} {$price->amount}, due to start on {$price->starts_on->format('j M Y')}", $package, null, $admin);

        return 'The price is removed; the one before it carries on.';
    }

    /**
     * The plan's $cycle price in $currency in force on $from - at least 30
     * days ahead - applied to every subscriber renewing on it at another
     * price: from their first renewal on or after $from. Each is told now. A
     * change to many businesses' billing, so a critical action (section
     * 15.25): the admin's password and a reason.
     */
    public function applyPrice(int $id, User $admin, string $currency, string $cycle, string $from, ?string $password, ?string $reason): string
    {
        $package = $this->packageRepository->findOrFail($id);
        $day     = Carbon::parse($from)->startOfDay();

        if ($day->lt(Carbon::today()->addDays(self::NOTICE_DAYS))) {
            throw ValidationException::withMessages(['from' => 'Give subscribers at least '.self::NOTICE_DAYS.' days: from '.Carbon::today()->addDays(self::NOTICE_DAYS)->format('j M Y').' or later.']);
        }

        $price   = $this->pricing->inForce($package, $currency, $cycle, $day)
            ?? throw ValidationException::withMessages(['from' => "{$package->name} has no {$cycle} price in {$currency} on that day."]);
        $amount  = (string) $price->amount;

        $this->critical->confirm($admin, $password, $reason, "Apply {$package->name}'s {$cycle} price of {$currency} {$amount} to its subscribers");

        $moved   = $this->subscriptions->payingOther($package, $currency, $cycle, $amount);

        foreach ($moved as $subscription) {
            $this->subscriptions->save($subscription->workspace, ['next_price' => $amount, 'next_price_from' => $day->toDateString()]);

            $this->billing->notice($subscription->workspace, SubscriptionNoticeMail::PRICE_CHANGE, [
                'plan' => $package->name, 'per' => self::per($cycle), 'from' => $day->format('j M Y'),
                'was'  => "{$currency} ".number_format((float) $subscription->price, 2), 'now' => "{$currency} ".number_format((float) $amount, 2),
            ]);
        }

        ActivityLogService::record('updated', "Applied plan \"{$package->name}\"'s {$cycle} price of {$currency} {$amount} to ".count($moved).' '.Str::plural('subscriber', count($moved)).", from {$day->format('j M Y')}", $package, ['subscribers' => count($moved)], $admin);

        return trans_choice(':count subscriber pays|:count subscribers pay', count($moved)).' '."{$currency} {$amount} a ".self::per($cycle)." from their first renewal on or after {$day->format('j M Y')}, and each is told now.";
    }

    private static function per(string $cycle): string
    {
        return $cycle === WorkspaceSubscription::YEARLY ? 'year' : 'month';
    }

    /** Every plan, lowest first, with how many apps and businesses it has. */
    public function listWithCounts(): Collection
    {
        return $this->packageRepository->allByRankWithCounts();
    }

    /** Every plan, lowest first. */
    public function all(): Collection
    {
        return $this->packageRepository->allByRank();
    }

    public function findOrFail(int $id): Package
    {
        return $this->packageRepository->findOrFail($id);
    }

    /**
     * None chosen is every country. Billing Starter, the plan every business
     * starts on and falls back to, is offered everywhere and has no cycles to
     * pay on (Doc 15 Phase E3a).
     *
     * A change to the limits, branding, levels, trial or grace is the plan's
     * next version, from effective_on (Phase E3b): started now when that is
     * today - subscribers stay on the version they are on - else waiting for
     * its day. The rest of the form counts at once. @return string what happened
     */
    public function update(Package $package, array $data, User $admin): string
    {
        $data['countries'] = $package->isFree() || empty($data['countries']) ? null : array_values(array_unique($data['countries']));

        if ($package->isFree()) {
            unset($data['offers_monthly'], $data['offers_yearly']);
        }

        $from    = Carbon::parse($data['effective_on'] ?? 'today')->startOfDay();
        $terms   = array_intersect_key($data, array_flip(PackageVersion::TERMS));
        $fields  = array_diff_key($data, array_flip([...PackageVersion::TERMS, 'effective_on']));

        [$version, $held] = DB::transaction(function () use ($package, $terms, $fields, $from, $admin) {
            $version = PackageVersion::differ($terms, $package) ? $this->versions->add($package, $terms, $from, $admin) : null;

            if ($version && $from->lte(Carbon::today())) {
                return [$version, $this->versions->start($version, $package, $fields)];
            }

            $this->packageRepository->update($package, $fields);

            return [$version, 0];
        });

        $started = $version?->applied_at !== null;
        $what    = match (true) {
            $version === null => '',
            $started          => "; its version {$version->number} started",
            default           => "; its version {$version->number} starts on {$version->starts_on->format('j M Y')}",
        };

        ActivityLogService::record('updated', "Updated plan \"{$package->name}\"{$what}", $package, $version ? ['version' => $version->number] : null);

        return match (true) {
            $version === null => 'Plan updated successfully.',
            $started          => "Plan updated: version {$version->number} has started. ".trans_choice(':count subscriber stays|:count subscribers stay', $held).' on the version before.',
            default           => "Plan updated: version {$version->number} starts on {$version->starts_on->format('j M Y')}. Until then the plan's terms stay as they are.",
        };
    }

    /** A version waiting for its day, removed (Doc 15 Phase E3b). */
    public function removeVersion(int $id, int $versionId, User $admin): string
    {
        $package = $this->packageRepository->findOrFail($id);
        $version = $this->versions->remove($package, $versionId);

        ActivityLogService::record('deleted', "Removed plan \"{$package->name}\"'s version {$version->number}, due to start on {$version->starts_on->format('j M Y')}", $package, null, $admin);

        return "Version {$version->number} is removed: the plan's terms stay as they are.";
    }
}
