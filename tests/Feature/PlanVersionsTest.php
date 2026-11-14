<?php

namespace Tests\Feature;

use App\Models\Admin\Role;
use App\Models\Billing\Package;
use App\Models\Billing\PackageVersion;
use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\Admin\UsageRepository;
use App\Services\User\Apps\SyncAppsWithPackage;
use App\Services\User\Workspaces\PlanLimits;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\PricesPlans;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 15 Phase E3b (section 15.8): a change to a plan's limits, branding,
 * levels, trial or grace is a new version from a day, kept with the ones
 * before. As one starts, a business subscribed to the plan stays on the
 * version it was on; one without a subscription has the plan's terms as
 * they stand.
 */
class PlanVersionsTest extends TestCase
{
    use PricesPlans;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $admin;

    private User $owner;

    private Workspace $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Mail::fake();
        Http::fake(['*' => Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])]);
        $this->travelTo(Carbon::parse('2026-11-09 10:00:00', 'UTC'));

        $this->priced('standard', '19.50', '195.00');
        $this->priced('premium', '49.00', '490.00');

        $this->admin    = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]);
        $this->owner    = $this->signIn();
        $this->business = $this->businessOf($this->owner);
        $this->putJson('/api/v1/subscription/billing-details', ['name' => 'Uddin Traders Ltd', 'email' => 'accounts@uddin.example', 'address' => 'Dhaka', 'country' => 'BD'])->assertOk();
    }

    private function plan(string $key): Package
    {
        return Package::where('key', $key)->sole();
    }

    /** $data saved on plan $key in the admin, with the rest of the form as it stands - its switches sent, as the form sends them. */
    private function edit(string $key, array $data)
    {
        $plan     = $this->plan($key);
        $response = $this->actingAs($this->admin)->putJson(route('admin.packages.update', $plan->id), ['name' => $plan->name, 'is_active' => 1, 'removes_branding' => (int) $plan->removes_branding, ...$data]);
        Sanctum::actingAs($this->owner);

        return $response;
    }

    /** $key, monthly, paid - for the signed-in owner's business. */
    private function subscribe(string $key): void
    {
        $id = $this->postJson('/api/v1/subscription', ['package' => $key, 'cycle' => 'monthly'])->assertCreated()->json('data.open_invoice.id');
        $this->actingAs($this->admin)->post(route('admin.subscription-invoices.confirm', $id))->assertRedirect();
        Sanctum::actingAs($this->owner);
    }

    private function version(string $key, int $number): PackageVersion
    {
        return $this->plan($key)->versions()->where('number', $number)->sole();
    }

    public function test_a_change_is_a_new_version_and_a_subscriber_stays_on_the_one_it_was_on(): void
    {
        $this->edit('standard', ['user_limit' => 5, 'storage_limit_mb' => 2048])->assertOk()
            ->assertJsonPath('message', 'Plan updated: version 2 has started. 0 subscribers stay on the version before.');
        $this->subscribe('standard');

        // Another business on Standard, without a subscription.
        $other = $this->businessOf(User::factory()->create());
        $other->forceFill(['package_id' => $this->plan('standard')->id])->save();

        $this->edit('standard', ['user_limit' => 3, 'storage_limit_mb' => 2048, 'removes_branding' => 0])->assertOk()
            ->assertJsonPath('message', 'Plan updated: version 3 has started. 1 subscriber stays on the version before.');

        $this->assertSame([3, false], [$this->plan('standard')->user_limit, $this->plan('standard')->removes_branding]);
        $this->assertSame([$this->version('standard', 2)->id, null], [$this->business->fresh()->package_version_id, $other->fresh()->package_version_id]);

        // The subscriber keeps 5 users and its branding; the other business has 3.
        $limits = app(PlanLimits::class);
        $this->assertSame([5, 3], [$limits->usage($this->business->fresh())['users']['limit'], $limits->usage($other->fresh())['users']['limit']]);
        $page   = $this->getJson('/api/v1/subscription')->assertOk()->json('data');
        $this->assertTrue($page['package']['removes_branding']);
        $this->assertSame([5, 2048], [collect($page['packages'])->firstWhere('key', 'standard')['user_limit'], collect($page['packages'])->firstWhere('key', 'standard')['storage_limit_mb']]);
        $this->assertSame(3, collect($this->getJson('/api/v1/catalog')->json('data.packages'))->firstWhere('key', 'standard')['user_limit']);

        // Admin > Usage and Businesses count by it too.
        $this->assertSame([5, 3], [app(UsageRepository::class)->find($this->business->id)->user_limit, app(UsageRepository::class)->find($other->id)->user_limit]);
        $this->actingAs($this->admin)->get(route('admin.businesses.show', $this->business->id))->assertOk()->assertSee('Version 2, from Nov 09, 2026');

        // The versions, newest first: the one waiting for no day, its businesses, what changed.
        $this->actingAs($this->admin)->get(route('admin.packages.edit', $this->plan('standard')->id))->assertOk()
            ->assertSeeInOrder(['Versions', 'Version 3', 'Current', '3 users', '1 business', 'Version 2', 'Earlier', '5 users', '1 business', 'Version 1', 'Earlier', 'Unlimited users', '0 businesses']);
    }

    public function test_a_change_from_a_later_day_waits_for_it(): void
    {
        $this->subscribe('premium');

        $this->edit('premium', ['storage_limit_mb' => 4096, 'effective_on' => '2026-11-20'])->assertOk()
            ->assertJsonPath('message', 'Plan updated: version 2 starts on 20 Nov 2026. Until then the plan\'s terms stay as they are.');
        $this->assertNull($this->plan('premium')->storage_limit_mb);

        // One waits at a time; the rest of the form still counts at once.
        $this->edit('premium', ['user_limit' => 10])->assertJsonValidationErrors(['effective_on' => 'Version 2 starts on 20 Nov 2026. Remove it, under Versions, before changing the limits, branding, levels, trial or grace again.']);
        $this->edit('premium', ['description' => 'The most of everything.'])->assertOk()->assertJsonPath('message', 'Plan updated successfully.');
        $this->edit('premium', ['effective_on' => '2026-11-08'])->assertJsonValidationErrors(['effective_on' => 'Choose today or a later day.']);

        $this->travelTo(Carbon::parse('2026-11-19 23:00:00', 'UTC'));
        $this->artisan('plans:advance')->expectsOutput('Started 0 plan versions.')->assertSuccessful();

        $this->travelTo(Carbon::parse('2026-11-20 00:10:00', 'UTC'));
        $this->artisan('plans:advance')->expectsOutput('Started 1 plan version.')->assertSuccessful();
        $this->artisan('plans:advance')->expectsOutput('Started 0 plan versions.')->assertSuccessful();

        // The plan has its new storage; the subscriber stays on version 1's - none.
        $this->assertSame(4096, $this->plan('premium')->storage_limit_mb);
        $this->assertSame($this->version('premium', 1)->id, $this->business->fresh()->package_version_id);
        $this->assertNull(app(\App\Services\Files\StorageUsage::class)->limitBytes($this->business->fresh()));
        $this->assertSame('The most of everything.', $this->plan('premium')->description);
        $this->assertTrue(ActivityLog::where('description', 'Plan "Premium" moved to its version 2; 1 subscriber stays on the version before')->exists());
    }

    public function test_a_waiting_version_can_be_removed_and_a_started_one_cannot(): void
    {
        $this->edit('standard', ['user_limit' => 8, 'effective_on' => '2026-12-01'])->assertOk();
        $waiting = $this->version('standard', 2);
        $started = $this->version('standard', 1);

        $this->actingAs($this->admin)->get(route('admin.packages.edit', $this->plan('standard')->id))->assertOk()->assertSee('Waiting')->assertSee('Remove This Version?');

        $this->actingAs($this->admin)->deleteJson(route('admin.packages.version-remove', [$this->plan('standard')->id, $started->id]))->assertJsonValidationErrors(['version' => 'Version 1 has started: businesses are on it, so it stays.']);
        $this->actingAs($this->admin)->deleteJson(route('admin.packages.version-remove', [$this->plan('standard')->id, $waiting->id]))->assertOk()
            ->assertJsonPath('message', "Version 2 is removed: the plan's terms stay as they are.");

        $this->assertSame(1, $this->plan('standard')->versions()->count());
        $this->assertNull($this->plan('standard')->user_limit);

        // Free again to change.
        $this->edit('standard', ['user_limit' => 8])->assertOk()->assertJsonPath('message', 'Plan updated: version 2 has started. 0 subscribers stay on the version before.');
    }

    public function test_a_subscriber_keeps_its_versions_grace_and_moving_plan_takes_the_new_ones_terms(): void
    {
        $this->edit('standard', ['grace_days' => 5])->assertOk();
        $this->subscribe('standard');
        $this->edit('standard', ['grace_days' => ''])->assertOk();

        // Unpaid as the period ends on 8 Dec: version 2's grace, not none.
        $this->travelTo(Carbon::parse('2026-12-09 10:00:00', 'UTC'));
        $this->artisan('subscriptions:advance')->assertSuccessful();
        $this->getJson('/api/v1/subscription')->assertJsonPath('data.status', 'grace')->assertJsonPath('data.subscription.grace_ends_on', '2026-12-13');

        // The same plan keeps the version; another plan has its terms as they stand.
        $sync = app(SyncAppsWithPackage::class);
        $sync->changePackage($this->business->fresh(), $this->plan('standard'));
        $this->assertSame($this->version('standard', 2)->id, $this->business->fresh()->package_version_id);

        $sync->changePackage($this->business->fresh(), $this->plan('premium'));
        $this->assertNull($this->business->fresh()->package_version_id);
        $this->assertSame($this->plan('premium')->id, $this->business->fresh()->terms()->id);
    }
}
