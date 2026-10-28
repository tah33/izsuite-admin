<?php

namespace Tests\Feature;

use App\Models\Shared\ActivityLog;
use App\Models\User\ExchangeRate;
use App\Models\User\User;
use App\Services\Api\BusinessSetup\ExchangeRateService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Settings > Currencies > Exchange Rates (Doc 11 section 7): what each of a
 * business's other currencies is worth in its base currency, kept by date.
 */
class ExchangeRatesApiTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded first, so super-admin holds id 1 and the owner is not taken for an admin.
        $this->seed(RoleSeeder::class);

        $this->user = $this->signIn();
        $this->businessOf($this->user)->forceFill(['base_currency' => 'BDT', 'additional_currencies' => ['EUR', 'USD']])->save();
    }

    private function set(string $currency, string $rate, string $date, array $headers = []): TestResponse
    {
        return $this->postJson('/api/v1/exchange-rates', ['currency' => $currency, 'rate' => $rate, 'effective_date' => $date], $headers);
    }

    public function test_rates_are_kept_by_date_and_listed_by_currency_newest_first(): void
    {
        $this->set('usd', '121.5', '2026-10-01')
            ->assertCreated()
            ->assertJsonPath('message', 'Exchange rate saved successfully.')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.rate', '121.50000000')
            ->assertJsonPath('data.effective_date', '2026-10-01');
        $this->set('USD', '122', '2026-10-20')->assertCreated();
        $this->set('EUR', '131.25', '2026-10-05')->assertCreated();

        $this->assertSame(
            [['EUR', '131.25000000', '2026-10-05'], ['USD', '122.00000000', '2026-10-20'], ['USD', '121.50000000', '2026-10-01']],
            collect($this->getJson('/api/v1/exchange-rates')->assertOk()->json('data'))
                ->map(fn (array $row) => [$row['currency'], $row['rate'], $row['effective_date']])
                ->all(),
        );

        $this->assertSame(
            ['Set the USD rate to 121.5 BDT from 2026-10-01', 'Set the USD rate to 122 BDT from 2026-10-20', 'Set the EUR rate to 131.25 BDT from 2026-10-05'],
            ActivityLog::where('user_id', $this->user->id)->orderBy('id')->pluck('description')->all(),
        );
    }

    public function test_a_second_rate_for_the_same_day_replaces_the_first(): void
    {
        $this->set('USD', '121.5', '2026-10-01')->assertCreated();
        $this->set('USD', '121.75', '2026-10-01')->assertOk()->assertJsonPath('data.rate', '121.75000000');

        $this->assertSame(1, ExchangeRate::count());
    }

    public function test_only_the_business_other_currencies_take_a_rate(): void
    {
        $this->set('BDT', '1', '2026-10-01')
            ->assertJsonValidationErrors(['currency' => 'Choose one of your other currencies. Switch it on under Settings > Currencies first.']);
        $this->set('GBP', '150', '2026-10-01')->assertJsonValidationErrors('currency');
        $this->set('USD', '0', '2026-10-01')->assertJsonValidationErrors(['rate' => 'A rate is above zero.']);
        $this->set('USD', '1.123456789', '2026-10-01')->assertJsonValidationErrors(['rate' => 'A rate can have at most eight decimal places.']);
        $this->set('USD', '121', '01/10/2026')->assertJsonValidationErrors('effective_date');

        $this->assertSame(0, ExchangeRate::count());
    }

    public function test_the_rate_in_force_on_a_day_is_the_latest_from_that_day_or_before(): void
    {
        $this->set('USD', '120', '2026-10-01');
        $this->set('USD', '122', '2026-10-20');

        $rates    = app(ExchangeRateService::class);
        $business = $this->businessOf($this->user)->fresh();

        $this->assertNull($rates->rateOn($business, 'USD', '2026-09-30'));
        $this->assertSame('120.00000000', $rates->rateOn($business, 'USD', '2026-10-19'));
        $this->assertSame('122.00000000', $rates->rateOn($business, 'USD', '2026-10-20'));
        $this->assertSame('1', $rates->rateOn($business, 'BDT', '2026-10-20'));
    }

    public function test_rates_against_an_earlier_base_currency_are_kept_but_no_longer_used(): void
    {
        $this->set('USD', '120', '2026-10-01');
        $this->businessOf($this->user)->forceFill(['base_currency' => 'EUR', 'additional_currencies' => ['USD']])->save();

        $this->assertSame([], $this->getJson('/api/v1/exchange-rates')->json('data'));
        $this->assertNull(app(ExchangeRateService::class)->rateOn($this->businessOf($this->user)->fresh(), 'USD', '2026-10-20'));
        $this->assertSame(1, ExchangeRate::count());
    }

    public function test_a_rate_is_removed_and_another_business_rate_is_not_found(): void
    {
        $id = $this->set('USD', '120', '2026-10-01')->json('data.id');

        $other = $this->user->workspaces()->create(['name' => 'Second Business']);
        $other->forceFill(['base_currency' => 'USD', 'additional_currencies' => ['EUR']])->save();
        $elsewhere = $this->set('EUR', '1.1', '2026-10-01', $this->pick($other))->assertCreated()->json('data.id');

        $this->deleteJson("/api/v1/exchange-rates/{$elsewhere}")->assertNotFound()->assertJsonPath('message', 'Exchange rate not found.');
        $this->deleteJson("/api/v1/exchange-rates/{$id}")->assertOk()->assertJsonPath('message', 'Exchange rate removed successfully.');

        $this->assertSame([$elsewhere], ExchangeRate::pluck('id')->all());
        $this->assertSame('Removed the USD rate of 2026-10-01', ActivityLog::where('user_id', $this->user->id)->orderByDesc('id')->value('description'));
    }
}
