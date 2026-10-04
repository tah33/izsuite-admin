<?php

namespace Tests\Feature;

use App\Models\Admin\Role;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use App\Models\User\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkspacesApiTest extends TestCase
{
    use RefreshDatabase;

    private Application $sales;

    private Application $purchases;

    private Application $retired;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sales     = Application::create(['name' => 'Sales Lite', 'category' => 'Finance', 'is_active' => true]);
        $this->purchases = Application::create(['name' => 'Purchases Lite', 'category' => 'Finance', 'is_active' => true]);
        $this->retired   = Application::create(['name' => 'Retired App', 'category' => 'Finance', 'is_active' => false]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['name' => 'Main Workspace', 'app_id' => $this->sales->id], $overrides);
    }

    private function workspaceOf(User $user, array $overrides = []): Workspace
    {
        return $user->workspaces()->create($this->payload($overrides));
    }

    private function staffUser(array $overrides = []): User
    {
        $role = Role::firstOrCreate(['slug' => 'staff'], ['name' => 'Staff', 'permissions' => null]);

        return User::factory()->create(array_merge(
            ['role_id' => $role->id, 'status' => 'active', 'first_name' => 'Rahim', 'last_name' => 'Uddin'],
            $overrides,
        ));
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

    public function test_list_returns_only_the_signed_in_users_workspaces_by_name_with_their_app(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        $this->workspaceOf($user, ['name' => 'Zeta', 'app_id' => $this->sales->id]);
        $this->workspaceOf($user, ['name' => 'Alpha', 'app_id' => null]);
        $this->workspaceOf($other, ['name' => 'Not Mine']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/workspaces')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'app_id', 'app']]])
            ->assertJsonPath('data.0.app', null)
            ->assertJsonPath('data.1.app', ['id' => $this->sales->id, 'name' => 'Sales Lite']);

        $this->assertSame(['Alpha', 'Zeta'], array_column($response->json('data'), 'name'));
    }

    public function test_the_list_carries_each_staff_member_by_name_and_never_the_owner(): void
    {
        $user  = User::factory()->create();
        $staff = $this->staffUser(['email' => 'rahim@izsuite.test', 'phone' => '+8801700000000']);

        $this->workspaceOf($user, ['name' => 'Alpha', 'staff_id' => $staff->id]);
        $this->workspaceOf($user, ['name' => 'Beta']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/workspaces')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'app_id', 'staff_id', 'app', 'staff']]])
            ->assertJsonPath('data.0.staff_id', $staff->id)
            ->assertJsonPath('data.0.staff', ['id' => $staff->id, 'name' => 'Rahim Uddin'])
            ->assertJsonPath('data.1.staff_id', null)
            ->assertJsonPath('data.1.staff', null)
            ->assertJsonMissingPath('data.0.user_id');

        // Only the name of a staff member goes out - nothing else on the account.
        $this->assertStringNotContainsString('rahim@izsuite.test', $response->getContent());
        $this->assertStringNotContainsString('+8801700000000', $response->getContent());
    }

    /* ---------------------------------------------------------- create */

    public function test_store_creates_a_workspace_for_the_signed_in_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/workspaces', $this->payload())
            ->assertCreated()
            ->assertJsonPath('message', 'Workspace added successfully.')
            ->assertJsonPath('data.name', 'Main Workspace')
            ->assertJsonPath('data.app_id', $this->sales->id)
            ->assertJsonPath('data.app', ['id' => $this->sales->id, 'name' => 'Sales Lite'])
            ->assertJsonStructure(['data' => ['id']]);

        $this->assertDatabaseHas('workspaces', ['user_id' => $user->id, 'name' => 'Main Workspace', 'app_id' => $this->sales->id]);
    }

    public function test_an_app_is_optional(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/workspaces', ['name' => 'No App Yet'])
            ->assertCreated()
            ->assertJsonPath('data.app_id', null)
            ->assertJsonPath('data.app', null);

        $this->postJson('/api/v1/workspaces', ['name' => 'Explicit Null', 'app_id' => null])->assertCreated();

        $this->assertSame(2, $user->workspaces()->whereNull('app_id')->count());
    }

    public function test_an_account_can_have_several_workspaces_with_the_same_name(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/workspaces', $this->payload())->assertCreated();
        $this->postJson('/api/v1/workspaces', $this->payload(['app_id' => $this->purchases->id]))->assertCreated();

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

    public function test_store_can_attach_a_staff_member(): void
    {
        $user  = User::factory()->create();
        $staff = $this->staffUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/workspaces', $this->payload(['staff_id' => $staff->id]))
            ->assertCreated()
            ->assertJsonPath('data.staff_id', $staff->id)
            ->assertJsonPath('data.staff', ['id' => $staff->id, 'name' => 'Rahim Uddin']);

        $this->assertDatabaseHas('workspaces', ['user_id' => $user->id, 'name' => 'Main Workspace', 'staff_id' => $staff->id]);
    }

    public function test_the_staff_member_is_optional(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/workspaces', ['name' => 'No Staff Yet'])
            ->assertCreated()
            ->assertJsonPath('data.staff_id', null)
            ->assertJsonPath('data.staff', null);

        $this->postJson('/api/v1/workspaces', ['name' => 'Explicit Null', 'staff_id' => null])->assertCreated();

        $this->assertSame(2, $user->workspaces()->whereNull('staff_id')->count());
    }

    public function test_the_staff_member_must_be_an_active_staff_account(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $customer    = User::factory()->create();
        $admin       = User::factory()->create(['role_id' => Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin', 'permissions' => null])->id]);
        $switchedOff = $this->staffUser(['status' => 'inactive']);

        // A customer, an admin, a staff account an admin has switched off, and
        // one that does not exist: none of them is on offer.
        foreach ([$customer->id, $admin->id, $switchedOff->id, 999999] as $id) {
            $this->postJson('/api/v1/workspaces', $this->payload(['staff_id' => $id]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['staff_id' => 'available staff']);
        }

        $this->postJson('/api/v1/workspaces', $this->payload(['staff_id' => 'rahim']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('staff_id');

        $this->assertDatabaseCount('workspaces', 0);
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

    public function test_the_app_must_be_an_existing_active_one(): void
    {
        Sanctum::actingAs(User::factory()->create());

        // An app an admin has switched off, one that does not exist, and a non-id.
        foreach ([$this->retired->id, 999999] as $id) {
            $this->postJson('/api/v1/workspaces', $this->payload(['app_id' => $id]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['app_id' => 'available apps']);
        }

        $this->postJson('/api/v1/workspaces', $this->payload(['app_id' => 'sales']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('app_id');

        $this->assertDatabaseCount('workspaces', 0);
    }

    public function test_the_longest_name_is_accepted_and_its_log_entry_still_fits(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/workspaces', ['name' => str_repeat('a', 255)])->assertCreated();

        // activity_logs.description is 255 characters wide.
        $this->assertLessThanOrEqual(255, strlen(ActivityLog::latest('id')->value('description')));
    }

    /* ---------------------------------------------------------- update */

    public function test_update_changes_the_name_and_the_app(): void
    {
        $user      = User::factory()->create();
        $workspace = $this->workspaceOf($user);
        Sanctum::actingAs($user);

        $this->putJson("/api/v1/workspaces/{$workspace->id}", ['name' => 'Renamed', 'app_id' => $this->purchases->id])
            ->assertOk()
            ->assertJsonPath('message', 'Workspace updated successfully.')
            ->assertJsonPath('data.id', $workspace->id)
            ->assertJsonPath('data.name', 'Renamed')
            // the app in the answer is the new one, not the one it was loaded with
            ->assertJsonPath('data.app', ['id' => $this->purchases->id, 'name' => 'Purchases Lite']);

        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id, 'name' => 'Renamed', 'app_id' => $this->purchases->id]);
        $this->assertDatabaseCount('workspaces', 1);
    }

    public function test_update_can_take_the_workspace_off_its_app(): void
    {
        $user      = User::factory()->create();
        $workspace = $this->workspaceOf($user);
        Sanctum::actingAs($user);

        $this->putJson("/api/v1/workspaces/{$workspace->id}", ['name' => 'Main Workspace', 'app_id' => null])
            ->assertOk()
            ->assertJsonPath('data.app_id', null)
            ->assertJsonPath('data.app', null);

        $this->assertNull($workspace->fresh()->app_id);
    }

    public function test_update_is_validated_like_a_create(): void
    {
        $user      = User::factory()->create();
        $workspace = $this->workspaceOf($user);
        Sanctum::actingAs($user);

        $this->putJson("/api/v1/workspaces/{$workspace->id}", ['name' => '', 'app_id' => $this->purchases->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseHas('workspaces', ['id' => $workspace->id, 'name' => 'Main Workspace', 'app_id' => $this->sales->id]);
    }

    public function test_update_can_change_or_clear_the_staff_member(): void
    {
        $user      = User::factory()->create();
        $first     = $this->staffUser();
        $other     = $this->staffUser(['first_name' => 'Karim', 'last_name' => 'Hossain']);
        $workspace = $this->workspaceOf($user, ['staff_id' => $first->id]);
        Sanctum::actingAs($user);

        // the staff member in the answer is the new one, not the one it was loaded with
        $this->putJson("/api/v1/workspaces/{$workspace->id}", $this->payload(['staff_id' => $other->id]))
            ->assertOk()
            ->assertJsonPath('data.staff', ['id' => $other->id, 'name' => 'Karim Hossain']);

        $this->putJson("/api/v1/workspaces/{$workspace->id}", $this->payload(['staff_id' => null]))
            ->assertOk()
            ->assertJsonPath('data.staff_id', null)
            ->assertJsonPath('data.staff', null);

        $this->assertNull($workspace->fresh()->staff_id);
    }

    public function test_a_field_left_out_of_an_update_is_left_as_it_was(): void
    {
        $user      = User::factory()->create();
        $staff     = $this->staffUser();
        $workspace = $this->workspaceOf($user, ['staff_id' => $staff->id]);
        Sanctum::actingAs($user);

        $this->putJson("/api/v1/workspaces/{$workspace->id}", ['name' => 'Renamed'])->assertOk();

        $fresh = $workspace->fresh();

        $this->assertSame('Renamed', $fresh->name);
        $this->assertSame($this->sales->id, $fresh->app_id);
        $this->assertSame($staff->id, $fresh->staff_id);
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
        $logs = ActivityLog::where('user_id', $user->id)->orderBy('id')->get();

        $this->assertCount(3, $logs);
        $this->assertSame(['created', 'Added workspace "Main Workspace"'], [$logs[0]->action, $logs[0]->description]);
        $this->assertSame(['updated', 'Updated workspace "Renamed"'], [$logs[1]->action, $logs[1]->description]);
        $this->assertSame(['name'], $logs[1]->properties['fields']);
        $this->assertSame(['deleted', 'Deleted workspace "Renamed"'], [$logs[2]->action, $logs[2]->description]);
    }
}
