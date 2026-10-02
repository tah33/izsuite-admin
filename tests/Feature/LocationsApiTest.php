<?php

namespace Tests\Feature;

use App\Models\Shared\ActivityLog;
use App\Models\User\Location;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocationsApiTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge(['name' => 'Dhaka Warehouse', 'country' => 'Bangladesh', 'state' => 'Dhaka'], $overrides);
    }

    private function locationOf(User $user, array $overrides = []): Location
    {
        return $user->locations()->create($this->payload($overrides));
    }

    /* ---------------------------------------------------------- auth */

    public function test_every_location_endpoint_requires_a_token(): void
    {
        $owner    = User::factory()->create();
        $location = $this->locationOf($owner);

        $this->getJson('/api/v1/locations')->assertUnauthorized();
        $this->postJson('/api/v1/locations', $this->payload())->assertUnauthorized();
        $this->putJson("/api/v1/locations/{$location->id}", $this->payload(['name' => 'Hijacked']))->assertUnauthorized();
        $this->deleteJson("/api/v1/locations/{$location->id}")->assertUnauthorized();

        $this->assertDatabaseCount('locations', 1);
        $this->assertDatabaseHas('locations', ['id' => $location->id, 'name' => 'Dhaka Warehouse']);
    }

    /* ---------------------------------------------------------- list */

    public function test_a_new_account_has_no_locations(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/locations')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_list_returns_only_the_signed_in_users_locations_by_name(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        $this->locationOf($user, ['name' => 'Zeta Outlet']);
        $this->locationOf($user, ['name' => 'Alpha Warehouse']);
        $this->locationOf($other, ['name' => 'Not Mine']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/locations')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'country', 'state']]])
            ->assertJsonMissingPath('data.0.user_id');

        $this->assertSame(['Alpha Warehouse', 'Zeta Outlet'], array_column($response->json('data'), 'name'));
    }

    /* ---------------------------------------------------------- create */

    public function test_store_creates_a_location_for_the_signed_in_user(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/locations', $this->payload())
            ->assertCreated()
            ->assertJsonPath('message', 'Location added successfully.')
            ->assertJsonPath('data.name', 'Dhaka Warehouse')
            ->assertJsonPath('data.country', 'Bangladesh')
            ->assertJsonPath('data.state', 'Dhaka')
            ->assertJsonStructure(['data' => ['id']]);

        $this->assertDatabaseHas('locations', ['user_id' => $user->id, 'name' => 'Dhaka Warehouse', 'country' => 'Bangladesh', 'state' => 'Dhaka']);
    }

    public function test_an_account_can_have_several_locations_with_the_same_name(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/locations', $this->payload())->assertCreated();
        $this->postJson('/api/v1/locations', $this->payload(['state' => 'Chattogram']))->assertCreated();

        $this->assertSame(2, $user->locations()->count());
    }

    public function test_a_user_id_in_the_request_cannot_give_the_location_to_someone_else(): void
    {
        $victim   = User::factory()->create();
        $attacker = User::factory()->create();
        Sanctum::actingAs($attacker);

        $this->postJson('/api/v1/locations', $this->payload(['user_id' => $victim->id]))->assertCreated();

        $this->assertSame(0, $victim->locations()->count());
        $this->assertSame(1, $attacker->locations()->count());
    }

    public function test_name_country_and_state_are_all_required(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/locations', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'country', 'state']);

        $this->assertDatabaseCount('locations', 0);
    }

    public function test_values_of_only_spaces_count_as_empty(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/locations', ['name' => '   ', 'country' => ' ', 'state' => "\t"])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'country', 'state']);
    }

    public function test_values_respect_their_column_lengths(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/locations', [
            'name'    => str_repeat('a', 256),
            'country' => str_repeat('a', 101),
            'state'   => str_repeat('a', 101),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'country', 'state']);

        // The longest values the columns hold are accepted - and the activity
        // log entry that names the location must still fit its own column.
        $this->postJson('/api/v1/locations', [
            'name'    => str_repeat('a', 255),
            'country' => str_repeat('a', 100),
            'state'   => str_repeat('a', 100),
        ])->assertCreated();

        $this->assertLessThanOrEqual(255, strlen(ActivityLog::latest('id')->value('description')));
    }

    /* ---------------------------------------------------------- update */

    public function test_update_changes_the_location(): void
    {
        $user     = User::factory()->create();
        $location = $this->locationOf($user);
        Sanctum::actingAs($user);

        $this->putJson("/api/v1/locations/{$location->id}", ['name' => 'Port Depot', 'country' => 'Bangladesh', 'state' => 'Chattogram'])
            ->assertOk()
            ->assertJsonPath('message', 'Location updated successfully.')
            ->assertJsonPath('data.id', $location->id)
            ->assertJsonPath('data.name', 'Port Depot')
            ->assertJsonPath('data.state', 'Chattogram');

        $this->assertDatabaseHas('locations', ['id' => $location->id, 'name' => 'Port Depot', 'state' => 'Chattogram']);
        $this->assertDatabaseCount('locations', 1);
    }

    public function test_update_is_validated_like_a_create(): void
    {
        $user     = User::factory()->create();
        $location = $this->locationOf($user);
        Sanctum::actingAs($user);

        $this->putJson("/api/v1/locations/{$location->id}", ['name' => '', 'country' => 'Bangladesh', 'state' => 'Chattogram'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseHas('locations', ['id' => $location->id, 'name' => 'Dhaka Warehouse', 'state' => 'Dhaka']);
    }

    public function test_a_user_id_in_an_update_cannot_move_the_location(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $location = $this->locationOf($owner);
        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/locations/{$location->id}", $this->payload(['name' => 'Renamed', 'user_id' => $other->id]))->assertOk();

        $this->assertSame($owner->id, $location->fresh()->user_id);
        $this->assertSame('Renamed', $location->fresh()->name);
    }

    public function test_someone_elses_location_cannot_be_updated(): void
    {
        $owner    = User::factory()->create();
        $location = $this->locationOf($owner);
        Sanctum::actingAs(User::factory()->create());

        $this->putJson("/api/v1/locations/{$location->id}", $this->payload(['name' => 'Hijacked']))
            ->assertNotFound()
            ->assertExactJson(['message' => 'Location not found.']);

        $this->assertDatabaseHas('locations', ['id' => $location->id, 'name' => 'Dhaka Warehouse', 'user_id' => $owner->id]);
    }

    public function test_updating_a_location_that_does_not_exist_is_a_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/v1/locations/999999', $this->payload())
            ->assertNotFound()
            ->assertExactJson(['message' => 'Location not found.']);
    }

    /* ---------------------------------------------------------- delete */

    public function test_destroy_removes_the_location(): void
    {
        $user     = User::factory()->create();
        $location = $this->locationOf($user);
        $kept     = $this->locationOf($user, ['name' => 'Keep Me']);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/locations/{$location->id}")
            ->assertOk()
            ->assertExactJson(['message' => 'Location deleted successfully.']);

        $this->assertDatabaseMissing('locations', ['id' => $location->id]);
        $this->assertDatabaseHas('locations', ['id' => $kept->id]);
    }

    public function test_someone_elses_location_cannot_be_deleted(): void
    {
        $owner    = User::factory()->create();
        $location = $this->locationOf($owner);
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson("/api/v1/locations/{$location->id}")
            ->assertNotFound()
            ->assertExactJson(['message' => 'Location not found.']);

        $this->assertDatabaseHas('locations', ['id' => $location->id]);
    }

    public function test_deleting_the_same_location_twice_is_a_404(): void
    {
        $user     = User::factory()->create();
        $location = $this->locationOf($user);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/locations/{$location->id}")->assertOk();
        $this->deleteJson("/api/v1/locations/{$location->id}")->assertNotFound();
    }

    public function test_an_id_that_is_not_a_number_is_a_plain_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson('/api/v1/locations/abc', $this->payload())->assertNotFound();
        $this->deleteJson('/api/v1/locations/abc')->assertNotFound();
    }

    /* ---------------------------------------------------------- activity log */

    public function test_each_write_is_logged_once_by_name(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/v1/locations', $this->payload())->json('data.id');
        $this->putJson("/api/v1/locations/{$id}", $this->payload(['name' => 'Port Depot']))->assertOk();
        $this->deleteJson("/api/v1/locations/{$id}")->assertOk();

        // One descriptive entry per write, and no generic `api_auto` one on top.
        $logs = ActivityLog::where('user_id', $user->id)->orderBy('id')->get();

        $this->assertCount(3, $logs);
        $this->assertSame(['created', 'Added location "Dhaka Warehouse"'], [$logs[0]->action, $logs[0]->description]);
        $this->assertSame(['updated', 'Updated location "Port Depot"'], [$logs[1]->action, $logs[1]->description]);
        $this->assertSame(['name'], $logs[1]->properties['fields']);
        $this->assertSame(['deleted', 'Deleted location "Port Depot"'], [$logs[2]->action, $logs[2]->description]);
    }
}
