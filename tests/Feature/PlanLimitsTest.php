<?php

namespace Tests\Feature;

use App\Models\Admin\Role;
use App\Models\Billing\Package;
use App\Models\User\User;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Billing Starter's limits (Doc 01 section 4): one business per account, one
 * user and one location per business - set per package in the admin, empty
 * being none. Adding past one is refused; nothing is taken away from a
 * business already over one (Doc 13).
 */
class PlanLimitsTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->user = $this->signIn();
    }

    public function test_billing_starter_gives_one_of_each_and_the_paid_plans_no_limits(): void
    {
        $this->assertSame(
            ['starter' => [1, 1, 1], 'standard' => [null, null, null], 'premium' => [null, null, null]],
            Package::orderBy('rank')->get()->mapWithKeys(fn (Package $package) => [$package->key => [$package->business_limit, $package->user_limit, $package->location_limit]])->all(),
        );
    }

    public function test_a_starter_business_has_one_location(): void
    {
        $place = fn (string $name, string $code) => ['name' => $name, 'code' => $code, 'country' => 'BD', 'timezone' => 'Asia/Dhaka'];

        $this->postJson('/api/v1/locations', $place('Main Location', 'MAIN'))->assertCreated();

        $this->postJson('/api/v1/locations', $place('Chattogram Branch', 'CTG'))
            ->assertForbidden()
            ->assertJsonPath('message', 'Billing Starter includes one location. Upgrade the plan to add another.');

        $this->getJson('/api/v1/business-settings/usage')->assertJsonPath('data.locations', ['used' => 1, 'limit' => 1, 'state' => 'full']);

        // On a plan without a limit it can grow.
        $this->businessOf($this->user)->package()->associate(Package::where('key', 'standard')->sole())->save();
        $this->postJson('/api/v1/locations', $place('Chattogram Branch', 'CTG'))->assertCreated();
        $this->getJson('/api/v1/business-settings/usage')->assertJsonPath('data.locations', ['used' => 2, 'limit' => null, 'state' => 'ok']);
    }

    public function test_a_starter_business_has_one_user_its_owner(): void
    {
        Mail::fake();

        $business = $this->businessOf($this->user);
        app(SetUpNewWorkspace::class)->addRoles($business, $this->user, 'api');

        // The owner is the one user.
        $this->getJson('/api/v1/business-settings/usage')->assertJsonPath('data.users', ['used' => 1, 'limit' => 1, 'state' => 'full']);

        $invite = fn (string $name, string $email) => [
            'name' => $name, 'email' => $email, 'role_id' => $business->roles()->where('template', 'view_only')->value('id'), 'location_access' => 'all',
        ];

        $this->postJson('/api/v1/members', $invite('Rahim Uddin', 'rahim@example.com'))
            ->assertForbidden()
            ->assertJsonPath('message', 'Billing Starter includes one user. Upgrade the plan to add users.');

        // Someone invited counts until the invitation is cancelled.
        Package::where('key', Package::STARTER)->update(['user_limit' => 2]);
        $this->postJson('/api/v1/members', $invite('Rahim Uddin', 'rahim@example.com'))->assertCreated();
        $this->postJson('/api/v1/members', $invite('Karim Ali', 'karim@example.com'))->assertForbidden();
        $this->getJson('/api/v1/business-settings/usage')->assertJsonPath('data.users', ['used' => 2, 'limit' => 2, 'state' => 'full']);
    }

    public function test_an_account_has_one_free_business_and_more_need_a_paid_plan(): void
    {
        $this->postJson('/api/v1/workspaces', ['name' => 'Second Business'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Your account already has its Billing Starter business. Another business needs a paid plan.');

        $this->assertSame(1, $this->user->workspaces()->count());

        // Once the first is on a paid plan, the account may start another on Billing Starter.
        $this->businessOf($this->user)->package()->associate(Package::where('key', 'standard')->sole())->save();
        $this->postJson('/api/v1/workspaces', ['name' => 'Second Business'])->assertCreated();
        $this->postJson('/api/v1/workspaces', ['name' => 'Third Business'])->assertForbidden();
    }

    public function test_the_admin_sets_each_limit_and_empty_is_none(): void
    {
        $admin   = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]);
        $package = Package::where('key', 'standard')->sole();
        $fields  = ['name' => $package->name, 'description' => $package->description, 'is_active' => 1];

        $this->actingAs($admin)->putJson(route('admin.packages.update', $package->id), [...$fields, 'business_limit' => 3, 'user_limit' => 5, 'location_limit' => ''])->assertOk();
        $this->assertSame([3, 5, null], [$package->fresh()->business_limit, $package->fresh()->user_limit, $package->fresh()->location_limit]);

        $this->actingAs($admin)->putJson(route('admin.packages.update', $package->id), [...$fields, 'user_limit' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_limit' => 'Enter a whole number of at least 1, or leave it empty for no limit.']);

        $this->actingAs($admin)->get(route('admin.packages.edit', $package->id))->assertOk()->assertSee('Users per business')->assertSee('Locations per business');
    }
}
