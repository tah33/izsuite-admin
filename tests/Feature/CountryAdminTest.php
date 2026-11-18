<?php

namespace Tests\Feature;

use App\Models\Admin\AdminAuditLog;
use App\Models\Admin\Country;
use App\Models\Admin\Role;
use App\Models\User\User;
use App\Services\Api\Sales\SalesSnapshots;
use App\Support\Countries;
use App\Support\RegionalDefaults;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Doc 15 Phase F3a (section 15.19): the countries in the admin, each with
 * the defaults a new business there takes - its currency, time zone,
 * regional settings, and its addresses' labels and format. A change to
 * those is the country's next version, from today or a later day; the name
 * and calling code change at once. Saturday 14 November 2026.
 */
class CountryAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'UTC'));

        $this->admin = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID, 'first_name' => 'Rafi']);
    }

    private function country(string $code): Country
    {
        return Country::where('code', $code)->sole();
    }

    /** The country's form as it stands, with $changes. */
    private function save(string $code, array $changes = [], ?User $as = null)
    {
        $country = $this->country($code);

        return $this->actingAs($as ?? $this->admin)->putJson(route('admin.countries.update', $country->id), [
            ...$country->only(['name', 'calling_code', ...Country::DEFAULTS]),
            ...$changes,
        ]);
    }

    /** A business based in $country, at $address. */
    private function businessIn(string $country, array $address)
    {
        $owner    = User::factory()->create();
        $business = $owner->workspaces()->create(['name' => 'Lincoln Supplies']);
        $business->businessSetting()->create([
            'user_id' => $owner->id, 'business_name' => 'Lincoln Supplies', 'language' => 'en', 'country' => $country,
            'timezone' => 'UTC', 'stock_accounting_method' => 'fifo', ...$address,
        ]);

        return $business;
    }

    public function test_the_countries_and_a_countrys_page(): void
    {
        $this->businessIn('BD', ['state' => 'Dhaka', 'city' => 'Dhaka', 'zip_code' => '1207']);

        $this->actingAs($this->admin)->get(route('admin.countries.index'))->assertOk()->assertSee('Afghanistan')->assertSee('Asia/Kabul');
        $this->get(route('admin.countries.index', ['search' => 'bangla']))->assertOk()->assertSee('Bangladesh')->assertSee('BDT')->assertSee('12,34,567.89 (lakh)')->assertDontSee('Bahrain');

        $this->get(route('admin.countries.edit', $this->country('BD')->id))->assertOk()
            ->assertSee('1 business is based here')
            ->assertSee('Defaults for New Businesses')
            ->assertSee('Asia/Dhaka')
            ->assertSeeInOrder(['Versions', 'Version 1', 'Current']);

        // The sample address as the country prints it.
        $this->get(route('admin.countries.edit', $this->country('US')->id))->assertSee('Springfield, Central 1207')->assertSee('America/Chicago');
    }

    public function test_defaults_changed_from_today_start_at_once_for_the_businesses_set_up_from_now(): void
    {
        $this->assertSame(['d/m/Y', 'saturday', 'BDT'], [RegionalDefaults::for('BD')['date_format'], RegionalDefaults::for('BD')['week_start'], RegionalDefaults::currency('BD')]);

        $this->save('BD', ['date_format' => 'Y-m-d', 'week_start' => 'sunday', 'currency' => 'usd'])
            ->assertOk()
            ->assertJsonPath('message', 'Saved: version 2 of its defaults has started. Businesses set up from now start from it; those already set up keep their own settings.');

        $bd = $this->country('BD');
        $this->assertSame(['Y-m-d', 'sunday', 'USD'], [$bd->date_format, $bd->week_start, $bd->currency]);
        $this->assertSame(['Y-m-d', 'sunday', 'USD'], [RegionalDefaults::for('BD')['date_format'], RegionalDefaults::for('BD')['week_start'], RegionalDefaults::currency('BD')]);

        $this->get(route('admin.countries.edit', $bd->id))->assertSeeInOrder(['Version 2', 'Current', 'Version 1', 'Earlier']);
        $this->assertSame(1, AdminAuditLog::where('description', 'Updated country "Bangladesh"; version 2 of its defaults started')->count());

        // The same defaults again are no new version.
        $this->save('BD')->assertOk()->assertJsonPath('message', 'Country saved.');
        $this->assertSame(2, $bd->versions()->count());
    }

    public function test_defaults_changed_from_a_later_day_wait_for_it(): void
    {
        $this->save('BD', ['fiscal_year_start_month' => 1, 'effective_on' => '2026-11-20'])
            ->assertOk()
            ->assertJsonPath('message', 'Saved: version 2 of its defaults starts on 20 Nov 2026. Until then they stay as they are.');

        $this->assertSame(7, $this->country('BD')->fiscal_year_start_month);
        $this->assertSame(7, RegionalDefaults::for('BD')['fiscal_year_start_month']);
        $this->get(route('admin.countries.index', ['search' => 'Bangladesh']))->assertSee('Version 2 from Nov 20, 2026');

        // One waits at a time.
        $this->save('BD', ['paper_size' => 'letter'])->assertJsonValidationErrors(['effective_on' => 'Version 2 starts on 20 Nov 2026']);

        // Its day: countries:advance starts it, once.
        $this->travelTo(Carbon::parse('2026-11-20 00:30:00', 'UTC'));
        $this->artisan('countries:advance')->expectsOutput('Started 1 country version.')->assertSuccessful();
        $this->artisan('countries:advance')->expectsOutput('Started 0 country versions.')->assertSuccessful();

        $this->assertSame(1, $this->country('BD')->fiscal_year_start_month);
        $this->assertSame(1, RegionalDefaults::for('BD')['fiscal_year_start_month']);
    }

    public function test_a_waiting_version_can_be_removed_and_a_started_one_cannot(): void
    {
        $this->save('BD', ['paper_size' => 'letter', 'effective_on' => '2026-12-01'])->assertOk();
        $waiting = $this->country('BD')->versions()->where('number', 2)->sole();

        $this->deleteJson(route('admin.countries.version-remove', [$waiting->country_id, $waiting->id]))
            ->assertOk()
            ->assertJsonPath('message', 'Version 2 is removed: the country\'s defaults stay as they are.');

        $this->assertSame(['a4', 1], [$this->country('BD')->paper_size, $this->country('BD')->versions()->count()]);

        $first = $this->country('BD')->versions()->sole();
        $this->deleteJson(route('admin.countries.version-remove', [$first->country_id, $first->id]))->assertJsonValidationErrors(['version']);
    }

    public function test_a_new_name_shows_at_once_everywhere(): void
    {
        $this->save('TR', ['name' => 'Türkiye', 'calling_code' => '90'])->assertOk()->assertJsonPath('message', 'Country saved.');

        $this->assertSame('Türkiye', Countries::name('TR'));
        $this->assertSame('TR', Countries::codeFor('türkiye'));
        $this->assertSame('TR', Countries::codeFor('Turkey'));
        $this->assertSame(1, $this->country('TR')->versions()->count());

        $listed = collect($this->getJson('/api/v1/countries')->assertOk()->json('data'))->firstWhere('code', 'TR');
        $this->assertSame(['Türkiye', '90', 'Europe/Istanbul', ['Europe/Istanbul']], [$listed['name'], $listed['calling_code'], $listed['timezone'], $listed['timezones']]);
    }

    public function test_documents_print_an_address_as_its_country_writes_it(): void
    {
        $business  = $this->businessIn('US', ['state' => 'IL', 'city' => 'Springfield', 'zip_code' => '62704', 'address_line_1' => '1 Main St']);
        $snapshots = app(SalesSnapshots::class);

        $this->assertSame(['1 Main St', 'Springfield, IL 62704', 'United States'], $snapshots->business($business, null)['address']);

        // Blank lines and spaces at the ends go.
        $this->save('US', ['address_format' => "{line1}\r\n\r\n  {city}, {region}\n{postal}\n{country}"])->assertOk();

        $this->assertSame("{line1}\n{city}, {region}\n{postal}\n{country}", $this->country('US')->address_format);
        $this->assertSame(['1 Main St', 'Springfield, IL', '62704', 'United States'], $snapshots->business($business->fresh(), null)['address']);
    }

    public function test_what_a_country_takes(): void
    {
        $this->save('BD', ['address_format' => "{line1}\n{town}", 'currency' => 'taka', 'timezone' => 'Mars/Olympus', 'calling_code' => '+880', 'date_format' => 'd.m.Y', 'effective_on' => '2026-11-13'])
            ->assertJsonValidationErrors(['address_format' => '{town}', 'currency', 'timezone', 'calling_code', 'date_format', 'effective_on']);

        $this->save('BD', ['address_format' => "{city}\n{country}"])->assertJsonValidationErrors(['address_format' => 'needs {line1}']);
        $this->save('BD', ['address_format' => "{line1}\nAttn: Accounts"])->assertJsonValidationErrors(['address_format' => 'at least one part']);

        $this->assertSame(1, $this->country('BD')->versions()->count());
    }

    public function test_who_may_change_them(): void
    {
        $bd       = $this->country('BD');
        $readOnly = User::factory()->create(['role_id' => Role::where('slug', 'read-only-administrator')->value('id')]);
        $product  = User::factory()->create(['role_id' => Role::where('slug', 'product-administrator')->value('id')]);
        $support  = User::factory()->create(['role_id' => Role::where('slug', 'support-administrator')->value('id')]);

        $this->actingAs($readOnly)->get(route('admin.countries.index'))->assertOk();
        $this->actingAs($readOnly)->get(route('admin.countries.edit', $bd->id))->assertForbidden();
        $this->actingAs($support)->get(route('admin.countries.index'))->assertForbidden();

        $this->actingAs($product)->get(route('admin.countries.edit', $bd->id))->assertOk();
        $this->save('BD', ['paper_size' => 'letter'], $product)->assertOk();
        $this->save('BD', ['name' => 'Bangladesh!'], $readOnly)->assertForbidden();
    }
}
