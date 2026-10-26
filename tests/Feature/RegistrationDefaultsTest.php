<?php

namespace Tests\Feature;

use App\Events\UserRegistered;
use App\Listeners\CreateDefaultLocation;
use App\Listeners\CreateDefaultWorkspace;
use App\Listeners\CreateOwnerStaffMember;
use App\Models\Admin\Role;
use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use App\Models\User\UserStaff;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RegistrationDefaultsTest extends TestCase
{
    use RefreshDatabase;

    private const LISTENERS = [CreateDefaultWorkspace::class, CreateDefaultLocation::class, CreateOwnerStaffMember::class];

    protected function setUp(): void
    {
        parent::setUp();

        // Registration gives every account this role, and refuses to run without it.
        Role::create(['name' => 'User', 'slug' => 'user']);
    }

    private function register(array $overrides = []): TestResponse
    {
        return $this->postJson('/api/v1/auth/register', array_merge([
            'prefix'                => 'Mr',
            'first_name'            => 'Rahim',
            'last_name'             => 'Uddin',
            'username'              => 'rahim',
            'email'                 => 'rahim@example.com',
            'password'              => 'Secret#123',
            'password_confirmation' => 'Secret#123',
            'accept_terms'          => true,
        ], $overrides));
    }

    private function registered(): User
    {
        return User::where('email', 'rahim@example.com')->sole();
    }

    /** Runs each listener the way the queue worker would, in the order given. */
    private function runListeners(int $userId, array $listeners = self::LISTENERS): void
    {
        foreach ($listeners as $listener) {
            app($listener)->handle(new UserRegistered($userId));
        }
    }

    /* ---------------------------------------------------------- the event */

    public function test_registering_announces_the_new_account_once(): void
    {
        Event::fake([UserRegistered::class]);

        $this->register()->assertCreated();

        Event::assertDispatchedTimes(UserRegistered::class, 1);
        Event::assertDispatched(UserRegistered::class, fn (UserRegistered $event) => $event->userId === $this->registered()->id);
    }

    public function test_a_refused_registration_announces_nothing(): void
    {
        Event::fake([UserRegistered::class]);

        $this->register(['email' => 'not-an-email'])->assertUnprocessable();

        Event::assertNotDispatched(UserRegistered::class);
    }

    public function test_registration_leaves_the_defaults_to_the_queue(): void
    {
        Queue::fake();

        $this->register()->assertCreated();

        $user = $this->registered();

        // One job per listener, each queued once, for this account...
        Queue::assertPushed(CallQueuedListener::class, count(self::LISTENERS));

        foreach (self::LISTENERS as $listener) {
            Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job) => $job->class === $listener
                && $job->data[0]->userId === $user->id);
        }

        // ...and nothing made during the request itself: that is what keeps
        // registration as quick as it was.
        $this->assertSame(0, $user->workspaces()->count());
        $this->assertSame(0, $user->locations()->count());
        $this->assertSame(0, $user->userStaff()->count());
    }

    /* ---------------------------------------------------------- what an account starts with */

    public function test_a_new_account_starts_with_its_workspace_location_and_owner_as_staff(): void
    {
        // The test queue runs jobs straight away, so they are done when this returns.
        $this->register()->assertCreated();

        $user = $this->registered();

        $workspace = $user->workspaces()->sole();
        $this->assertSame('Billing Starter', $workspace->name);
        $this->assertNull($workspace->app_id);

        $location = $user->locations()->sole();
        $this->assertSame('Main Location', $location->name);
        $this->assertNull($location->country);
        $this->assertNull($location->state);

        $staff = $user->userStaff()->sole();
        $this->assertSame('Rahim Uddin', $staff->name);
        $this->assertSame('rahim@example.com', $staff->email);
        $this->assertNull($staff->phone);
        $this->assertSame(UserStaff::STATUS_ACTIVE, $staff->status);
        $this->assertSame([$workspace->id], $staff->assigned_workspaces);
    }

    public function test_the_owner_is_assigned_to_the_workspace_whichever_job_runs_first(): void
    {
        foreach ([
            'workspace first' => [CreateDefaultWorkspace::class, CreateOwnerStaffMember::class],
            'staff first'     => [CreateOwnerStaffMember::class, CreateDefaultWorkspace::class],
        ] as $order => $listeners) {
            $user = User::factory()->create();

            $this->runListeners($user->id, $listeners);

            $this->assertSame([$user->workspaces()->sole()->id], $user->userStaff()->sole()->assigned_workspaces, $order);
        }
    }

    public function test_a_long_name_is_cut_to_fit_the_staff_column(): void
    {
        $user = User::factory()->create(['first_name' => str_repeat('a', 200), 'last_name' => str_repeat('b', 200)]);

        $this->runListeners($user->id, [CreateOwnerStaffMember::class]);

        $this->assertSame(255, strlen($user->userStaff()->sole()->name));
    }

    public function test_the_defaults_are_written_to_the_owners_activity_log(): void
    {
        $this->register()->assertCreated();

        $user = $this->registered();

        // The account itself first, then what it was given - in whatever order
        // the queue got to the three, which do not depend on one another.
        $descriptions = ActivityLog::where('user_id', $user->id)->orderBy('id')->pluck('description')->all();

        $this->assertSame('Registered an izSuite account', array_shift($descriptions));
        $this->assertEqualsCanonicalizing([
            'Added workspace "Billing Starter"',
            'Added location "Main Location"',
            'Added staff member "Rahim Uddin"',
        ], $descriptions);

        $this->assertSame(3, ActivityLog::where('user_id', $user->id)->where('properties->source', 'registration')->count());
    }

    /* ---------------------------------------------------------- safe to run again */

    public function test_a_job_run_again_makes_nothing_twice(): void
    {
        $user = User::factory()->create();

        // A retried job, or the event announced twice.
        $this->runListeners($user->id);
        $this->runListeners($user->id);

        $this->assertSame(1, $user->workspaces()->count());
        $this->assertSame(1, $user->locations()->count());
        $this->assertSame(1, $user->userStaff()->count());
        $this->assertSame([$user->workspaces()->sole()->id], $user->userStaff()->sole()->assigned_workspaces);
        $this->assertSame(3, ActivityLog::where('user_id', $user->id)->count());
    }

    public function test_an_account_deleted_before_the_jobs_ran_is_skipped(): void
    {
        $user = User::factory()->create();
        $id   = $user->id;
        $user->delete();

        $this->runListeners($id);

        $this->assertDatabaseCount('workspaces', 0);
        $this->assertDatabaseCount('locations', 0);
        $this->assertDatabaseCount('user_staff', 0);
    }

    public function test_an_account_made_any_other_way_is_given_nothing(): void
    {
        Event::fake([UserRegistered::class]);

        User::factory()->create();

        Event::assertNotDispatched(UserRegistered::class);
    }

    public function test_each_listener_is_queued_after_the_account_is_committed(): void
    {
        foreach (self::LISTENERS as $listener) {
            $this->assertInstanceOf(ShouldQueueAfterCommit::class, app($listener), $listener);
        }
    }
}
