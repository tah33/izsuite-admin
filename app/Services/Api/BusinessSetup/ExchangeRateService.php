<?php

namespace App\Services\Api\BusinessSetup;

use App\Models\User\ExchangeRate;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\ExchangeRateRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Database\Eloquent\Collection;

/**
 * Settings > Currencies > Exchange Rates (Doc 11 section 7): what each of the
 * business's other currencies is worth in its base currency, entered by hand
 * from a date on. Every rate is kept, so the history shows what was in force
 * when. A document copies the rate of its date and keeps it, so changing or
 * removing one here never touches a document.
 *
 * A currency the business stops using keeps its rates; they show again if it
 * is switched back on.
 */
class ExchangeRateService
{
    public function __construct(
        protected ExchangeRateRepository $exchangeRateRepository,
    ) {}

    /** The rates of the business's other currencies: by currency, newest first. */
    public function list(Workspace $workspace): Collection
    {
        return $this->exchangeRateRepository->getFor($workspace, $workspace->additional_currencies ?? []);
    }

    /**
     * $data is validated: one of the business's other currencies, a rate above
     * zero, and the day it takes effect. A rate for a day that already has one
     * replaces it.
     */
    public function save(Workspace $workspace, User $user, array $data): ExchangeRate
    {
        $rate = $this->exchangeRateRepository->put($workspace, [
            ...$data,
            'base_currency' => $workspace->base_currency,
            'created_by'    => $user->id,
        ]);

        // Logged here, like every save, so LogApiWriteActivity does not write
        // its own vaguer entry.
        ActivityLogService::record(
            $rate->wasRecentlyCreated ? 'created' : 'updated',
            "Set the {$rate->currency} rate to {$this->plain($rate->rate)} {$workspace->base_currency} from {$rate->effective_date}",
            $rate,
            ['source' => 'api'],
        );

        return $rate;
    }

    public function delete(Workspace $workspace, int $id): void
    {
        $rate = $this->exchangeRateRepository->findFor($workspace, $id)
            ?? abort(404, 'Exchange rate not found.');

        $this->exchangeRateRepository->delete($rate);

        ActivityLogService::record('deleted', "Removed the {$rate->currency} rate of {$rate->effective_date}", $rate, ['source' => 'api']);
    }

    /**
     * The rate of $currency in force on $date ("2026-10-28"), as a string - "1"
     * for the base currency, null when none was entered by then.
     */
    public function rateOn(Workspace $workspace, string $currency, string $date): ?string
    {
        if ($currency === $workspace->base_currency) {
            return '1';
        }

        return $this->exchangeRateRepository->rateOn($workspace, $currency, $date)?->rate;
    }

    /** "121.50000000" as a person writes it: "121.5". */
    private function plain(string $rate): string
    {
        return str_contains($rate, '.') ? rtrim(rtrim($rate, '0'), '.') : $rate;
    }
}
