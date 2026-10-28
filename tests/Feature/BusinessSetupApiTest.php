<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Settings > Currencies and Settings > Taxes (Doc 01 sections 11 and 12): a
 * business's base currency, its other currencies, and whether it uses taxes.
 */
class BusinessSetupApiTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        // GBP exists but an admin has switched it off.
        Currency::create(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);
        Currency::create(['name' => 'Bangladeshi Taka', 'code' => 'BDT', 'symbol' => '৳', 'is_default' => false, 'is_active' => true]);
        Currency::create(['name' => 'Euro', 'code' => 'EUR', 'symbol' => '€', 'is_default' => false, 'is_active' => true]);
        Currency::create(['name' => 'Pound Sterling', 'code' => 'GBP', 'symbol' => '£', 'is_default' => false, 'is_active' => false]);
    }

    /* ---------------------------------------------------------- a new business */

    public function test_a_new_business_starts_on_the_platform_currency_with_taxes_undecided(): void
    {
        $this->signIn();

        $this->getJson('/api/v1/business-settings/currencies')
            ->assertOk()
            ->assertExactJson(['data' => ['base_currency' => 'USD', 'additional_currencies' => []]]);

        $this->getJson('/api/v1/business-settings/taxes')
            ->assertOk()
            ->assertExactJson(['data' => [
                'tax_mode'                => 'later',
                'price_tax_mode'          => 'exclusive',
                'tax_rounding'            => 'line',
                'default_sales_tax_id'    => null,
                'default_purchase_tax_id' => null,
            ]]);
    }

    public function test_both_need_a_token_and_a_business(): void
    {
        $this->getJson('/api/v1/business-settings/currencies')->assertUnauthorized();
        $this->putJson('/api/v1/business-settings/taxes', ['tax_mode' => 'enabled'])->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/business-settings/currencies')
            ->assertNotFound()
            ->assertJsonPath('message', 'Create a workspace first.');
        $this->putJson('/api/v1/business-settings/taxes', ['tax_mode' => 'enabled'])->assertNotFound();
    }

    /* ---------------------------------------------------------- currencies */

    public function test_the_base_and_other_currencies_are_saved(): void
    {
        $user = $this->signIn();

        // Lower case, out of order: stored upper case, sorted.
        $this->putJson('/api/v1/business-settings/currencies', ['base_currency' => 'bdt', 'additional_currencies' => ['usd', 'EUR']])
            ->assertOk()
            ->assertJsonPath('message', 'Currencies saved successfully.')
            ->assertExactJson([
                'message' => 'Currencies saved successfully.',
                'data'    => ['base_currency' => 'BDT', 'additional_currencies' => ['EUR', 'USD']],
            ]);

        $business = $this->businessOf($user)->fresh();
        $this->assertSame('BDT', $business->base_currency);
        $this->assertSame(['BDT', 'EUR', 'USD'], $business->currencyCodes());
    }

    public function test_every_currency_must_be_an_active_one_chosen_once_and_not_the_base(): void
    {
        $this->signIn();

        foreach (['GBP', 'XYZ', ''] as $code) {
            $this->putJson('/api/v1/business-settings/currencies', ['base_currency' => $code])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('base_currency');
        }

        $this->putJson('/api/v1/business-settings/currencies', ['base_currency' => 'USD', 'additional_currencies' => ['GBP']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('additional_currencies.0');

        $this->putJson('/api/v1/business-settings/currencies', ['base_currency' => 'USD', 'additional_currencies' => ['EUR', 'EUR']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['additional_currencies.0' => 'only once']);

        $this->putJson('/api/v1/business-settings/currencies', ['base_currency' => 'USD', 'additional_currencies' => ['USD']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('additional_currencies.0');
    }

    public function test_a_currency_a_payment_account_uses_cannot_be_dropped(): void
    {
        $user = $this->signIn();
        $this->putJson('/api/v1/business-settings/currencies', ['base_currency' => 'USD', 'additional_currencies' => ['EUR']])->assertOk();
        $this->businessOf($user)->paymentAccounts()->create(['type' => 'bank', 'name' => 'Euro Account', 'currency' => 'EUR']);

        $this->putJson('/api/v1/business-settings/currencies', ['base_currency' => 'USD', 'additional_currencies' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['additional_currencies' => 'Euro Account']);

        // Moving the base currency away from one in use is the same: keep it as another currency.
        $this->businessOf($user)->paymentAccounts()->create(['type' => 'cash', 'name' => 'Cash', 'currency' => 'USD']);

        $this->putJson('/api/v1/business-settings/currencies', ['base_currency' => 'BDT', 'additional_currencies' => ['EUR']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['additional_currencies' => '"Cash"']);

        $this->putJson('/api/v1/business-settings/currencies', ['base_currency' => 'BDT', 'additional_currencies' => ['EUR', 'USD']])->assertOk();
    }

    public function test_each_business_has_its_own_currencies(): void
    {
        $user   = $this->signIn();
        $second = $user->workspaces()->create(['name' => 'Second Business']);

        $this->putJson('/api/v1/business-settings/currencies', ['base_currency' => 'BDT'], $this->pick($second))->assertOk();

        $this->getJson('/api/v1/business-settings/currencies')->assertJsonPath('data.base_currency', 'USD');
        $this->getJson('/api/v1/business-settings/currencies', $this->pick($second))->assertJsonPath('data.base_currency', 'BDT');
    }

    /* ---------------------------------------------------------- taxes */

    public function test_taxes_can_be_turned_on_off_or_left_for_later(): void
    {
        $user = $this->signIn();

        foreach (['enabled', 'disabled', 'later'] as $mode) {
            $this->putJson('/api/v1/business-settings/taxes', ['tax_mode' => $mode])
                ->assertOk()
                ->assertJsonPath('data.tax_mode', $mode);

            $this->assertSame($mode, $this->businessOf($user)->fresh()->tax_mode);
        }

        foreach (['', 'yes', null] as $mode) {
            $this->putJson('/api/v1/business-settings/taxes', ['tax_mode' => $mode])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('tax_mode');
        }
    }

    public function test_turning_taxes_off_keeps_the_tax_details_already_recorded(): void
    {
        $user     = $this->signIn();
        $business = $this->businessOf($user);
        $contact  = $business->contacts()->create(['name' => 'Acme', 'tax_number' => 'VAT-123']);

        $this->putJson('/api/v1/business-settings/taxes', ['tax_mode' => 'disabled'])->assertOk();

        $this->assertSame('VAT-123', $contact->fresh()->tax_number);
    }

    /* ---------------------------------------------------------- the sidebar's copy */

    public function test_the_sidebar_carries_both_and_follows_a_change(): void
    {
        $this->signIn();

        $this->getJson('/api/v1/navigation')
            ->assertJsonPath('data.workspace.base_currency', 'USD')
            ->assertJsonPath('data.workspace.currencies', ['USD'])
            ->assertJsonPath('data.workspace.tax_mode', 'later');

        $this->putJson('/api/v1/business-settings/currencies', ['base_currency' => 'BDT', 'additional_currencies' => ['USD']])->assertOk();
        $this->putJson('/api/v1/business-settings/taxes', ['tax_mode' => 'enabled'])->assertOk();

        // The cached copy is rebuilt, not served stale.
        $this->getJson('/api/v1/navigation')
            ->assertJsonPath('data.workspace.base_currency', 'BDT')
            ->assertJsonPath('data.workspace.currencies', ['BDT', 'USD'])
            ->assertJsonPath('data.workspace.tax_mode', 'enabled');

        // A change to the other currencies alone is enough.
        $this->putJson('/api/v1/business-settings/currencies', ['base_currency' => 'BDT', 'additional_currencies' => ['EUR', 'USD']])->assertOk();

        $this->getJson('/api/v1/navigation')->assertJsonPath('data.workspace.currencies', ['BDT', 'EUR', 'USD']);
    }

    public function test_each_save_is_logged_with_what_changed(): void
    {
        $user = $this->signIn();

        $this->putJson('/api/v1/business-settings/currencies', ['base_currency' => 'BDT', 'additional_currencies' => ['EUR']])->assertOk();
        $this->putJson('/api/v1/business-settings/currencies', ['base_currency' => 'BDT', 'additional_currencies' => ['EUR']])->assertOk();
        $this->putJson('/api/v1/business-settings/taxes', ['tax_mode' => 'enabled'])->assertOk();
        $this->putJson('/api/v1/business-settings/taxes', ['tax_mode' => 'enabled'])->assertOk();

        // One entry per save, never LogApiWriteActivity's vaguer "PUT ..." one;
        // the second of each changed nothing.
        $logs = ActivityLog::where('user_id', $user->id)->orderBy('id')->get();

        $this->assertSame([
            'Updated the business currencies',
            'Updated the business currencies',
            'Set taxes to "enabled"',
            'Set taxes to "enabled"',
        ], $logs->pluck('description')->all());
        $this->assertSame([['base_currency', 'additional_currencies'], [], ['tax_mode'], []], $logs->pluck('properties.fields')->all());
    }

    /* ---------------------------------------------------------- the migration */

    public function test_existing_businesses_keep_their_currency_and_tax_details(): void
    {
        $migration = require database_path('migrations/2025_01_01_000068_give_businesses_a_currency_and_tax_choice.php');
        $migration->down();

        $user     = User::factory()->create();
        $taxed    = Workspace::withoutEvents(fn () => $user->workspaces()->create(['name' => 'Taxed']));
        $untaxed  = Workspace::withoutEvents(fn () => $user->workspaces()->create(['name' => 'Untaxed']));
        $nothing  = Workspace::withoutEvents(fn () => $user->workspaces()->create(['name' => 'No Settings']));

        foreach ([[$taxed, 'BDT', 'VAT'], [$untaxed, 'EUR', null]] as [$business, $currency, $tax]) {
            DB::table('business_settings')->insert([
                'user_id' => $user->id, 'workspace_id' => $business->id, 'business_name' => $business->name,
                'currency' => $currency, 'language' => 'en', 'country' => 'Bangladesh', 'state' => 'Dhaka',
                'city' => 'Dhaka', 'zip_code' => '1207', 'landmark' => 'Near City Center', 'timezone' => 'Asia/Dhaka',
                'tax_1_name' => $tax, 'financial_year_start_month' => 7, 'stock_accounting_method' => 'fifo',
            ]);
        }

        $migration->up();

        // Only the workspaces are checked: on SQLite, inside this test's
        // transaction, making base_currency NOT NULL rebuilds the table and
        // cascades to its children. MySQL alters the column in place.
        $this->assertSame(['BDT', 'enabled'], [$taxed->fresh()->base_currency, $taxed->fresh()->tax_mode]);
        $this->assertSame(['EUR', 'later'], [$untaxed->fresh()->base_currency, $untaxed->fresh()->tax_mode]);
        $this->assertSame(['USD', 'later'], [$nothing->fresh()->base_currency, $nothing->fresh()->tax_mode]);
        $this->assertFalse(Schema::hasColumn('business_settings', 'currency'));
    }

    public function test_rolling_back_gives_the_settings_their_currency_again(): void
    {
        $user = $this->signIn();
        $this->putJson('/api/v1/business-settings/currencies', ['base_currency' => 'BDT'])->assertOk();
        DB::table('business_settings')->insert([
            'user_id' => $user->id, 'workspace_id' => $this->businessOf($user)->id, 'business_name' => 'Acme',
            'language' => 'en', 'country' => 'Bangladesh', 'state' => 'Dhaka', 'city' => 'Dhaka', 'zip_code' => '1207',
            'landmark' => 'Near City Center', 'timezone' => 'Asia/Dhaka', 'financial_year_start_month' => 7, 'stock_accounting_method' => 'fifo',
        ]);

        (require database_path('migrations/2025_01_01_000068_give_businesses_a_currency_and_tax_choice.php'))->down();

        $this->assertSame('BDT', DB::table('business_settings')->value('currency'));
        $this->assertFalse(Schema::hasColumn('workspaces', 'base_currency'));
        $this->assertFalse(Schema::hasColumn('workspaces', 'tax_mode'));
    }
}
