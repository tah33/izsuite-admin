<?php

namespace Tests\Feature;

use App\Jobs\AddDefaultRoles;
use App\Models\Frontend\MenuItem;
use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceRole;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use App\Support\Permissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Roles (Doc 12 sections 10, 11 and 15) - Doc 12 Phase A: every business
 * starts with the default templates, its owner as Business Owner; it adds,
 * changes, copies, switches off and deletes roles, each holding what its
 * permissions need; Business Owner is protected, and a role someone holds
 * stays until they are given another.
 */
class RolesApiTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);

        app(SetUpNewWorkspace::class)->addRoles($this->business, $this->user, 'api');
    }

    private function roles(): array
    {
        return collect($this->getJson('/api/v1/roles')->assertOk()->json('data'))->keyBy('name')->all();
    }

    private function role(string $name): WorkspaceRole
    {
        return $this->business->roles()->where('name', $name)->sole();
    }

    public function test_a_business_starts_with_the_templates_and_its_owner_as_business_owner(): void
    {
        $roles = $this->roles();

        $this->assertSame(['Business Owner', 'Administrator', 'Manager', 'Sales User', 'Purchase User', 'Expense User', 'Finance User', 'View Only'], array_keys($roles));
        $this->assertSame(['id', 'name', 'description', 'kind', 'full_access', 'is_active', 'members'], array_keys($roles['Manager']));
        $this->assertSame(['owner', true, 1], [$roles['Business Owner']['kind'], $roles['Business Owner']['full_access'], $roles['Business Owner']['members']]);
        $this->assertSame(['template', true, 0], [$roles['Administrator']['kind'], $roles['Administrator']['full_access'], $roles['Administrator']['members']]);

        // Only Sales is on: the templates hold nothing of Purchases or Expenses.
        $this->assertContains('sales.invoices.issue', $this->role('Sales User')->permissions);
        $this->assertSame([], array_filter($this->role('Manager')->permissions, fn (string $key) => str_starts_with($key, 'purchases.')));
        $this->assertSame(1, ActivityLog::where('description', 'Added the default roles')->count());

        // Run twice, nothing is added twice.
        app(SetUpNewWorkspace::class)->addRoles($this->business, $this->user, 'api');
        AddDefaultRoles::dispatchSync($this->user->id);

        $this->assertSame(8, $this->business->roles()->count());
        $this->assertSame(1, $this->business->members()->count());
    }

    public function test_a_role_shows_the_groups_of_the_apps_that_are_on(): void
    {
        $groups = collect($this->getJson('/api/v1/roles/permissions')->assertOk()->json('data'))->keyBy('key');

        $this->assertTrue($groups->has('sales.invoices'));
        $this->assertFalse($groups->has('purchases.purchases'));
        $this->assertSame(['key', 'label', 'family', 'permissions'], array_keys($groups['sales.invoices']));
        $this->assertSame(
            ['key' => 'sales.invoices.issue', 'label' => 'Issue', 'requires' => ['sales.invoices.view', 'sales.invoices.edit']],
            collect($groups['sales.invoices']['permissions'])->firstWhere('key', 'sales.invoices.issue'),
        );
    }

    public function test_a_new_role_holds_what_its_permissions_need(): void
    {
        $response = $this->postJson('/api/v1/roles', [
            'name' => 'Cashier', 'description' => 'Takes payments at the till.', 'permissions' => ['sales.payments.record'],
        ])->assertCreated();

        $response->assertJsonPath('message', 'Role "Cashier" added.')
            ->assertJsonPath('data.kind', 'custom')
            ->assertJsonPath('data.permissions', ['payment_accounts.view', 'sales.invoices.view', 'sales.payments.view', 'sales.payments.record']);

        $this->postJson('/api/v1/roles', ['name' => 'Cashier', 'permissions' => []])
            ->assertJsonValidationErrors(['name' => 'The business has a role of this name already.']);
        $this->postJson('/api/v1/roles', ['name' => 'Odd', 'permissions' => ['sales.invoices.fly']])
            ->assertJsonValidationErrors(['permissions.0' => 'That is not a permission a role can hold.']);

        // With full access it lists none: it holds them all.
        $this->postJson('/api/v1/roles', ['name' => 'Deputy', 'full_access' => true, 'permissions' => ['contacts.view']])
            ->assertCreated()->assertJsonPath('data.full_access', true)->assertJsonPath('data.permissions', []);
    }

    public function test_a_change_keeps_what_an_app_turned_off_holds_and_says_what_changed(): void
    {
        $role = $this->role('Sales User');
        $role->update(['permissions' => [...$role->permissions, 'purchases.purchases.view']]);
        $version = $this->business->fresh()->nav_version;

        $this->putJson("/api/v1/roles/{$role->id}", [
            'name' => 'Sales Desk', 'description' => $role->description, 'permissions' => ['sales.invoices.view', 'sales.invoices.void', 'contacts.view'],
        ])->assertOk()->assertJsonPath('message', 'Role "Sales Desk" saved.');

        $this->assertSame(['contacts.view', 'sales.invoices.view', 'sales.invoices.void', 'purchases.purchases.view'], $role->fresh()->permissions);
        $this->assertGreaterThan($version, $this->business->fresh()->nav_version);

        $changes = ActivityLog::where('description', 'Updated role "Sales Desk"')->sole()->properties['changes'];

        $this->assertSame(['setting' => 'name', 'from' => 'Sales User', 'to' => 'Sales Desk'], $changes[0]);
        $this->assertContains(['setting' => 'Invoices: Void', 'from' => false, 'to' => true], $changes);
        $this->assertContains(['setting' => 'Invoices: Issue', 'from' => true, 'to' => false], $changes);
        $this->assertNotContains('Purchases: View', array_column($changes, 'setting'));
    }

    public function test_business_owner_is_protected(): void
    {
        $owner = $this->role('Business Owner');
        $body  = ['name' => 'Boss', 'permissions' => []];

        $this->putJson("/api/v1/roles/{$owner->id}", $body)->assertStatus(409)->assertJsonPath('message', 'Business Owner is protected, so it cannot be changed.');
        $this->postJson("/api/v1/roles/{$owner->id}/deactivate")->assertStatus(409);
        $this->deleteJson("/api/v1/roles/{$owner->id}")->assertStatus(409);
        $this->postJson("/api/v1/roles/{$owner->id}/copy")->assertStatus(409);

        $this->assertSame(['Business Owner', true], [$owner->fresh()->name, $owner->fresh()->is_active]);
    }

    public function test_a_role_someone_holds_stays_and_only_the_business_own_roles_are_deleted(): void
    {
        $manager = $this->role('Manager');
        $staff   = User::factory()->create();
        $this->business->members()->create(['user_id' => $staff->id, 'role_id' => $manager->id]);

        $this->postJson("/api/v1/roles/{$manager->id}/deactivate")->assertStatus(409)
            ->assertJsonPath('message', '1 member holds this role. Give them another role before switching this one off.');

        $viewOnly = $this->role('View Only');
        $this->postJson("/api/v1/roles/{$viewOnly->id}/deactivate")->assertOk()->assertJsonPath('data.is_active', false);
        $this->postJson("/api/v1/roles/{$viewOnly->id}/activate")->assertOk()->assertJsonPath('data.is_active', true);
        $this->deleteJson("/api/v1/roles/{$viewOnly->id}")->assertStatus(409)->assertJsonPath('message', 'A default role cannot be deleted. Switch it off instead.');

        $copy = $this->postJson("/api/v1/roles/{$manager->id}/copy")->assertCreated()->assertJsonPath('data.name', 'Copy of Manager')->json('data');
        $this->postJson("/api/v1/roles/{$manager->id}/copy")->assertCreated()->assertJsonPath('data.name', 'Copy of Manager (2)');
        $this->assertSame($manager->permissions, WorkspaceRole::find($copy['id'])->permissions);

        $this->deleteJson("/api/v1/roles/{$copy['id']}")->assertOk()->assertJsonPath('message', 'Role deleted.');
        $this->assertNull(WorkspaceRole::find($copy['id']));
    }

    public function test_another_business_roles_are_not_found(): void
    {
        $other = User::factory()->create();
        $theirs = $this->businessOf($other);
        app(SetUpNewWorkspace::class)->addRoles($theirs, $other, 'api');

        $this->getJson('/api/v1/roles/'.$theirs->roles()->first()->id)->assertNotFound();
    }

    public function test_existing_businesses_get_the_templates_and_the_sidebar_its_permissions(): void
    {
        $migration = require database_path('migrations/2025_01_01_000104_create_workspace_roles_and_members.php');
        MenuItem::create(['section' => 'core', 'label' => 'Contacts', 'route' => '/contacts', 'sort_order' => 1, 'is_active' => true]);
        MenuItem::create(['section' => 'bottom', 'label' => 'Workspaces', 'route' => '/workspace', 'sort_order' => 2, 'is_active' => true]);

        $migration->down();
        $migration->up();

        $this->assertSame(8, DB::table('workspace_roles')->where('workspace_id', $this->business->id)->count());
        $this->assertSame(
            json_encode(Permissions::forTemplate('sales_user', ['sales'])),
            DB::table('workspace_roles')->where('workspace_id', $this->business->id)->where('template', 'sales_user')->value('permissions'),
        );
        $this->assertSame('Business Owner', $this->business->members()->sole()->role->name);
        $this->assertSame(['contacts.view', null], MenuItem::orderBy('sort_order')->pluck('permission_key')->all());
    }
}
