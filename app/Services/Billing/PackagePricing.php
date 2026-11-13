<?php

namespace App\Services\Billing;

use App\Models\Billing\Package;
use App\Models\Billing\PackagePrice;
use App\Models\Billing\WorkspaceSubscription;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\Billing\PackagePriceRepository;
use App\Services\Shared\BusinessCalendar;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * What izSuite's plans cost (Doc 15 sections 15.8 and 15.9 - Phase C3a): a
 * price per currency and cycle, each from its start day until the next
 * starts, kept as history. A business pays in its own base currency when the
 * plan has a price in it, else in the plan's main currency - which leaves
 * the business's currency as it is.
 *
 * A subscription keeps the price it pays: a renewal on the same plan and
 * cycle charges it, until izSuite applies a new one from a day it has told
 * the business of. A move to another plan takes that plan's price then.
 */
class PackagePricing
{
    public function __construct(
        private PackagePriceRepository $prices,
        private BusinessCalendar $calendar,
    ) {}

    /** The currency $workspace pays $package in on $day: its own, when the plan has a price in it then; else the plan's main one. */
    public function currencyFor(Package $package, ?Workspace $workspace, ?CarbonInterface $day = null): string
    {
        $main = (string) ($package->price_currency ?: 'USD');
        $own  = $workspace?->base_currency;

        return $own && $own !== $main && $this->prices->hasCurrency($package, $own, $this->day($workspace, $day)) ? $own : $main;
    }

    /**
     * What $workspace pays for $package's $cycle on $day - today when not
     * given: the price in force in its currency. Null while none is
     * announced.
     *
     * @return array{currency: string, amount: string}|null
     */
    public function price(Package $package, string $cycle, ?Workspace $workspace = null, ?CarbonInterface $day = null): ?array
    {
        $currency = $this->currencyFor($package, $workspace, $day);
        $price    = $this->prices->inForce($package, $currency, $cycle, $this->day($workspace, $day));

        return $price ? ['currency' => $currency, 'amount' => bcadd((string) $price->amount, '0', 2)] : null;
    }

    /**
     * What a renewal of $subscription for the period from $start charges, on
     * $package's $cycle: on its own plan and cycle, the price it pays - or
     * the new one izSuite applied, once its day has come; on another (a move
     * down), that plan's price now. The second item says whether the new
     * one was used, for the subscription to keep it from then.
     *
     * @return array{0: array{currency: string, amount: string}|null, 1: bool}
     */
    public function renewalPrice(WorkspaceSubscription $subscription, Package $package, string $cycle, CarbonInterface $start): array
    {
        $own = $package->id === $subscription->package_id && $cycle === $subscription->cycle;

        if (! $own || $subscription->price === null || ! $subscription->currency) {
            return [$this->price($package, $cycle, $subscription->workspace), false];
        }

        $next = $subscription->next_price !== null && $subscription->next_price_from !== null && $subscription->next_price_from->lte($start);

        return [['currency' => $subscription->currency, 'amount' => bcadd((string) ($next ? $subscription->next_price : $subscription->price), '0', 2)], $next];
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, PackagePrice> every price of $package: its history, by currency and cycle */
    public function history(Package $package)
    {
        return $this->prices->history($package);
    }

    public function find(Package $package, int $id): ?PackagePrice
    {
        return $this->prices->find($package, $id);
    }

    /** The price of $package in $currency for $cycle in force on $day. */
    public function inForce(Package $package, string $currency, string $cycle, CarbonInterface $day): ?PackagePrice
    {
        return $this->prices->inForce($package, $currency, $cycle, $day->toDateString());
    }

    /**
     * $amount as $package's $cycle price in $currency from $startsOn - today
     * or later - the price before it ending the day before. Refused, on the
     * starts_on field, when one of that currency and cycle starts on or
     * after that day already.
     */
    public function add(Package $package, string $currency, string $cycle, string $amount, CarbonInterface $startsOn, User $admin): PackagePrice
    {
        $day = $startsOn->toDateString();

        if ($startsOn->lt(Carbon::today())) {
            throw ValidationException::withMessages(['starts_on' => 'A new price starts today or later: the prices before stay as they were.']);
        }

        if ($this->prices->startingFrom($package, $currency, $cycle, $day)) {
            throw ValidationException::withMessages(['starts_on' => "A {$cycle} price in {$currency} starts on or after that day already: remove it first."]);
        }

        return DB::transaction(function () use ($package, $currency, $cycle, $amount, $startsOn, $day, $admin) {
            if ($open = $this->prices->open($package, $currency, $cycle)) {
                $this->prices->update($open, ['ends_on' => $startsOn->copy()->subDay()->toDateString()]);
            }

            return $this->prices->create([
                'package_id' => $package->id, 'currency' => $currency, 'cycle' => $cycle, 'amount' => bcadd($amount, '0', 2),
                'starts_on'  => $day, 'created_by' => $admin->id,
            ]);
        });
    }

    /** A price not started yet taken away: the one before it carries on. One in force or past is history and stays. */
    public function remove(PackagePrice $price): void
    {
        if ($price->starts_on->lte(Carbon::today())) {
            throw ValidationException::withMessages(['price' => 'A price in force or past is history: it stays. Add a new one to change it.']);
        }

        DB::transaction(function () use ($price) {
            if ($before = $this->prices->endingOn($price->package, $price->currency, $price->cycle, $price->starts_on->copy()->subDay()->toDateString())) {
                $this->prices->update($before, ['ends_on' => null]);
            }

            $this->prices->delete($price);
        });
    }

    /** $day as "2026-11-09": the business's today when none is given. */
    private function day(?Workspace $workspace, ?CarbonInterface $day): string
    {
        return ($day ?? ($workspace ? $this->calendar->today($workspace) : Carbon::today()))->toDateString();
    }
}
