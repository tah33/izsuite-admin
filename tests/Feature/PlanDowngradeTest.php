<?php

namespace Tests\Feature;

use App\Models\Admin\Role;
use App\Models\Billing\Package;
use App\Models\Frontend\AppCategory;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\Location;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use App\Support\Permissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Moving a business to a lower plan (Doc 13 sections 13 and 14) - Doc 13
 * Phase B2: the admin sees what the move does before making it; apps go to
 * Lite or off; over a user or location limit nothing is switched off until
 * the Business Owner chooses what stays active; and usage warns as a limit
 * nears, is reached and is passed.
 */
class PlanDowngradeTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $owner;

    private Workspace $business;

    /** @var array<string, Package> by key */
    private array $packages;

    /** @var array<string, WorkspaceMember> two active users and an invitation */
    private array $members;

    /** @var array<string, Location> the Primary, another active one and an inactive one */
    private array $locations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Mail::fake();

        $this->packages  = Package::all()->keyBy('key')->all();
        $category        = AppCategory::create(['name' => 'Sales & Commerce', 'sort_order' => 1]);
        $app             = fn (string $key, string $edition, string $lowest) => tap(Application::create([
            'app_key'     => $key, 'name' => ucfirst(str_replace('_', ' ', $key)), 'family_key' => 'sales', 'edition' => $edition,
            'category_id' => $category->id, 'status' => 'available', 'is_active' => true,
        ]), fn (Application $app) => $app->packages()->attach(collect($this->packages)->filter(fn ($p) => $p->rank >= $this->packages[$lowest]->rank)->pluck('id')));

        $app('sales_lite', 'lite', 'starter');
        $sales           = $app('sales', 'full', 'standard');

        $this->owner     = $this->signIn();
        $this->business  = $this->businessOf($this->owner);
        $this->business->package()->associate($this->packages['standard'])->save();
        $this->business->workspaceApps()->where('family_key', 'sales')->update(['app_id' => $sales->id, 'status' => 'active']);
        app(SetUpNewWorkspace::class)->handle($this->business, $this->owner, 'api');

        $role            = $this->business->roles()->where('template', 'sales_user')->value('id');
        $active          = function (string $first) use ($role): WorkspaceMember {
            $user = User::factory()->create(['first_name' => $first, 'last_name' => 'Staff']);

            return $this->business->members()->create(['user_id' => $user->id, 'role_id' => $role, 'name' => $user->name, 'email' => $user->email, 'status' => 'active']);
        };
        $this->members   = [
            'rahim'   => $active('Rahim'),
            'karim'   => $active('Karim'),
            'invited' => $this->business->members()->create(['role_id' => $role, 'name' => 'Nadia Invited', 'email' => 'nadia@example.com', 'status' => 'pending']),
        ];

        // Setting the business up made its Primary Location.
        $place           = fn (string $name, array $more = []) => $this->business->locations()->create(['user_id' => $this->owner->id, 'name' => $name, 'code' => strtoupper(substr($name, 0, 4)), ...$more]);
        $this->locations = ['main' => $this->business->locations()->where('is_primary', true)->sole(), 'port' => $place('Port Depot'), 'old' => $place('Old Shop', ['is_active' => false])];
    }

    private function toStarter(): void
    {
        $this->business->package()->associate($this->packages['starter'])->save();
    }

    public function test_the_admin_sees_what_a_move_down_does_before_making_it(): void
    {
        $this->business->files()->create(['workspace_id' => $this->business->id, 'collection' => 'logo', 'disk' => 'public', 'path' => 'x.png', 'visibility' => 'public', 'name' => 'x.png', 'size' => 150 * 1024 * 1024]);
        $this->actingAs(User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]));

        $this->get(route('admin.workspaces.package.review', ['id' => $this->business->id, 'package_id' => $this->packages['starter']->id]))
            ->assertOk()
            ->assertSee('From Standard down to Billing Starter')
            ->assertSeeInOrder(['Sales', 'Sales lite'])
            ->assertSee('4 users · the plan gives 1 user')
            ->assertSee('2 active locations · the plan gives 1 location')
            ->assertSee('150 MB · the plan gives 100 MB')
            ->assertSee('The owner chooses who stays active')
            ->assertSee('uploads stop until');

        // Seeing it changes nothing.
        $this->assertSame('standard', $this->business->fresh()->package->key);
        $this->assertSame('sales', $this->business->workspaceApps()->with('app')->where('family_key', 'sales')->first()->app->app_key);

        $this->get(route('admin.workspaces.package.review', ['id' => $this->business->id, 'package_id' => $this->packages['premium']->id]))
            ->assertOk()->assertSee('Nothing is turned off');

        // The move itself: apps within the plan, back to the client's page; nobody switched off yet.
        $this->patch(route('admin.workspaces.package', $this->business->id), ['package_id' => $this->packages['starter']->id])
            ->assertRedirect(route('admin.clients.show', $this->owner->id));

        $this->assertSame('sales_lite', $this->business->workspaceApps()->with('app')->where('family_key', 'sales')->first()->app->app_key);
        $this->assertSame(['active', 'active', 'pending'], array_map(fn (WorkspaceMember $member) => $member->fresh()->status, array_values($this->members)));
        $this->assertTrue($this->locations['port']->fresh()->is_active);
    }

    public function test_usage_warns_as_a_limit_nears_is_reached_and_is_passed(): void
    {
        $this->packages['standard']->update(['user_limit' => 5, 'location_limit' => 2]);

        // Four users of five - the owner, two active and one invited; two active locations of two, the inactive one aside.
        $this->getJson('/api/v1/business-settings/usage')
            ->assertJsonPath('data.users', ['used' => 4, 'limit' => 5, 'state' => 'warning'])
            ->assertJsonPath('data.locations', ['used' => 2, 'limit' => 2, 'state' => 'full']);

        // Turning the inactive one on needs room.
        $this->postJson("/api/v1/locations/{$this->locations['old']->id}/activate")
            ->assertForbidden()->assertJsonPath('message', 'Standard includes 2 locations. Upgrade the plan to add another.');

        $this->toStarter();
        $this->getJson('/api/v1/business-settings/usage')
            ->assertJsonPath('data.users', ['used' => 4, 'limit' => 1, 'state' => 'over'])
            ->assertJsonPath('data.locations', ['used' => 2, 'limit' => 1, 'state' => 'over']);
    }

    public function test_the_owner_chooses_who_and_what_stays_active(): void
    {
        $this->packages['starter']->update(['user_limit' => 2]);
        $this->toStarter();

        $this->getJson('/api/v1/subscription')->assertOk()
            ->assertJsonPath('data.excess', ['users' => ['used' => 4, 'limit' => 2], 'locations' => ['used' => 2, 'limit' => 1], 'can_choose' => true]);

        $choices = $this->getJson('/api/v1/subscription/over-limit')->assertOk()->json('data');
        $this->assertSame([$this->owner->name, 'Karim Staff', 'Rahim Staff', 'Nadia Invited'], array_column($choices['users']['members'], 'name'));
        $this->assertSame([true, false, false, false], array_column($choices['users']['members'], 'owner'));
        $this->assertSame(['Main Location', 'Port Depot'], array_column($choices['locations']['locations'], 'name'));
        $this->assertSame([2, 1, true], [$choices['users']['limit'], $choices['locations']['limit'], $choices['locations']['locations'][0]['primary']]);

        // No more than the plan holds - the owner and the Primary always among them.
        $this->postJson('/api/v1/subscription/over-limit', ['member_ids' => [$this->members['rahim']->id, $this->members['karim']->id]])
            ->assertJsonValidationErrors(['member_ids' => 'choose one other']);
        $this->postJson('/api/v1/subscription/over-limit', ['location_ids' => [$this->locations['port']->id]])
            ->assertJsonValidationErrors(['location_ids' => 'only the Primary Location stays active']);
        $this->postJson('/api/v1/subscription/over-limit', ['member_ids' => [999999]])->assertJsonValidationErrors('member_ids');

        $this->postJson('/api/v1/subscription/over-limit', ['member_ids' => [$this->members['rahim']->id], 'location_ids' => []])
            ->assertOk()
            ->assertJsonPath('message', 'Kept the business within Billing Starter: suspended 1 user, withdrew 1 invitation, made 1 location inactive.')
            ->assertJsonPath('data.users', ['used' => 2, 'limit' => 2, 'state' => 'full'])
            ->assertJsonPath('data.locations', ['used' => 1, 'limit' => 1, 'state' => 'full']);

        $this->assertSame(['active', 'suspended'], [$this->members['rahim']->fresh()->status, $this->members['karim']->fresh()->status]);
        $this->assertNull($this->members['invited']->fresh());
        $this->assertSame([true, false, false], array_map(fn (Location $location) => $location->fresh()->is_active, array_values($this->locations)));
        $this->assertDatabaseHas('activity_logs', ['description' => 'Suspended user "Karim Staff"']);
        $this->assertDatabaseHas('activity_logs', ['description' => 'Deactivated location "Port Depot"']);
        $this->assertSame(1, ActivityLog::where('description', 'like', 'Kept the business within Billing Starter%')->count());

        // Within the plan now: nothing more to choose.
        $this->getJson('/api/v1/subscription')->assertJsonPath('data.excess', null);
        $this->postJson('/api/v1/subscription/over-limit', [])->assertStatus(409);
    }

    public function test_only_the_owner_chooses(): void
    {
        $this->toStarter();

        $role    = $this->business->roles()->create(['name' => 'Billing Manager', 'permissions' => Permissions::expand(['settings.view', 'settings.subscription'])]);
        $manager = User::factory()->create();
        $this->business->members()->create(['user_id' => $manager->id, 'role_id' => $role->id, 'name' => $manager->name, 'email' => $manager->email, 'status' => 'active']);
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/subscription', $this->pick($this->business))->assertOk()->assertJsonPath('data.excess.can_choose', false);
        $this->getJson('/api/v1/subscription/over-limit', $this->pick($this->business))->assertForbidden();
        $this->postJson('/api/v1/subscription/over-limit', [], $this->pick($this->business))->assertForbidden();

        $this->assertSame('active', $this->members['karim']->fresh()->status);
    }
}
