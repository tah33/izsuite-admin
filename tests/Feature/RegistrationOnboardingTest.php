<?php

namespace Tests\Feature;

use App\Events\UserRegistered;
use App\Jobs\AddOwnerAsStaff;
use App\Jobs\CreateMainLocation;
use App\Jobs\CreateStarterWorkspace;
use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RegistrationOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Registration gives every account the "user" role, so it has to exist.
        // Seeded with the others, super-admin keeps id 1 - a "user" role made
        // first would take that id, and the new account would pass for an
        // admin, whose activity is not logged.
        $this->seed(RoleSeeder::class);
    }

    private function register(): User
    {
        $this->postJson('/api/v1/auth/register', [
            'first_name'            => 'Amina',
            'last_name'             => 'Rahman',
            'username'              => 'amina',
            'email'                 => 'amina@example.com',
            'password'              => 'Secret#123',
            'password_confirmation' => 'Secret#123',
            'accept_terms'          => true,
        ])->assertCreated();

        return User::where('email', 'amina@example.com')->firstOrFail();
    }

    public function test_registering_announces_the_new_account_once(): void
    {
        Event::fake([UserRegistered::class]);

        $user = $this->register();

        Event::assertDispatchedTimes(UserRegistered::class, 1);
        Event::assertDispatched(UserRegistered::class, fn (UserRegistered $event) => $event->userId === $user->id);
    }

    public function test_a_refused_registration_or_an_account_made_any_other_way_announces_nothing(): void
    {
        Event::fake([UserRegistered::class]);

        $this->postJson('/api/v1/auth/register', ['email' => 'not-an-email'])->assertUnprocessable();
        User::factory()->create();

        Event::assertNotDispatched(UserRegistered::class);
    }

    public function test_a_new_account_starts_with_its_workspace_location_and_owner_as_staff(): void
    {
        $user = $this->register();

        $workspace = $user->workspaces()->sole();
        $this->assertSame('Billing Starter', $workspace->name);
        $this->assertSame('starter', $workspace->package->key);

        // Both belong to that business, not just to the account.
        $location = $workspace->locations()->sole();
        $this->assertSame('Main Location', $location->name);
        $this->assertNull($location->country);
        $this->assertNull($location->state);
        $this->assertSame($user->id, $location->user_id);

        $staff = $workspace->staff()->sole();
        $this->assertSame('Amina Rahman', $staff->name);
        $this->assertSame('amina@example.com', $staff->email);
        $this->assertSame(1, $staff->status);
        $this->assertSame([$workspace->id], $staff->assigned_workspaces);
        $this->assertSame($user->id, $staff->user_id);
    }

    public function test_the_setup_is_queued_not_done_during_the_request(): void
    {
        Bus::fake();

        $user = $this->register();

        Bus::assertChained([CreateStarterWorkspace::class, CreateMainLocation::class, AddOwnerAsStaff::class]);

        $this->assertSame(0, $user->workspaces()->count());
        $this->assertSame(0, $user->locations()->count());
        $this->assertSame(0, $user->userStaff()->count());
    }

    public function test_running_the_setup_twice_still_leaves_one_of_each(): void
    {
        $user = $this->register();

        // As a retried job would.
        UserRegistered::dispatch($user->id);

        $workspace = $user->workspaces()->sole();
        $this->assertSame(1, $user->locations()->count());
        $this->assertSame([$workspace->id], $user->userStaff()->sole()->assigned_workspaces);
        $this->assertSame(3, ActivityLog::where('properties->source', 'registration')->count());
    }

    public function test_what_the_setup_makes_is_logged_for_the_account_after_its_registration(): void
    {
        $user = $this->register();

        $logs       = ActivityLog::where('user_id', $user->id)->orderBy('id')->get();
        $registered = $logs->firstWhere('description', 'Registered an izSuite account');
        $setup      = $logs->filter(fn (ActivityLog $log) => ($log->properties['source'] ?? null) === 'registration');

        $this->assertNotNull($registered);
        $this->assertEqualsCanonicalizing([
            'Added location "Main Location"',
            'Added workspace "Billing Starter"',
            'Added staff member "Amina Rahman"',
        ], $setup->pluck('description')->all());
        $this->assertTrue($setup->every(fn (ActivityLog $log) => $log->id > $registered->id));
    }

    public function test_an_account_deleted_before_the_setup_ran_gets_nothing(): void
    {
        CreateStarterWorkspace::dispatchSync(999);
        CreateMainLocation::dispatchSync(999);
        AddOwnerAsStaff::dispatchSync(999);

        $this->assertDatabaseCount('workspaces', 0);
        $this->assertDatabaseCount('locations', 0);
        $this->assertDatabaseCount('user_staff', 0);
    }

    public function test_a_renamed_starter_workspace_still_gets_its_location_and_owner(): void
    {
        $user      = User::factory()->create(['first_name' => 'Amina', 'last_name' => 'Rahman']);
        $workspace = $user->workspaces()->create(['name' => 'Amina Traders']);

        CreateMainLocation::dispatchSync($user->id);
        AddOwnerAsStaff::dispatchSync($user->id);

        $this->assertSame('Main Location', $workspace->locations()->sole()->name);
        $this->assertSame([$workspace->id], $workspace->staff()->sole()->assigned_workspaces);
    }

    public function test_with_no_workspace_left_there_is_nowhere_to_put_the_location_or_owner(): void
    {
        $user = User::factory()->create();

        CreateMainLocation::dispatchSync($user->id);
        AddOwnerAsStaff::dispatchSync($user->id);

        $this->assertDatabaseCount('locations', 0);
        $this->assertDatabaseCount('user_staff', 0);
    }

    public function test_a_long_name_is_cut_to_fit_the_staff_column(): void
    {
        // Each half may be 255 characters on its own; the staff name holds 255 in all.
        $user = User::factory()->create(['first_name' => str_repeat('a', 200), 'last_name' => str_repeat('b', 200)]);
        $user->workspaces()->create(['name' => 'Billing Starter']);

        AddOwnerAsStaff::dispatchSync($user->id);

        $this->assertSame(255, strlen($user->userStaff()->sole()->name));
    }
}
