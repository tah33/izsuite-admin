<?php

namespace Tests\Feature;

use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use App\Models\User\UserStaff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserStaffApiTest extends TestCase
{
    use RefreshDatabase;

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

    private function staffOf(User $user, array $overrides = []): UserStaff
    {
        return $user->userStaff()->create($this->payload($overrides));
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
        Sanctum::actingAs(User::factory()->create());

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

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/user-staff')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'phone', 'address', 'state', 'country', 'status']]])
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

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/user-staff')->assertOk();

        $this->assertSame([$first->id, $second->id], array_column($response->json('data'), 'id'));
    }

    /* ---------------------------------------------------------- create */

    public function test_store_creates_a_staff_member_for_the_signed_in_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

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
            ->assertJsonStructure(['data' => ['id']])
            ->assertJsonMissingPath('data.user_id');

        $this->assertDatabaseHas('user_staff', array_merge(['user_id' => $user->id], $this->payload()));
    }

    public function test_the_signed_in_user_is_always_the_owner(): void
    {
        $first  = User::factory()->create();
        $second = User::factory()->create();

        Sanctum::actingAs($first);
        $this->postJson('/api/v1/user-staff', $this->payload(['name' => 'First Owner Staff']))->assertCreated();

        Sanctum::actingAs($second);
        $this->postJson('/api/v1/user-staff', $this->payload(['name' => 'Second Owner Staff']))->assertCreated();

        $this->assertDatabaseHas('user_staff', ['user_id' => $first->id, 'name' => 'First Owner Staff']);
        $this->assertDatabaseHas('user_staff', ['user_id' => $second->id, 'name' => 'Second Owner Staff']);
    }

    public function test_a_user_id_in_the_request_cannot_give_the_staff_member_to_someone_else(): void
    {
        $victim   = User::factory()->create();
        $attacker = User::factory()->create();
        Sanctum::actingAs($attacker);

        $this->postJson('/api/v1/user-staff', $this->payload(['user_id' => $victim->id]))->assertCreated();

        $this->assertSame(0, $victim->userStaff()->count());
        $this->assertSame(1, $attacker->userStaff()->count());
    }

    public function test_the_address_state_and_country_are_optional(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

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
        Sanctum::actingAs(User::factory()->create());

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
        Sanctum::actingAs(User::factory()->create());

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
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/user-staff', $this->payload(['status' => 0]))->assertCreated();

        $this->assertSame(0, $response->json('data.status'));
        $this->assertDatabaseHas('user_staff', ['user_id' => $user->id, 'name' => 'Rahim Uddin', 'status' => 0]);
    }

    public function test_the_status_is_kept_as_a_number_even_when_it_arrives_as_text(): void
    {
        Sanctum::actingAs(User::factory()->create());

        foreach ([0, 1] as $status) {
            $response = $this->postJson('/api/v1/user-staff', $this->payload(['status' => (string) $status]))->assertCreated();

            $this->assertSame($status, $response->json('data.status'));
        }
    }

    public function test_the_status_must_be_one_or_zero(): void
    {
        Sanctum::actingAs(User::factory()->create());

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
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/user-staff', $this->payload())->assertCreated();
        $this->postJson('/api/v1/user-staff', $this->payload(['email' => 'another@example.com']))->assertCreated();

        $this->assertSame(2, $user->userStaff()->where('name', 'Rahim Uddin')->count());
    }

    public function test_the_name_is_required_and_limited(): void
    {
        Sanctum::actingAs(User::factory()->create());

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
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/user-staff', $this->payload(['name' => '    ']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_the_email_must_look_like_one(): void
    {
        Sanctum::actingAs(User::factory()->create());

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
        Sanctum::actingAs(User::factory()->create());

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
        Sanctum::actingAs(User::factory()->create());

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
        Sanctum::actingAs($user);

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
        Sanctum::actingAs($user);

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
        Sanctum::actingAs($user);

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
        Sanctum::actingAs($user);

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
        Sanctum::actingAs($user);

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
        Sanctum::actingAs($user);

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
        Sanctum::actingAs($user);

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
        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/user-staff/{$staff->id}", $this->payload(['name' => 'Renamed', 'user_id' => $other->id]))->assertOk();

        $fresh = $staff->fresh();

        $this->assertSame('Renamed', $fresh->name);
        $this->assertSame($owner->id, $fresh->user_id);
    }

    public function test_someone_elses_staff_member_cannot_be_updated(): void
    {
        $owner = User::factory()->create();
        $staff = $this->staffOf($owner);
        Sanctum::actingAs(User::factory()->create());

        $this->putJson("/api/v1/user-staff/{$staff->id}", $this->payload(['name' => 'Hijacked']))
            ->assertNotFound()
            ->assertExactJson(['message' => 'Staff member not found.']);

        $this->assertDatabaseHas('user_staff', ['id' => $staff->id, 'name' => 'Rahim Uddin', 'user_id' => $owner->id]);
    }

    public function test_updating_a_staff_member_that_does_not_exist_is_a_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

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
        Sanctum::actingAs($user);

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
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson("/api/v1/user-staff/{$staff->id}")
            ->assertNotFound()
            ->assertExactJson(['message' => 'Staff member not found.']);

        $this->assertDatabaseHas('user_staff', ['id' => $staff->id]);
    }

    public function test_deleting_the_same_staff_member_twice_is_a_404(): void
    {
        $user  = User::factory()->create();
        $staff = $this->staffOf($user);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/user-staff/{$staff->id}")->assertOk();
        $this->deleteJson("/api/v1/user-staff/{$staff->id}")->assertNotFound();
    }

    public function test_an_id_that_is_not_a_number_is_a_plain_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

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

    /* ---------------------------------------------------------- activity log */

    public function test_each_write_is_logged_once_by_name(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

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
        Sanctum::actingAs($user);

        $this->putJson("/api/v1/user-staff/{$staff->id}", $this->payload(['status' => 0]))->assertOk();

        $log = ActivityLog::where('user_id', $user->id)->where('action', 'updated')->firstOrFail();

        $this->assertSame(['status'], $log->properties['fields']);
    }
}
