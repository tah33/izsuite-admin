<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveWorkspace;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkspacesApiTest extends TestCase
{
    use RefreshDatabase;

    private Application $sales;

    /** Billing Starter, which every new workspace starts on. */
    private Package $starter;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded with the others, super-admin keeps id 1 - a "user" role made
        // first by the factory would take it, and every account would pass for
        // an admin, whose activity is not logged.
        $this->seed(RoleSeeder::class);

        $this->starter = Package::where('key', 'starter')->firstOrFail();
        $this->sales   = Application::create(['name' => 'Sales Lite', 'family_key' => 'sales', 'edition' => 'lite', 'is_active' => true]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['name' => 'Main Workspace'], $overrides);
    }

    private function workspaceOf(User $user, array $overrides = []): Workspace
    {
        return $user->workspaces()->create($this->payload($overrides));
    }

    /* ---------------------------------------------------------- auth */

    public function test_every_workspace_endpoint_requires_a_token(): void
    {
        $owner     = User::factory()->create();
        $workspace = $this->workspaceOf($owner);

        $this->getJson('/api/v1/workspaces')->assertUnauthorized();
        $this->postJson('/api/v1/workspaces', $this->payload())->assertUnauthorized();
        $this->putJson("/api/v1/workspaces/{$workspace->id}", $this->payload(['name' => 'Hijacked']))->assertUnauthorized();
        $this->deleteJson("/api/v1/workspaces/{$workspace->id}")->assertUnauthorized();

        $this->assertDatabaseCount('workspaces', 1);
        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id, 'name' => 'Main Workspace']);
    }

    /* ---------------------------------------------------------- list */

    public function test_a_new_account_has_no_workspaces(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/workspaces')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_list_returns_only_the_signed_in_users_workspaces_by_name_with_their_plan(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        $this->workspaceOf($user, ['name' => 'Zeta']);
        $this->workspaceOf($user, ['name' => 'Alpha']);
        $this->workspaceOf($other, ['name' => 'Not Mine']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/workspaces')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'package']]])
            ->assertJsonPath('data.0.package', ['key' => 'starter', 'name' => 'Billing Starter']);

        $this->assertSame(['Alpha', 'Zeta'], array_column($response->json('data'), 'name'));
    }

    /* ---------------------------------------------------------- create */

    public function test_store_creates_a_workspace_for_the_signed_in_user_on_billing_starter(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/workspaces', $this->payload())
            ->assertCreated()
            ->assertJsonPath('message', 'Workspace added successfully.')
            ->assertJsonPath('data.name', 'Main Workspace')
            ->assertJsonPath('data.package', ['key' => 'starter', 'name' => 'Billing Starter'])
            ->assertJsonMissingPath('data.app_id')
            ->assertJsonStructure(['data' => ['id']]);

        $this->assertDatabaseHas('workspaces', ['user_id' => $user->id, 'name' => 'Main Workspace', 'package_id' => $this->starter->id]);
    }

    public function test_a_new_workspace_starts_with_its_plans_lite_apps_on(): void
    {
        $this->sales->packages()->attach($this->starter);
        Sanctum::actingAs(User::factory()->create());

        $id = $this->postJson('/api/v1/workspaces', $this->payload())->assertCreated()->json('data.id');

        $this->assertDatabaseHas('workspace_apps', ['workspace_id' => $id, 'family_key' => 'sales', 'app_id' => $this->sales->id, 'status' => 'active']);
    }

    public function test_a_new_workspace_starts_with_a_main_location_and_its_owner_on_staff(): void
    {
        $user = User::factory()->create(['first_name' => 'Amina', 'last_name' => 'Rahman', 'email' => 'amina@example.com']);
        Sanctum::actingAs($user);

        // As registration starts the first business.
        $workspace = Workspace::findOrFail($this->postJson('/api/v1/workspaces', $this->payload())->assertCreated()->json('data.id'));

        $location = $workspace->locations()->sole();
        $this->assertSame(['Main Location', null, null, $user->id], [$location->name, $location->country, $location->state, $location->user_id]);

        $staff = $workspace->staff()->sole();
        $this->assertSame('Amina Rahman', $staff->name);
        $this->assertSame('amina@example.com', $staff->email);
        $this->assertSame(1, $staff->status);
        $this->assertSame([$workspace->id], $staff->assigned_workspaces);
        $this->assertSame($user->id, $staff->user_id);
    }

    public function test_each_new_workspace_gets_its_own_and_the_others_keep_theirs(): void
    {
        $user  = User::factory()->create();
        $first = $this->workspaceOf($user, ['name' => 'First Business']);
        $first->locations()->create(['user_id' => $user->id, 'name' => 'Head Office']);
        Sanctum::actingAs($user);

        $second = $this->postJson('/api/v1/workspaces', $this->payload(['name' => 'Second Business']))->json('data.id');

        // What each business's own pages list.
        $this->getJson('/api/v1/locations', [ResolveWorkspace::HEADER => (string) $second])->assertJsonPath('data.*.name', ['Main Location']);
        $this->getJson('/api/v1/user-staff', [ResolveWorkspace::HEADER => (string) $second])->assertJsonPath('data.*.email', [$user->email]);
        $this->getJson('/api/v1/locations', [ResolveWorkspace::HEADER => (string) $first->id])->assertJsonPath('data.*.name', ['Head Office']);
        $this->getJson('/api/v1/user-staff', [ResolveWorkspace::HEADER => (string) $first->id])->assertJsonPath('data', []);
    }

    public function test_apps_and_plans_are_not_set_from_here(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // A business turns apps on in the Apps Center, and its plan is not the caller's to choose.
        $premium = Package::where('key', 'premium')->firstOrFail();

        $this->postJson('/api/v1/workspaces', $this->payload(['app_id' => $this->sales->id, 'package_id' => $premium->id, 'nav_version' => 99]))
            ->assertCreated()
            ->assertJsonPath('data.package.key', 'starter');

        $workspace = $user->workspaces()->sole();
        $this->assertSame($this->starter->id, $workspace->package_id);
        $this->assertSame(0, $workspace->workspaceApps()->count());
        $this->assertFalse(Schema::hasColumn('workspaces', 'app_id'));
    }

    public function test_an_account_can_have_several_workspaces_with_the_same_name(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/workspaces', $this->payload())->assertCreated();
        $this->postJson('/api/v1/workspaces', $this->payload())->assertCreated();

        $this->assertSame(2, $user->workspaces()->count());
    }

    public function test_a_user_id_in_the_request_cannot_give_the_workspace_to_someone_else(): void
    {
        $victim   = User::factory()->create();
        $attacker = User::factory()->create();
        Sanctum::actingAs($attacker);

        $this->postJson('/api/v1/workspaces', $this->payload(['user_id' => $victim->id]))->assertCreated();

        $this->assertSame(0, $victim->workspaces()->count());
        $this->assertSame(1, $attacker->workspaces()->count());
    }

    public function test_a_staff_id_sent_with_a_new_workspace_is_ignored(): void
    {
        $user  = User::factory()->create();
        $staff = $this->workspaceOf($user, ['name' => 'First Business'])->staff()->create([
            'user_id' => $user->id, 'name' => 'Rahim Uddin', 'email' => 'rahim@example.com', 'phone' => '+8801700000000', 'status' => 1,
        ]);
        Sanctum::actingAs($user);

        // Which staff work in which workspace is set from the staff side, so a
        // staff_id sent here is not a field of the workspace: it is ignored.
        $response = $this->postJson('/api/v1/workspaces', $this->payload(['staff_id' => $staff->id]))
            ->assertCreated()
            ->assertJsonMissingPath('data.staff_id')
            ->assertJsonMissingPath('data.staff');

        $this->assertStringNotContainsString('Rahim', $response->getContent());
        $this->assertFalse(Schema::hasColumn('workspaces', 'staff_id'));

        $this->getJson('/api/v1/workspaces')
            ->assertOk()
            ->assertJsonMissingPath('data.0.staff_id')
            ->assertJsonMissingPath('data.0.staff');
        $this->assertSame([], $staff->fresh()->assigned_workspaces ?? []);
    }

    public function test_the_name_is_required_and_limited(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/workspaces', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->postJson('/api/v1/workspaces', ['name' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('workspaces', 0);
    }

    public function test_a_name_of_only_spaces_counts_as_empty(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/workspaces', ['name' => '    '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_the_longest_name_is_accepted_and_its_log_entry_still_fits(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/workspaces', ['name' => str_repeat('a', 255)])->assertCreated();

        // activity_logs.description is 255 characters wide.
        $this->assertLessThanOrEqual(255, strlen(ActivityLog::where('model_type', Workspace::class)->latest('id')->value('description')));
    }

    /* ---------------------------------------------------------- update */

    public function test_update_changes_the_name(): void
    {
        $user      = User::factory()->create();
        $workspace = $this->workspaceOf($user);
        Sanctum::actingAs($user);

        $this->putJson("/api/v1/workspaces/{$workspace->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('message', 'Workspace updated successfully.')
            ->assertJsonPath('data.id', $workspace->id)
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.package.key', 'starter');

        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id, 'name' => 'Renamed']);
        $this->assertDatabaseCount('workspaces', 1);
    }

    public function test_update_is_validated_like_a_create(): void
    {
        $user      = User::factory()->create();
        $workspace = $this->workspaceOf($user);
        Sanctum::actingAs($user);

        $this->putJson("/api/v1/workspaces/{$workspace->id}", ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id, 'name' => 'Main Workspace']);
    }

    public function test_an_update_leaves_the_plan_and_the_apps_as_they_were(): void
    {
        $user      = User::factory()->create();
        $workspace = $this->workspaceOf($user);
        $premium   = Package::where('key', 'premium')->firstOrFail();
        $workspace->package()->associate($premium)->save();
        Sanctum::actingAs($user);

        $this->putJson("/api/v1/workspaces/{$workspace->id}", ['name' => 'Renamed', 'package_id' => $this->starter->id])->assertOk();

        $fresh = $workspace->fresh();

        $this->assertSame('Renamed', $fresh->name);
        $this->assertSame($premium->id, $fresh->package_id);
    }

    public function test_a_user_id_in_an_update_cannot_move_the_workspace(): void
    {
        $owner     = User::factory()->create();
        $other     = User::factory()->create();
        $workspace = $this->workspaceOf($owner);
        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/workspaces/{$workspace->id}", $this->payload(['name' => 'Renamed', 'user_id' => $other->id]))->assertOk();

        $fresh = $workspace->fresh();

        $this->assertSame('Renamed', $fresh->name);
        $this->assertSame($owner->id, $fresh->user_id);
    }

    public function test_someone_elses_workspace_cannot_be_updated(): void
    {
        $owner     = User::factory()->create();
        $workspace = $this->workspaceOf($owner);
        Sanctum::actingAs(User::factory()->create());

        $this->putJson("/api/v1/workspaces/{$workspace->id}", $this->payload(['name' => 'Hijacked']))
            ->assertNotFound()
            ->assertExactJson(['message' => 'Workspace not found.']);

        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id, 'name' => 'Main Workspace', 'user_id' => $owner->id]);
    }

    public function test_updating_a_workspace_that_does_not_exist_is_a_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/v1/workspaces/999999', $this->payload())
            ->assertNotFound()
            ->assertExactJson(['message' => 'Workspace not found.']);
    }

    /* ---------------------------------------------------------- delete */

    public function test_destroy_removes_the_workspace_and_only_that(): void
    {
        $user      = User::factory()->create();
        $workspace = $this->workspaceOf($user);
        $kept      = $this->workspaceOf($user, ['name' => 'Keep Me']);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}")
            ->assertOk()
            ->assertExactJson(['message' => 'Workspace deleted successfully.']);

        $this->assertDatabaseMissing('workspaces', ['id' => $workspace->id]);
        $this->assertDatabaseHas('workspaces', ['id' => $kept->id]);

        // The app it pointed at is not the workspace's to take down with it.
        $this->assertDatabaseHas('apps', ['id' => $this->sales->id]);
    }

    public function test_deleting_a_business_deletes_its_own_data_and_leaves_the_others(): void
    {
        Storage::fake('public');

        $user    = User::factory()->create();
        $leaving = $this->workspaceOf($user, ['name' => 'Leaving']);
        $staying = $this->workspaceOf($user, ['name' => 'Staying']);

        foreach ([$leaving, $staying] as $business) {
            $logo = "business-logos/{$business->id}.png";
            Storage::disk('public')->put($logo, 'logo');

            $business->files()->create([
                'workspace_id' => $business->id,
                'collection'   => 'logo',
                'disk'         => 'public',
                'path'         => $logo,
                'visibility'   => 'public',
                'name'         => 'logo.png',
                'size'         => 4,
            ]);
            $business->locations()->create(['user_id' => $user->id, 'name' => "{$business->name} Depot"]);
            $business->staff()->create(['user_id' => $user->id, 'name' => "{$business->name} Staff", 'status' => 1]);
            $business->businessSetting()->create([
                'user_id'                    => $user->id,
                'business_name'              => $business->name,
                'currency'                   => 'USD',
                'language'                   => 'en',
                'country'                    => 'Bangladesh',
                'state'                      => 'Dhaka',
                'city'                       => 'Dhaka',
                'zip_code'                   => '1207',
                'landmark'                   => 'Near City Center',
                'timezone'                   => 'Asia/Dhaka',
                'financial_year_start_month' => 7,
                'stock_accounting_method'    => 'fifo',
            ]);
        }

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/workspaces/{$leaving->id}")->assertOk();

        foreach (['locations', 'user_staff', 'business_settings', 'workspace_files'] as $table) {
            $this->assertDatabaseMissing($table, ['workspace_id' => $leaving->id]);
            $this->assertDatabaseHas($table, ['workspace_id' => $staying->id]);
        }

        Storage::disk('public')->assertMissing("business-logos/{$leaving->id}.png");
        Storage::disk('public')->assertExists("business-logos/{$staying->id}.png");
    }

    public function test_someone_elses_workspace_cannot_be_deleted(): void
    {
        $owner     = User::factory()->create();
        $workspace = $this->workspaceOf($owner);
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}")
            ->assertNotFound()
            ->assertExactJson(['message' => 'Workspace not found.']);

        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id]);
    }

    public function test_deleting_the_same_workspace_twice_is_a_404(): void
    {
        $user      = User::factory()->create();
        $workspace = $this->workspaceOf($user);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/workspaces/{$workspace->id}")->assertOk();
        $this->deleteJson("/api/v1/workspaces/{$workspace->id}")->assertNotFound();
    }

    public function test_an_id_that_is_not_a_number_is_a_plain_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/v1/workspaces/abc', $this->payload())->assertNotFound();
        $this->deleteJson('/api/v1/workspaces/abc')->assertNotFound();
    }

    /* ---------------------------------------------------------- activity log */

    public function test_each_write_is_logged_once_by_name(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/v1/workspaces', $this->payload())->json('data.id');
        $this->putJson("/api/v1/workspaces/{$id}", $this->payload(['name' => 'Renamed']))->assertOk();
        $this->deleteJson("/api/v1/workspaces/{$id}")->assertOk();

        // One descriptive entry per write, and no generic `api_auto` one on top.
        // Adding the business also adds its Main Location and its owner as
        // staff, and those are logged as registration logs them.
        $logs = ActivityLog::where('user_id', $user->id)->orderBy('id')->get();

        $this->assertCount(5, $logs);
        $this->assertSame(['created', 'Added workspace "Main Workspace"'], [$logs[0]->action, $logs[0]->description]);
        $this->assertSame(['created', 'Added location "Main Location"'], [$logs[1]->action, $logs[1]->description]);
        $this->assertSame(['created', "Added staff member \"{$user->name}\""], [$logs[2]->action, $logs[2]->description]);
        $this->assertSame(['api', 'api', 'api'], $logs->take(3)->pluck('properties.source')->all());
        $this->assertSame(['updated', 'Updated workspace "Renamed"'], [$logs[3]->action, $logs[3]->description]);
        $this->assertSame(['name'], $logs[3]->properties['fields']);
        $this->assertSame(['deleted', 'Deleted workspace "Renamed"'], [$logs[4]->action, $logs[4]->description]);
    }
}
