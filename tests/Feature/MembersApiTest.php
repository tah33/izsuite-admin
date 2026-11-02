<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\MenuItem;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * A business's users (Doc 12 sections 5, 7 to 9 and 20) - Doc 12 Phase B:
 * the list, changing a user's role and locations - never one's own - and
 * suspending, reactivating, removing and signing out; the Business Owner is
 * none of these until the business is handed over, by its owner, confirmed
 * by password. Staff kept before Users become invitations to send.
 */
class MembersApiTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $owner;

    private Workspace $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Mail::fake();

        $this->offerSalesLite();
        $this->owner    = $this->signIn(User::factory()->create(['password' => 'Owner!234']));
        $this->business = $this->businessOf($this->owner);
        $this->business->package()->associate(Package::where('key', 'standard')->sole())->save();

        app(SetUpNewWorkspace::class)->handle($this->business, $this->owner, 'api');
    }

    private function role(string $template): int
    {
        return $this->business->roles()->where('template', $template)->value('id');
    }

    /** An active user of the business holding $template's role. */
    private function member(string $template, array $fields = []): WorkspaceMember
    {
        $user = User::factory()->create($fields);

        return $this->business->members()->create([
            'user_id' => $user->id, 'role_id' => $this->role($template), 'name' => $user->name, 'email' => $user->email, 'status' => 'active',
        ]);
    }

    public function test_the_list_starts_with_the_owner(): void
    {
        $this->member('sales_user', ['first_name' => 'Rahim', 'last_name' => 'Uddin']);

        $rows = $this->getJson('/api/v1/members')->assertOk()->json('data');

        $this->assertSame([$this->owner->name, 'Rahim Uddin'], array_column($rows, 'name'));
        $this->assertSame(
            ['id', 'name', 'email', 'role_id', 'role', 'status', 'is_owner', 'is_self', 'location_access', 'location_ids', 'default_location_id', 'last_login_at', 'invitation'],
            array_keys($rows[0]),
        );
        $this->assertSame(['Business Owner', true, true, 'all'], [$rows[0]['role'], $rows[0]['is_owner'], $rows[0]['is_self'], $rows[0]['location_access']]);
        $this->assertSame(['Sales User', false, false], [$rows[1]['role'], $rows[1]['is_owner'], $rows[1]['is_self']]);
    }

    public function test_a_user_gets_a_role_and_locations_and_nobody_changes_their_own(): void
    {
        $branch = $this->business->locations()->create(['user_id' => $this->owner->id, 'name' => 'Branch', 'code' => 'BR', 'timezone' => 'Asia/Dhaka']);
        $rahim  = $this->member('sales_user');

        $this->putJson("/api/v1/members/{$rahim->id}", [
            'name' => 'Rahim Uddin', 'role_id' => $this->role('finance_user'), 'location_access' => 'selected', 'location_ids' => [$branch->id], 'job_title' => 'Cashier',
        ])->assertOk()->assertJsonPath('data.role', 'Finance User')->assertJsonPath('data.location_ids', [$branch->id])
            ->assertJsonPath('data.default_location_id', $branch->id)->assertJsonPath('data.job_title', 'Cashier');

        $this->putJson("/api/v1/members/{$rahim->id}", [
            'name' => 'Rahim Uddin', 'role_id' => $this->role('finance_user'), 'location_access' => 'selected', 'location_ids' => [$branch->id],
            'default_location_id' => $this->business->locations()->where('code', 'MAIN')->value('id'),
        ])->assertJsonValidationErrors(['default_location_id' => 'The default location must be one the user may use.']);

        // An Administrator may change others - not themselves.
        $admin = $this->member('administrator');
        Sanctum::actingAs($admin->user);
        $pick = $this->pick($this->business);

        $this->putJson("/api/v1/members/{$admin->id}", ['name' => 'Me', 'role_id' => $this->role('manager'), 'location_access' => 'all'], $pick)
            ->assertForbidden()->assertJsonPath('message', 'You cannot change your own role or locations.');
        $this->putJson("/api/v1/members/{$admin->id}", ['name' => 'Me Again', 'role_id' => $this->role('administrator'), 'location_access' => 'all'], $pick)
            ->assertOk()->assertJsonPath('data.name', 'Me Again');
    }

    public function test_suspended_users_are_shut_out_and_removed_ones_invited_again(): void
    {
        $rahim = $this->member('sales_user', ['email' => 'rahim@example.com']);

        $this->postJson("/api/v1/members/{$rahim->id}/suspend")->assertOk()->assertJsonPath('data.status', 'suspended');

        Sanctum::actingAs($rahim->user);
        $this->getJson('/api/v1/sales/invoices', $this->pick($this->business))->assertNotFound();
        Sanctum::actingAs($this->owner);

        $this->postJson("/api/v1/members/{$rahim->id}/reactivate")->assertOk()->assertJsonPath('data.status', 'active');
        $this->postJson("/api/v1/members/{$rahim->id}/remove")->assertOk()->assertJsonPath('data.status', 'removed');

        // Invited again, on the same row; the owner is none of these.
        $this->postJson('/api/v1/members', ['name' => 'Rahim', 'email' => 'rahim@example.com', 'role_id' => $this->role('view_only'), 'location_access' => 'all'])->assertCreated()
            ->assertJsonPath('data.id', $rahim->id)->assertJsonPath('data.status', 'pending');

        $owner = WorkspaceMember::where('user_id', $this->owner->id)->sole();
        $this->postJson("/api/v1/members/{$owner->id}/suspend")->assertStatus(409)
            ->assertJsonPath('message', 'The Business Owner cannot be suspended. Hand the business over to another user first.');
        $this->postJson("/api/v1/members/{$owner->id}/remove")->assertStatus(409);
    }

    public function test_signing_a_user_out_ends_every_session_of_theirs(): void
    {
        $rahim = $this->member('sales_user');
        $rahim->user->createToken('phone');
        $rahim->user->createToken('laptop');

        $this->postJson("/api/v1/members/{$rahim->id}/sign-out")->assertOk()->assertJsonPath('message', 'Signed out of 2 sessions.');
        $this->assertSame(0, $rahim->user->tokens()->count());

        $self = WorkspaceMember::where('user_id', $this->owner->id)->value('id');
        $this->postJson("/api/v1/members/{$self}/sign-out")->assertStatus(409);
    }

    public function test_the_owner_hands_the_business_over_with_their_password(): void
    {
        $rahim = $this->member('sales_user', ['first_name' => 'Rahim', 'last_name' => 'Uddin']);
        $admin = $this->member('administrator');

        Sanctum::actingAs($admin->user);
        $this->postJson("/api/v1/members/{$rahim->id}/transfer-ownership", ['password' => 'x'], $this->pick($this->business))->assertForbidden();

        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v1/members/{$rahim->id}/transfer-ownership", ['password' => 'wrong'])
            ->assertJsonValidationErrors(['password' => 'The password is not right.']);

        $this->postJson("/api/v1/members/{$rahim->id}/transfer-ownership", ['password' => 'Owner!234'])->assertOk()
            ->assertJsonPath('message', 'The business now belongs to "Rahim Uddin". You are an Administrator of it.')
            ->assertJsonPath('data.role', 'Business Owner');

        $this->assertSame($rahim->user_id, $this->business->fresh()->user_id);
        $this->assertSame([$rahim->user_id], $this->business->locations()->distinct()->pluck('user_id')->all());
        $this->assertSame('Administrator', WorkspaceMember::where('user_id', $this->owner->id)->sole()->role->name);

        // The previous owner is a user now: the business stays open to them, as Administrator.
        $this->getJson('/api/v1/roles', $this->pick($this->business))->assertOk();
        $this->postJson("/api/v1/members/{$rahim->id}/suspend", [], $this->pick($this->business))->assertStatus(409);
    }

    public function test_the_list_is_exported(): void
    {
        $this->member('sales_user', ['first_name' => 'Rahim', 'last_name' => 'Uddin', 'email' => 'rahim@example.com']);

        $csv = $this->get('/api/v1/members/export')->assertOk()->streamedContent();

        $this->assertStringContainsString("Name,Email,Role,Status,\"Location Access\",\"Default Location\",\"Last Login\"\n", $csv);
        $this->assertStringContainsString('"Rahim Uddin",rahim@example.com,"Sales User",Active,"All locations"', $csv);
    }

    public function test_staff_from_before_users_become_invitations_to_send(): void
    {
        $migration = require database_path('migrations/2025_01_01_000105_add_invitations_to_workspace_members.php');
        MenuItem::create(['section' => 'bottom', 'label' => 'Staff', 'icon' => 'user-check', 'route' => '/staff', 'sort_order' => 1]);

        $staff = fn (string $name, ?string $email) => $this->business->staff()->create(['user_id' => $this->owner->id, 'name' => $name, 'email' => $email, 'phone' => '+8801700000000', 'status' => 1]);
        $staff('Owner Self', strtoupper($this->owner->email));
        $staff('Rahim Uddin', 'Rahim@Example.com');
        $staff('No Mail', null);

        $migration->down();
        $migration->up();

        $pending = DB::table('workspace_members')->where('status', 'pending')->get();

        $this->assertSame(['Rahim Uddin'], $pending->pluck('name')->all());
        $this->assertSame(['rahim@example.com', null], [$pending[0]->email, $pending[0]->invitation_token]);
        $this->assertSame($this->role('view_only'), $pending[0]->role_id);
        $this->assertSame([$this->owner->email], DB::table('workspace_members')->where('status', 'active')->pluck('email')->all());
        $this->assertSame('Users', MenuItem::sole()->label);
        $this->getJson('/api/v1/members')->assertJsonPath('data.1.invitation', 'not_sent');
    }
}
