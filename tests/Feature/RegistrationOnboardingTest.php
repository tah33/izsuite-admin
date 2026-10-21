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

    public function test_a_new_account_starts_with_its_workspace_location_and_owner_as_staff(): void
    {
        $user = $this->register();

        $workspace = $user->workspaces()->sole();
        $this->assertSame('Billing Starter', $workspace->name);
        $this->assertNull($workspace->app_id);

        $location = $user->locations()->sole();
        $this->assertSame('Main Location', $location->name);
        $this->assertNull($location->country);
        $this->assertNull($location->state);

        $staff = $user->userStaff()->sole();
        $this->assertSame('Amina Rahman', $staff->name);
        $this->assertSame('amina@example.com', $staff->email);
        $this->assertSame(1, $staff->status);
        $this->assertSame([$workspace->id], $staff->assigned_workspaces);
    }

    public function test_the_setup_is_queued_not_done_during_the_request(): void
    {
        Bus::fake();

        $user = $this->register();

        Bus::assertDispatched(CreateMainLocation::class, fn (CreateMainLocation $job) => $job->userId === $user->id);
        Bus::assertChained([CreateStarterWorkspace::class, AddOwnerAsStaff::class]);

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

    public function test_the_owner_is_still_added_when_the_starter_workspace_is_gone(): void
    {
        $user = User::factory()->create(['first_name' => 'Amina', 'last_name' => 'Rahman']);

        AddOwnerAsStaff::dispatchSync($user->id);

        $staff = $user->userStaff()->sole();
        $this->assertSame('Amina Rahman', $staff->name);
        $this->assertEmpty($staff->assigned_workspaces);
    }
}
