<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveWorkspace;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use App\Models\User\UserStaff;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserStaffApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded with the others, super-admin keeps id 1 - a "user" role made
        // first by the factory would take it, and every account would pass for
        // an admin, whose activity is not logged.
        $this->seed(RoleSeeder::class);

        // These are about adding staff, not about the plan's limit on users (see PlanLimitsTest).
        Package::where('key', Package::STARTER)->update(['user_limit' => null]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name'    => 'Rahim Uddin',
            'email'   => 'rahim@example.com',
            'phone'   => '+8801700000000',
            'address' => 'House 12, Road 5, Mirpur-11',
            'state'   => 'Dhaka',
            'country' => 'Bangladesh',
            'status'  => 1,
        ], $overrides);
    }

    /** The account's first business - the one the API answers for when none is picked. */
    private function businessOf(User $user): Workspace
    {
        return $user->workspaces()->orderBy('id')->first()
            ?? $user->workspaces()->create(['name' => 'Main Business']);
    }

    private function signIn(User $user): User
    {
        $this->businessOf($user);
        Sanctum::actingAs($user);

        return $user;
    }

    private function staffOf(User $user, array $overrides = []): UserStaff
    {
        return $this->businessOf($user)->staff()->create([...$this->payload($overrides), 'user_id' => $user->id]);
    }

    private function app(string $name, bool $active = true): Application
    {
        return Application::create(['name' => $name, 'category' => 'Finance', 'is_active' => $active]);
    }

    private function workspaceOf(User $user, string $name): Workspace
    {
        return $user->workspaces()->create(['name' => $name]);
    }

    /** The JSON a column actually holds, as a list. */
    private function stored(UserStaff $staff, string $column): ?array
    {
        $json = DB::table('user_staff')->where('id', $staff->id)->value($column);

        return $json === null ? null : json_decode($json, true);
    }

    /* ---------------------------------------------------------- auth */

    public function test_every_user_staff_endpoint_requires_a_token(): void
    {
        $owner = User::factory()->create();
        $staff = $this->staffOf($owner);

        $this->getJson('/api/v1/user-staff')->assertUnauthorized();
        $this->postJson('/api/v1/user-staff', $this->payload())->assertUnauthorized();
        $this->putJson("/api/v1/user-staff/{$staff->id}", $this->payload(['name' => 'Hijacked']))->assertUnauthorized();
        $this->deleteJson("/api/v1/user-staff/{$staff->id}")->assertUnauthorized();

        $this->assertDatabaseCount('user_staff', 1);
        $this->assertDatabaseHas('user_staff', ['id' => $staff->id, 'name' => 'Rahim Uddin']);
    }

    /* ---------------------------------------------------------- list */

    public function test_a_new_account_has_no_staff(): void
    {
        $this->signIn(User::factory()->create());

        $this->getJson('/api/v1/user-staff')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_list_returns_only_the_signed_in_users_staff_by_name(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        // Alpha is a row saved before the email and phone were required: it has neither.
        $this->staffOf($user, ['name' => 'Zeta']);
        $this->staffOf($user, ['name' => 'Alpha', 'email' => null, 'phone' => null, 'address' => null, 'state' => null, 'country' => null, 'status' => 0]);
        $this->staffOf($other, ['name' => 'Not Mine']);

        $this->signIn($user);

        $response = $this->getJson('/api/v1/user-staff')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'phone', 'address', 'state', 'country', 'status', 'assigned_apps', 'assigned_workspaces']]])
            ->assertJsonPath('data.0.name', 'Alpha')
            ->assertJsonPath('data.0.email', null)
            ->assertJsonPath('data.0.status', 0)
            ->assertJsonPath('data.1.name', 'Zeta')
            ->assertJsonPath('data.1.email', 'rahim@example.com')
            ->assertJsonPath('data.1.country', 'Bangladesh')
            ->assertJsonPath('data.1.status', 1)
            ->assertJsonMissingPath('data.0.user_id')
            ->assertJsonMissingPath('data.1.user_id');

        $this->assertStringNotContainsString('Not Mine', $response->getContent());
    }

    public function test_staff_sharing_a_name_come_back_in_a_stable_order(): void
    {
        $user   = User::factory()->create();
        $first  = $this->staffOf($user, ['name' => 'Same Name', 'email' => 'one@example.com']);
        $second = $this->staffOf($user, ['name' => 'Same Name', 'email' => 'two@example.com']);

        $this->signIn($user);

        $response = $this->getJson('/api/v1/user-staff')->assertOk();

        $this->assertSame([$first->id, $second->id], array_column($response->json('data'), 'id'));
    }

    /* ---------------------------------------------------------- create */

    public function test_store_creates_a_staff_member_for_the_signed_in_user(): void
    {
        $user = User::factory()->create();
        $this->signIn($user);

        $this->postJson('/api/v1/user-staff', $this->payload())
            ->assertCreated()
            ->assertJsonPath('message', 'Staff member added successfully.')
            ->assertJsonPath('data.name', 'Rahim Uddin')
            ->assertJsonPath('data.email', 'rahim@example.com')
            ->assertJsonPath('data.phone', '+8801700000000')
            ->assertJsonPath('data.address', 'House 12, Road 5, Mirpur-11')
            ->assertJsonPath('data.state', 'Dhaka')
            ->assertJsonPath('data.country', 'Bangladesh')
            ->assertJsonPath('data.status', 1)
            ->assertJsonPath('data.assigned_apps', [])
            ->assertJsonPath('data.assigned_workspaces', [])
            ->assertJsonStructure(['data' => ['id']])
            ->assertJsonMissingPath('data.user_id');

        $this->assertDatabaseHas('user_staff', array_merge(['user_id' => $user->id, 'workspace_id' => $this->businessOf($user)->id], $this->payload()));
    }

    public function test_the_signed_in_user_is_always_the_owner(): void
    {
        $first  = User::factory()->create();
        $second = User::factory()->create();

        $this->signIn($first);
        $this->postJson('/api/v1/user-staff', $this->payload(['name' => 'First Owner Staff']))->assertCreated();

        $this->signIn($second);
        $this->postJson('/api/v1/user-staff', $this->payload(['name' => 'Second Owner Staff']))->assertCreated();

        $this->assertDatabaseHas('user_staff', ['user_id' => $first->id, 'name' => 'First Owner Staff']);
        $this->assertDatabaseHas('user_staff', ['user_id' => $second->id, 'name' => 'Second Owner Staff']);
    }

    public function test_a_user_id_in_the_request_cannot_give_the_staff_member_to_someone_else(): void
    {
        $victim   = User::factory()->create();
        $attacker = User::factory()->create();
        $this->signIn($attacker);

        $this->postJson('/api/v1/user-staff', $this->payload(['user_id' => $victim->id]))->assertCreated();

        $this->assertSame(0, $victim->userStaff()->count());
        $this->assertSame(1, $attacker->userStaff()->count());
    }

    public function test_the_address_state_and_country_are_optional(): void
    {
        $user = User::factory()->create();
        $this->signIn($user);

        $this->postJson('/api/v1/user-staff', ['name' => 'Required Only', 'email' => 'required@example.com', 'phone' => '+8801700000001', 'status' => 1])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Required Only')
            ->assertJsonPath('data.email', 'required@example.com')
            ->assertJsonPath('data.phone', '+8801700000001')
            ->assertJsonPath('data.address', null)
            ->assertJsonPath('data.state', null)
            ->assertJsonPath('data.country', null)
            ->assertJsonPath('data.status', 1);

        $this->assertDatabaseHas('user_staff', [
            'user_id' => $user->id,
            'name'    => 'Required Only',
            'address' => null,
            'state'   => null,
            'country' => null,
            'status'  => 1,
        ]);
    }

    public function test_the_name_email_phone_and_status_are_each_required(): void
    {
        $this->signIn(User::factory()->create());

        foreach (['name', 'email', 'phone', 'status'] as $field) {
            $without = $this->payload();
            unset($without[$field]);

            // Left out, sent as null, sent empty, sent as spaces: each is the same - nothing there.
            foreach ([$without, $this->payload([$field => null]), $this->payload([$field => '']), $this->payload([$field => '   '])] as $body) {
                $this->postJson('/api/v1/user-staff', $body)
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors($field);
            }
        }

        $this->assertDatabaseCount('user_staff', 0);
    }

    public function test_blank_optional_fields_are_stored_as_null_not_as_empty_text(): void
    {
        $this->signIn(User::factory()->create());

        $this->postJson('/api/v1/user-staff', $this->payload([
            'name'    => 'Blanks',
            'address' => '',
            'state'   => ' ',
            'country' => '',
        ]))->assertCreated();

        $this->assertDatabaseHas('user_staff', [
            'name'    => 'Blanks',
            'address' => null,
            'state'   => null,
            'country' => null,
        ]);
    }

    public function test_a_staff_member_can_be_added_as_inactive(): void
    {
        $user = User::factory()->create();
        $this->signIn($user);

        $response = $this->postJson('/api/v1/user-staff', $this->payload(['status' => 0]))->assertCreated();

        $this->assertSame(0, $response->json('data.status'));
        $this->assertDatabaseHas('user_staff', ['user_id' => $user->id, 'name' => 'Rahim Uddin', 'status' => 0]);
    }

    public function test_the_status_is_kept_as_a_number_even_when_it_arrives_as_text(): void
    {
        $this->signIn(User::factory()->create());

        foreach ([0, 1] as $status) {
            $response = $this->postJson('/api/v1/user-staff', $this->payload(['status' => (string) $status]))->assertCreated();

            $this->assertSame($status, $response->json('data.status'));
        }
    }

    public function test_the_status_must_be_one_or_zero(): void
    {
        $this->signIn(User::factory()->create());

        // Not a whole number, or not one of the two.
        foreach ([2, -1, 10, 1.5, 'active', 'inactive', 'yes', 'abc', [1], [0]] as $status) {
            $this->postJson('/api/v1/user-staff', $this->payload(['status' => $status]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('status')
                ->assertJsonMissingValidationErrors(['name']);
        }

        // Nor can it be empty.
        foreach ([null, '', '  '] as $status) {
            $this->postJson('/api/v1/user-staff', $this->payload(['status' => $status]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('status');
        }

        $this->assertDatabaseCount('user_staff', 0);
    }

    public function test_two_staff_members_may_share_a_name(): void
    {
        $user = User::factory()->create();
        $this->signIn($user);

        $this->postJson('/api/v1/user-staff', $this->payload())->assertCreated();
        $this->postJson('/api/v1/user-staff', $this->payload(['email' => 'another@example.com']))->assertCreated();

        $this->assertSame(2, $user->userStaff()->where('name', 'Rahim Uddin')->count());
    }

    public function test_the_name_is_required_and_limited(): void
    {
        $this->signIn(User::factory()->create());

        $this->postJson('/api/v1/user-staff', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->postJson('/api/v1/user-staff', $this->payload(['name' => str_repeat('a', 256)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseCount('user_staff', 0);
    }

    public function test_a_name_of_only_spaces_counts_as_empty(): void
    {
        $this->signIn(User::factory()->create());

        $this->postJson('/api/v1/user-staff', $this->payload(['name' => '    ']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_the_email_must_look_like_one(): void
    {
        $this->signIn(User::factory()->create());

        foreach (['not-an-email', 'missing@', '@missing.com', 'two words@example.com'] as $email) {
            $this->postJson('/api/v1/user-staff', $this->payload(['email' => $email]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('email');
        }

        // Too long to be one, whatever it looks like.
        $this->postJson('/api/v1/user-staff', $this->payload(['email' => str_repeat('a', 250).'@example.com']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('user_staff', 0);
    }

    public function test_each_field_is_limited_to_what_its_column_holds(): void
    {
        $this->signIn(User::factory()->create());

        // One over the column width is refused, on that field and no other.
        foreach (['phone' => 30, 'address' => 255, 'state' => 100, 'country' => 100] as $field => $max) {
            $this->postJson('/api/v1/user-staff', $this->payload([$field => str_repeat('a', $max + 1)]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field)
                ->assertJsonMissingValidationErrors(['name']);
        }

        $this->assertDatabaseCount('user_staff', 0);
    }

    public function test_the_longest_values_are_accepted_and_the_log_entry_still_fits(): void
    {
        $this->signIn(User::factory()->create());

        $this->postJson('/api/v1/user-staff', [
            'name'    => str_repeat('n', 255),
            'email'   => 'longest@example.com',
            'phone'   => str_repeat('1', 30),
            'address' => str_repeat('a', 255),
            'state'   => str_repeat('s', 100),
            'country' => str_repeat('c', 100),
            'status'  => 1,
        ])->assertCreated();

        // activity_logs.description is 255 characters wide.
        $this->assertLessThanOrEqual(255, strlen(ActivityLog::latest('id')->value('description')));
    }

    /* ---------------------------------------------------------- update */

    public function test_update_changes_the_fields(): void
    {
        $user  = User::factory()->create();
        $staff = $this->staffOf($user);
        $this->signIn($user);

        $this->putJson("/api/v1/user-staff/{$staff->id}", [
            'name'    => 'Karim Hossain',
            'email'   => 'karim@example.com',
            'phone'   => '+919800000000',
            'address' => '4 Park Street',
            'state'   => 'West Bengal',
            'country' => 'India',
            'status'  => 0,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Staff member updated successfully.')
            ->assertJsonPath('data.id', $staff->id)
            ->assertJsonPath('data.name', 'Karim Hossain')
            ->assertJsonPath('data.email', 'karim@example.com')
            ->assertJsonPath('data.country', 'India')
            ->assertJsonPath('data.status', 0);

        $this->assertDatabaseHas('user_staff', [
            'id'      => $staff->id,
            'user_id' => $user->id,
            'name'    => 'Karim Hossain',
            'email'   => 'karim@example.com',
            'phone'   => '+919800000000',
            'address' => '4 Park Street',
            'state'   => 'West Bengal',
            'country' => 'India',
            'status'  => 0,
        ]);
        $this->assertDatabaseCount('user_staff', 1);
    }

    public function test_update_can_clear_the_optional_fields(): void
    {
        $user  = User::factory()->create();
        $staff = $this->staffOf($user);
        $this->signIn($user);

        // Cleared both ways: an explicit null, and a blank the form sends for an emptied input.
        $this->putJson("/api/v1/user-staff/{$staff->id}", $this->payload([
            'address' => null,
            'state'   => '  ',
            'country' => null,
        ]))
            ->assertOk()
            ->assertJsonPath('data.address', null)
            ->assertJsonPath('data.state', null)
            ->assertJsonPath('data.country', null);

        // The required four are not among them: they were sent, and they stay.
        $this->assertDatabaseHas('user_staff', [
            'id'      => $staff->id,
            'name'    => 'Rahim Uddin',
            'email'   => 'rahim@example.com',
            'phone'   => '+8801700000000',
            'address' => null,
            'state'   => null,
            'country' => null,
            'status'  => 1,
        ]);
    }

    public function test_an_update_needs_the_required_fields_too(): void
    {
        $user  = User::factory()->create();
        $staff = $this->staffOf($user);
        $this->signIn($user);

        foreach (['name', 'email', 'phone', 'status'] as $field) {
            $without = $this->payload();
            unset($without[$field]);

            foreach ([$without, $this->payload([$field => null]), $this->payload([$field => ''])] as $body) {
                $this->putJson("/api/v1/user-staff/{$staff->id}", $body)
                    ->assertUnprocessable()
                    ->assertJsonValidationErrors($field);
            }
        }

        // None of that touched the row.
        $this->assertDatabaseHas('user_staff', [
            'id'      => $staff->id,
            'name'    => 'Rahim Uddin',
            'email'   => 'rahim@example.com',
            'phone'   => '+8801700000000',
            'status'  => 1,
        ]);
    }

    public function test_an_optional_field_left_out_of_an_update_is_left_as_it_was(): void
    {
        $user  = User::factory()->create();
        $staff = $this->staffOf($user, ['status' => 0]);
        $this->signIn($user);

        // The required four, and nothing about the address, state or country.
        $this->putJson("/api/v1/user-staff/{$staff->id}", [
            'name'   => 'Renamed',
            'email'  => 'renamed@example.com',
            'phone'  => '+8801711111111',
            'status' => 0,
        ])->assertOk();

        $fresh = $staff->fresh();

        $this->assertSame('Renamed', $fresh->name);
        $this->assertSame('renamed@example.com', $fresh->email);
        $this->assertSame('+8801711111111', $fresh->phone);
        $this->assertSame('House 12, Road 5, Mirpur-11', $fresh->address);
        $this->assertSame('Dhaka', $fresh->state);
        $this->assertSame('Bangladesh', $fresh->country);
        $this->assertSame(0, $fresh->status);
    }

    public function test_update_changes_the_status_both_ways(): void
    {
        $user  = User::factory()->create();
        $staff = $this->staffOf($user);
        $this->signIn($user);

        $response = $this->putJson("/api/v1/user-staff/{$staff->id}", $this->payload(['status' => 0]))->assertOk();

        $this->assertSame(0, $response->json('data.status'));
        $this->assertSame(0, $staff->fresh()->status);

        $response = $this->putJson("/api/v1/user-staff/{$staff->id}", $this->payload(['status' => 1]))->assertOk();

        $this->assertSame(1, $response->json('data.status'));
        $this->assertSame(1, $staff->fresh()->status);
    }

    public function test_an_update_cannot_clear_the_status_or_set_one_that_is_not_offered(): void
    {
        $user  = User::factory()->create();
        $staff = $this->staffOf($user, ['status' => 0]);
        $this->signIn($user);

        foreach ([null, '', 2, 'active'] as $status) {
            $this->putJson("/api/v1/user-staff/{$staff->id}", $this->payload(['status' => $status]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('status');
        }

        $this->assertSame(0, $staff->fresh()->status);
    }

    public function test_update_is_validated_like_a_create(): void
    {
        $user  = User::factory()->create();
        $staff = $this->staffOf($user);
        $this->signIn($user);

        $this->putJson("/api/v1/user-staff/{$staff->id}", $this->payload(['name' => '', 'email' => 'nope']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);

        $this->assertDatabaseHas('user_staff', ['id' => $staff->id, 'name' => 'Rahim Uddin', 'email' => 'rahim@example.com']);
    }

    public function test_a_user_id_in_an_update_cannot_move_the_staff_member(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $staff = $this->staffOf($owner);
        $this->signIn($owner);

        $this->putJson("/api/v1/user-staff/{$staff->id}", $this->payload(['name' => 'Renamed', 'user_id' => $other->id]))->assertOk();

        $fresh = $staff->fresh();

        $this->assertSame('Renamed', $fresh->name);
        $this->assertSame($owner->id, $fresh->user_id);
    }

    public function test_someone_elses_staff_member_cannot_be_updated(): void
    {
        $owner = User::factory()->create();
        $staff = $this->staffOf($owner);
        $this->signIn(User::factory()->create());

        $this->putJson("/api/v1/user-staff/{$staff->id}", $this->payload(['name' => 'Hijacked']))
            ->assertNotFound()
            ->assertExactJson(['message' => 'Staff member not found.']);

        $this->assertDatabaseHas('user_staff', ['id' => $staff->id, 'name' => 'Rahim Uddin', 'user_id' => $owner->id]);
    }

    public function test_updating_a_staff_member_that_does_not_exist_is_a_404(): void
    {
        $this->signIn(User::factory()->create());

        $this->putJson('/api/v1/user-staff/999999', $this->payload())
            ->assertNotFound()
            ->assertExactJson(['message' => 'Staff member not found.']);
    }

    /* ---------------------------------------------------------- delete */

    public function test_destroy_removes_the_staff_member_and_only_that(): void
    {
        $user  = User::factory()->create();
        $staff = $this->staffOf($user);
        $kept  = $this->staffOf($user, ['name' => 'Keep Me']);
        $this->signIn($user);

        $this->deleteJson("/api/v1/user-staff/{$staff->id}")
            ->assertOk()
            ->assertExactJson(['message' => 'Staff member deleted successfully.']);

        $this->assertDatabaseMissing('user_staff', ['id' => $staff->id]);
        $this->assertDatabaseHas('user_staff', ['id' => $kept->id]);

        // The account that owned it is not the staff member's to take down with it.
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_someone_elses_staff_member_cannot_be_deleted(): void
    {
        $owner = User::factory()->create();
        $staff = $this->staffOf($owner);
        $this->signIn(User::factory()->create());

        $this->deleteJson("/api/v1/user-staff/{$staff->id}")
            ->assertNotFound()
            ->assertExactJson(['message' => 'Staff member not found.']);

        $this->assertDatabaseHas('user_staff', ['id' => $staff->id]);
    }

    public function test_deleting_the_same_staff_member_twice_is_a_404(): void
    {
        $user  = User::factory()->create();
        $staff = $this->staffOf($user);
        $this->signIn($user);

        $this->deleteJson("/api/v1/user-staff/{$staff->id}")->assertOk();
        $this->deleteJson("/api/v1/user-staff/{$staff->id}")->assertNotFound();
    }

    public function test_an_id_that_is_not_a_number_is_a_plain_404(): void
    {
        $this->signIn(User::factory()->create());

        $this->putJson('/api/v1/user-staff/abc', $this->payload())->assertNotFound();
        $this->deleteJson('/api/v1/user-staff/abc')->assertNotFound();
    }

    public function test_deleting_an_account_takes_its_staff_with_it(): void
    {
        $leaving = User::factory()->create();
        $staying = User::factory()->create();

        $this->staffOf($leaving);
        $kept = $this->staffOf($staying, ['name' => 'Stays']);

        $leaving->delete();

        $this->assertSame([$kept->id], UserStaff::pluck('id')->all());
    }

    /* ---------------------------------------------------------- one business at a time */

    public function test_each_business_keeps_its_own_staff(): void
    {
        $user = User::factory()->create();
        $this->staffOf($user, ['name' => 'First Business Staff']);

        $second = $user->workspaces()->create(['name' => 'Second Business']);
        $header = [ResolveWorkspace::HEADER => (string) $second->id];
        $this->signIn($user);

        $this->getJson('/api/v1/user-staff', $header)->assertOk()->assertExactJson(['data' => []]);
        $this->postJson('/api/v1/user-staff', $this->payload(['name' => 'Second Business Staff']), $header)->assertCreated();

        // No business picked: the oldest one's.
        $this->assertSame(['First Business Staff'], array_column($this->getJson('/api/v1/user-staff')->json('data'), 'name'));
        $this->assertSame(['Second Business Staff'], array_column($this->getJson('/api/v1/user-staff', $header)->json('data'), 'name'));
        $this->assertSame(['Second Business Staff'], $second->staff()->pluck('name')->all());
    }

    public function test_a_staff_member_cannot_be_reached_from_another_business_of_the_same_owner(): void
    {
        $user   = User::factory()->create();
        $staff  = $this->staffOf($user);
        $second = $user->workspaces()->create(['name' => 'Second Business']);
        $header = [ResolveWorkspace::HEADER => (string) $second->id];
        $this->signIn($user);

        $this->putJson("/api/v1/user-staff/{$staff->id}", $this->payload(['name' => 'Moved']), $header)
            ->assertNotFound()
            ->assertExactJson(['message' => 'Staff member not found.']);
        $this->deleteJson("/api/v1/user-staff/{$staff->id}", [], $header)->assertNotFound();

        $this->assertDatabaseHas('user_staff', ['id' => $staff->id, 'name' => 'Rahim Uddin', 'workspace_id' => $this->businessOf($user)->id]);
    }

    public function test_an_account_with_no_workspace_is_asked_to_create_one(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/user-staff')
            ->assertNotFound()
            ->assertJsonPath('message', 'Create a workspace first.');
        $this->postJson('/api/v1/user-staff', $this->payload())->assertNotFound();

        $this->assertDatabaseCount('user_staff', 0);
    }

    /* ---------------------------------------------------------- assignments */

    public function test_the_apps_and_workspaces_a_staff_member_is_assigned_to_are_saved(): void
    {
        $user      = User::factory()->create();
        $sales     = $this->app('Sales Lite');
        $purchases = $this->app('Purchases Lite');
        $main      = $this->workspaceOf($user, 'Main');
        $branch    = $this->workspaceOf($user, 'Branch');
        $this->signIn($user);

        $response = $this->postJson('/api/v1/user-staff', $this->payload([
            'assigned_apps'       => [$purchases->id, $sales->id],
            'assigned_workspaces' => [$branch->id, $main->id],
        ]))->assertCreated();

        // Whole numbers, in order, in the answer...
        $this->assertSame([$sales->id, $purchases->id], $response->json('data.assigned_apps'));
        $this->assertSame([$main->id, $branch->id], $response->json('data.assigned_workspaces'));

        // ...as the row holds them...
        $staff = UserStaff::findOrFail($response->json('data.id'));

        $this->assertSame([$sales->id, $purchases->id], $this->stored($staff, 'assigned_apps'));
        $this->assertSame([$main->id, $branch->id], $this->stored($staff, 'assigned_workspaces'));

        // ...and as the list hands them back.
        $this->getJson('/api/v1/user-staff')
            ->assertOk()
            ->assertJsonPath('data.0.assigned_apps', [$sales->id, $purchases->id])
            ->assertJsonPath('data.0.assigned_workspaces', [$main->id, $branch->id]);
    }

    public function test_assignments_are_optional_and_come_back_as_empty_lists(): void
    {
        $user = User::factory()->create();
        $this->signIn($user);

        foreach ([[], ['assigned_apps' => null, 'assigned_workspaces' => null], ['assigned_apps' => [], 'assigned_workspaces' => []]] as $extra) {
            $response = $this->postJson('/api/v1/user-staff', $this->payload($extra))->assertCreated();

            $this->assertSame([], $response->json('data.assigned_apps'));
            $this->assertSame([], $response->json('data.assigned_workspaces'));
        }

        // A row that never had them - as every row did before the columns - reads the same.
        DB::table('user_staff')->update(['assigned_apps' => null, 'assigned_workspaces' => null]);

        $this->getJson('/api/v1/user-staff')
            ->assertOk()
            ->assertJsonPath('data.0.assigned_apps', [])
            ->assertJsonPath('data.0.assigned_workspaces', []);
    }

    public function test_assignments_are_stored_as_whole_numbers_each_once_and_in_order(): void
    {
        $user = User::factory()->create();
        $a    = $this->app('A');
        $b    = $this->app('B');
        $w1   = $this->workspaceOf($user, 'One');
        $w2   = $this->workspaceOf($user, 'Two');
        $this->signIn($user);

        // Text, and out of order: what a form may send.
        $response = $this->postJson('/api/v1/user-staff', $this->payload([
            'assigned_apps'       => [(string) $b->id, (string) $a->id],
            'assigned_workspaces' => [(string) $w2->id, (string) $w1->id],
        ]))->assertCreated();

        $this->assertSame([$a->id, $b->id], $response->json('data.assigned_apps'));
        $this->assertSame([$w1->id, $w2->id], $response->json('data.assigned_workspaces'));
    }

    public function test_update_changes_and_clears_the_assignments(): void
    {
        $user  = User::factory()->create();
        $a     = $this->app('A');
        $b     = $this->app('B');
        $w1    = $this->workspaceOf($user, 'One');
        $w2    = $this->workspaceOf($user, 'Two');
        $staff = $this->staffOf($user, ['assigned_apps' => [$a->id], 'assigned_workspaces' => [$w1->id]]);
        $this->signIn($user);

        $this->putJson("/api/v1/user-staff/{$staff->id}", $this->payload(['assigned_apps' => [$b->id], 'assigned_workspaces' => [$w1->id, $w2->id]]))
            ->assertOk()
            ->assertJsonPath('data.assigned_apps', [$b->id])
            ->assertJsonPath('data.assigned_workspaces', [$w1->id, $w2->id]);

        $this->assertSame([$b->id], $this->stored($staff, 'assigned_apps'));

        // Cleared both ways: an empty list, and null.
        $this->putJson("/api/v1/user-staff/{$staff->id}", $this->payload(['assigned_apps' => [], 'assigned_workspaces' => null]))
            ->assertOk()
            ->assertJsonPath('data.assigned_apps', [])
            ->assertJsonPath('data.assigned_workspaces', []);

        $this->assertSame([], $this->stored($staff, 'assigned_apps'));
        $this->assertSame([], $this->stored($staff, 'assigned_workspaces'));
    }

    public function test_an_update_that_leaves_the_assignments_out_leaves_them_as_they_were(): void
    {
        $user  = User::factory()->create();
        $a     = $this->app('A');
        $w1    = $this->workspaceOf($user, 'One');
        $staff = $this->staffOf($user, ['assigned_apps' => [$a->id], 'assigned_workspaces' => [$w1->id]]);
        $this->signIn($user);

        $this->putJson("/api/v1/user-staff/{$staff->id}", $this->payload(['name' => 'Renamed']))
            ->assertOk()
            ->assertJsonPath('data.assigned_apps', [$a->id])
            ->assertJsonPath('data.assigned_workspaces', [$w1->id]);

        $this->assertSame([$a->id], $this->stored($staff, 'assigned_apps'));
        $this->assertSame([$w1->id], $this->stored($staff, 'assigned_workspaces'));
    }

    public function test_the_assigned_apps_must_be_active_apps_chosen_once_each(): void
    {
        $user    = User::factory()->create();
        $active  = $this->app('Active');
        $retired = $this->app('Retired', false);
        $this->signIn($user);

        // An app an admin has switched off, one that does not exist, and a non-id.
        foreach ([[$retired->id], [999999], ['sales'], [$active->id, 999999]] as $apps) {
            $this->postJson('/api/v1/user-staff', $this->payload(['assigned_apps' => $apps]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('assigned_apps.'.(count($apps) - 1));
        }

        // The same one twice, and not a list at all.
        $this->postJson('/api/v1/user-staff', $this->payload(['assigned_apps' => [$active->id, $active->id]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_apps.0' => 'only once']);

        $this->postJson('/api/v1/user-staff', $this->payload(['assigned_apps' => 'sales']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_apps');

        $this->assertDatabaseCount('user_staff', 0);
    }

    public function test_the_assigned_workspaces_must_be_the_callers_own(): void
    {
        $user      = User::factory()->create();
        $mine      = $this->workspaceOf($user, 'Mine');
        $someoneElses = $this->workspaceOf(User::factory()->create(), 'Not Mine');
        $this->signIn($user);

        foreach ([[$someoneElses->id], [999999], [$mine->id, $someoneElses->id]] as $workspaces) {
            $this->postJson('/api/v1/user-staff', $this->payload(['assigned_workspaces' => $workspaces]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['assigned_workspaces.'.(count($workspaces) - 1) => 'your own workspaces']);
        }

        $this->postJson('/api/v1/user-staff', $this->payload(['assigned_workspaces' => [$mine->id, $mine->id]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assigned_workspaces.0' => 'only once']);

        // The same rule when a staff member is edited.
        $staff = $this->staffOf($user);

        $this->putJson("/api/v1/user-staff/{$staff->id}", $this->payload(['assigned_workspaces' => [$someoneElses->id]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_workspaces.0');

        $this->assertNull($this->stored($staff, 'assigned_workspaces'));
    }

    public function test_deleting_a_workspace_takes_it_out_of_the_staff_it_was_assigned_to(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $w1    = $this->workspaceOf($user, 'One');
        $w2    = $this->workspaceOf($user, 'Two');

        $both     = $this->staffOf($user, ['name' => 'Both', 'assigned_workspaces' => [$w1->id, $w2->id]]);
        $onlyTwo  = $this->staffOf($user, ['name' => 'Only Two', 'assigned_workspaces' => [$w2->id]]);
        $neither  = $this->staffOf($user, ['name' => 'Neither', 'assigned_workspaces' => [$w1->id]]);
        // Another account's staff member cannot really hold this id; if one somehow does, it is not ours to edit.
        $foreign  = $this->staffOf($other, ['name' => 'Foreign', 'assigned_workspaces' => [$w2->id]]);
        $this->signIn($user);

        $this->deleteJson("/api/v1/workspaces/{$w2->id}")->assertOk();

        $this->assertSame([$w1->id], $this->stored($both, 'assigned_workspaces'));
        $this->assertSame([], $this->stored($onlyTwo, 'assigned_workspaces'));
        $this->assertSame([$w1->id], $this->stored($neither, 'assigned_workspaces'));
        $this->assertSame([$w2->id], $this->stored($foreign, 'assigned_workspaces'));

        $this->getJson('/api/v1/user-staff')
            ->assertOk()
            ->assertJsonPath('data.0.assigned_workspaces', [$w1->id]);
    }

    /* ---------------------------------------------------------- activity log */

    public function test_a_changed_assignment_is_logged_as_the_field_that_changed_and_a_reordered_one_is_not(): void
    {
        $user  = User::factory()->create();
        $a     = $this->app('A');
        $b     = $this->app('B');
        $staff = $this->staffOf($user, ['assigned_apps' => [$a->id, $b->id]]);
        $this->signIn($user);

        // The same two apps, sent the other way round, with a new name: only the name changed.
        $this->putJson("/api/v1/user-staff/{$staff->id}", $this->payload(['name' => 'Renamed', 'assigned_apps' => [$b->id, $a->id]]))->assertOk();

        $this->assertSame(['name'], ActivityLog::where('user_id', $user->id)->where('action', 'updated')->latest('id')->firstOrFail()->properties['fields']);

        // Now one app fewer: that is the change.
        $this->putJson("/api/v1/user-staff/{$staff->id}", $this->payload(['name' => 'Renamed', 'assigned_apps' => [$a->id]]))->assertOk();

        $this->assertSame(['assigned_apps'], ActivityLog::where('user_id', $user->id)->where('action', 'updated')->latest('id')->firstOrFail()->properties['fields']);
    }

    public function test_each_write_is_logged_once_by_name(): void
    {
        $user = User::factory()->create();
        $this->signIn($user);

        $id = $this->postJson('/api/v1/user-staff', $this->payload())->json('data.id');
        $this->putJson("/api/v1/user-staff/{$id}", $this->payload(['name' => 'Renamed']))->assertOk();
        $this->deleteJson("/api/v1/user-staff/{$id}")->assertOk();

        // One descriptive entry per write, and no generic `api_auto` one on top.
        $logs = ActivityLog::where('user_id', $user->id)->orderBy('id')->get();

        $this->assertCount(3, $logs);
        $this->assertSame(['created', 'Added staff member "Rahim Uddin"'], [$logs[0]->action, $logs[0]->description]);
        $this->assertSame(['updated', 'Updated staff member "Renamed"'], [$logs[1]->action, $logs[1]->description]);
        $this->assertSame(['name'], $logs[1]->properties['fields']);
        $this->assertSame(['deleted', 'Deleted staff member "Renamed"'], [$logs[2]->action, $logs[2]->description]);
    }

    public function test_a_status_change_is_logged_as_the_field_that_changed(): void
    {
        $user  = User::factory()->create();
        $staff = $this->staffOf($user);
        $this->signIn($user);

        $this->putJson("/api/v1/user-staff/{$staff->id}", $this->payload(['status' => 0]))->assertOk();

        $log = ActivityLog::where('user_id', $user->id)->where('action', 'updated')->firstOrFail();

        $this->assertSame(['status'], $log->properties['fields']);
    }
}
