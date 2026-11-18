<?php

namespace Tests\Feature;

use App\Models\Admin\AdminAuditLog;
use App\Models\Admin\FeatureFlag;
use App\Models\Admin\Role;
use App\Models\Billing\Package;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Services\Shared\Features;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 15 Phase E1 (section 15.12): feature flags - on, between their days,
 * for the businesses they name and a steady share of those in their plans
 * and countries; off at once by the switch; asked by Features::enabled(),
 * and handed to the app with its navigation.
 */
class FeatureFlagTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $admin;

    /** @var array<string, Workspace> */
    private array $business = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'UTC'));
        $this->admin = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]);

        foreach ([['karim', 'standard', 'BD'], ['rahim', 'premium', 'IN'], ['uddin', 'starter', 'BD']] as [$name, $plan, $country]) {
            $owner    = User::factory()->create();
            $business = $owner->workspaces()->create(['name' => ucfirst($name).' Stores']);
            Workspace::whereKey($business->id)->update(['package_id' => Package::where('key', $plan)->value('id')]);
            $business->businessSetting()->create([
                'user_id' => $owner->id, 'business_name' => ucfirst($name).' Stores', 'language' => 'en', 'country' => $country, 'state' => 'Dhaka',
                'city'    => 'Dhaka', 'zip_code' => '1207', 'address_line_1' => 'Near City Center', 'timezone' => 'UTC', 'stock_accounting_method' => 'fifo',
            ]);
            $this->business[$name] = $business->fresh();
        }
    }

    private function flag(string $key, array $data = []): FeatureFlag
    {
        return FeatureFlag::create([...['key' => $key, 'name' => ucfirst($key), 'status' => FeatureFlag::ON, 'percentage' => 0], ...$data]);
    }

    /** @return list<string> who has $key on, by name */
    private function reached(string $key): array
    {
        app(Features::class)->forget();

        return array_keys(array_filter($this->business, fn (Workspace $business) => Features::enabled($key, $business)));
    }

    public function test_a_flag_reaches_the_businesses_it_names_and_a_steady_share_of_its_plans_and_countries(): void
    {
        $this->flag('pilot', ['businesses' => [$this->business['uddin']->id]]);
        $this->flag('bd_standard', ['packages' => ['standard'], 'countries' => ['BD'], 'percentage' => 100]);
        $this->flag('everyone', ['percentage' => 100]);
        $this->flag('half', ['percentage' => 50]);

        $this->assertSame(['uddin'], $this->reached('pilot'));
        $this->assertSame(['karim'], $this->reached('bd_standard'));
        $this->assertSame(['karim', 'rahim', 'uddin'], $this->reached('everyone'));
        $this->assertSame(array_keys(array_filter($this->business, fn (Workspace $business) => Features::bucket('half', $business->id) < 50)), $this->reached('half'));
        // A business in a share stays in as the share grows.
        FeatureFlag::where('key', 'half')->update(['percentage' => 100]);
        $this->assertSame(['karim', 'rahim', 'uddin'], $this->reached('half'));

        // Without a business, only a flag on for every business is on; a flag not made is off.
        $this->assertTrue(Features::enabled('everyone'));
        $this->assertFalse(Features::enabled('pilot'));
        $this->assertFalse(Features::enabled('no_such_flag', $this->business['karim']));
    }

    public function test_its_days_and_the_switch_decide_when(): void
    {
        $this->flag('later', ['percentage' => 100, 'starts_at' => '2026-11-15 09:00:00']);
        $this->flag('over', ['percentage' => 100, 'ends_at' => '2026-11-14 09:00:00']);
        $this->flag('off', ['percentage' => 100, 'status' => FeatureFlag::OFF]);

        $this->assertSame([[], [], []], [$this->reached('later'), $this->reached('over'), $this->reached('off')]);
        $this->assertSame(['scheduled', 'ended', 'off'], FeatureFlag::orderBy('id')->get()->map(fn (FeatureFlag $flag) => $flag->stateAt(now()))->all());

        $this->travelTo(Carbon::parse('2026-11-15 09:00:00', 'UTC'));
        $this->assertSame(['karim', 'rahim', 'uddin'], $this->reached('later'));
    }

    public function test_the_app_gets_a_business_s_features_with_its_navigation_and_a_switch_counts_at_once(): void
    {
        $this->flag('everyone', ['percentage' => 100]);
        $this->flag('bd_standard', ['packages' => ['standard'], 'countries' => ['BD'], 'percentage' => 100]);
        $owner = $this->business['karim']->user;
        $this->signIn($owner);

        $this->getJson('/api/v1/navigation', $this->pick($this->business['karim']))->assertOk()->assertJsonPath('data.features', ['bd_standard', 'everyone']);

        // Switched off from the admin: the next navigation leaves it out, cached or not.
        $this->actingAs($this->admin)->post(route('admin.feature-flags.switch', FeatureFlag::where('key', 'everyone')->value('id')), ['on' => '0'])->assertRedirect(route('admin.feature-flags.index'));
        $this->signIn($owner);
        $this->getJson('/api/v1/navigation', $this->pick($this->business['karim']))->assertOk()->assertJsonPath('data.features', ['bd_standard']);
        $this->assertSame('Switched feature flag everyone off', AdminAuditLog::latest('id')->value('description'));
    }

    public function test_the_admin_adds_changes_switches_and_deletes_a_flag(): void
    {
        $form = ['key' => 'New_Reports', 'name' => 'New reports', 'status' => 'on', 'businesses' => "BW-{$this->business['uddin']->id} Uddin Stores\n{$this->business['rahim']->id}", 'percentage' => '25', 'packages' => ['standard'], 'countries' => ['BD'], 'responsible_id' => $this->admin->id];
        $this->actingAs($this->admin);

        $this->post(route('admin.feature-flags.store'), [...$form, 'key' => '1st'])->assertSessionHasErrors(['key' => 'Start with a letter; then 3 to 60 small letters, numbers, dots, dashes or underscores.']);
        $this->post(route('admin.feature-flags.store'), [...$form, 'businesses' => 'BW-000999'])->assertSessionHasErrors(['businesses' => 'No business has the ID BW-000999.']);
        $this->post(route('admin.feature-flags.store'), [...$form, 'starts_at' => '2026-11-20T10:00', 'ends_at' => '2026-11-19T10:00'])->assertSessionHasErrors(['ends_at' => 'The end comes after the start.']);
        $this->post(route('admin.feature-flags.store'), $form)->assertRedirect(route('admin.feature-flags.index'));
        $this->post(route('admin.feature-flags.store'), [...$form, 'key' => 'new_reports'])->assertSessionHasErrors(['key' => 'Another flag has this key.']);

        $flag = FeatureFlag::sole();
        $this->assertSame(['new_reports', [$this->business['uddin']->id, $this->business['rahim']->id], ['standard'], ['BD'], 25], [$flag->key, $flag->businesses, $flag->packages, $flag->countries, $flag->percentage]);
        $this->get(route('admin.feature-flags.index'))->assertOk()->assertSeeInOrder(['New reports', 'new_reports', 'Live', '2 businesses named', '25% of businesses on Standard in BD', 'Switch Off']);
        $this->get(route('admin.feature-flags.edit', $flag->id))->assertOk()->assertSee($this->business['uddin']->businessId().' Uddin Stores');

        // Its key stays; the rest changes.
        $this->put(route('admin.feature-flags.update', $flag->id), [...$form, 'key' => 'other'])->assertSessionHasErrors(['key' => 'A flag keeps the key it was made with: the code asks by it.']);
        $this->put(route('admin.feature-flags.update', $flag->id), [...array_diff_key($form, ['key' => 0]), 'businesses' => '', 'percentage' => '100'])->assertRedirect();
        $this->assertSame([null, 100], [$flag->fresh()->businesses, $flag->fresh()->percentage]);

        // Deleted only once off.
        $this->delete(route('admin.feature-flags.destroy', $flag->id))->assertSessionHasErrors(['flag' => 'Switch new_reports off before deleting it.']);
        $this->post(route('admin.feature-flags.switch', $flag->id), ['on' => '0']);
        $this->delete(route('admin.feature-flags.destroy', $flag->id))->assertRedirect();
        $this->assertSame(0, FeatureFlag::count());
        $this->assertSame(['Deleted feature flag new_reports', 'Switched feature flag new_reports off', 'Changed feature flag new_reports, on', 'Added feature flag new_reports, on'], AdminAuditLog::where('description', 'like', '%feature flag%')->latest('id')->pluck('description')->all());
    }

    public function test_product_administrators_run_flags_read_only_ones_look(): void
    {
        $flag = $this->flag('pilot');
        $role = fn (string $slug) => User::factory()->create(['role_id' => Role::where('slug', $slug)->value('id')]);

        $this->actingAs($role('product-administrator'))->post(route('admin.feature-flags.switch', $flag->id), ['on' => '0'])->assertRedirect();
        $this->assertSame(FeatureFlag::OFF, $flag->fresh()->status);

        $readOnly = $role('read-only-administrator');
        $this->actingAs($readOnly)->get(route('admin.feature-flags.index'))->assertOk()->assertDontSee('Switch On')->assertDontSee('Add Flag');
        $this->actingAs($readOnly)->post(route('admin.feature-flags.switch', $flag->id), ['on' => '1'])->assertForbidden();
        $this->actingAs($role('billing-administrator'))->get(route('admin.feature-flags.index'))->assertForbidden();
    }
}
