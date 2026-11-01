<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveWorkspace;
use App\Models\Billing\Package;
use App\Models\Shared\ActivityLog;
use App\Models\User\Location;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Support\LocationCodes;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocationsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded with the others, super-admin keeps id 1 - a "user" role made
        // first by the factory would take it, and every account would pass for
        // an admin, whose activity is not logged.
        $this->seed(RoleSeeder::class);

        // These are about adding locations, not about the plan's limit on them (see PlanLimitsTest).
        Package::where('key', Package::STARTER)->update(['location_limit' => null]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge(['name' => 'Dhaka Warehouse', 'code' => 'DHK', 'country' => 'BD', 'state' => 'Dhaka', 'timezone' => 'Asia/Dhaka'], $overrides);
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

    /** A location made straight in the table - its code from its name, unless given. */
    private function locationOf(User $user, array $overrides = []): Location
    {
        $data = $this->payload($overrides);

        return $this->businessOf($user)->locations()->create([...$data, 'code' => $overrides['code'] ?? LocationCodes::from($data['name']), 'user_id' => $user->id]);
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
        $this->signIn(User::factory()->create());

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

        $this->signIn($user);

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
        $this->signIn($user);

        $this->postJson('/api/v1/locations', $this->payload())
            ->assertCreated()
            ->assertJsonPath('message', 'Location added successfully.')
            ->assertJsonPath('data.name', 'Dhaka Warehouse')
            ->assertJsonPath('data.country', 'BD')
            ->assertJsonPath('data.state', 'Dhaka')
            ->assertJsonStructure(['data' => ['id']]);

        $this->assertDatabaseHas('locations', [
            'user_id'      => $user->id,
            'workspace_id' => $this->businessOf($user)->id,
            'name'         => 'Dhaka Warehouse',
            'country'      => 'BD',
            'state'        => 'Dhaka',
        ]);
    }

    public function test_an_account_can_have_several_locations_with_the_same_name(): void
    {
        $user = User::factory()->create();
        $this->signIn($user);

        $this->postJson('/api/v1/locations', $this->payload())->assertCreated();
        $this->postJson('/api/v1/locations', $this->payload(['code' => 'DHK2', 'state' => 'Chattogram']))->assertCreated();

        $this->assertSame(2, $user->locations()->count());
    }

    public function test_a_user_id_in_the_request_cannot_give_the_location_to_someone_else(): void
    {
        $victim   = User::factory()->create();
        $attacker = User::factory()->create();
        $this->signIn($attacker);

        $this->postJson('/api/v1/locations', $this->payload(['user_id' => $victim->id]))->assertCreated();

        $this->assertSame(0, $victim->locations()->count());
        $this->assertSame(1, $attacker->locations()->count());
    }

    public function test_a_name_code_country_and_time_zone_are_required(): void
    {
        $user = User::factory()->create();
        $this->signIn($user);

        $this->postJson('/api/v1/locations', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'code', 'country', 'timezone'])
            ->assertJsonMissingValidationErrors(['state', 'city', 'phone', 'email', 'address_line_1', 'postal_code', 'document_header']);

        $this->assertDatabaseCount('locations', 0);

        // Those four are a whole location, with no address yet.
        $this->postJson('/api/v1/locations', ['name' => 'Main Location', 'code' => 'MAIN', 'country' => 'BD', 'timezone' => 'Asia/Dhaka'])
            ->assertCreated()
            ->assertJsonPath('data.city', null)
            ->assertJsonPath('data.state', null)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('locations', ['user_id' => $user->id, 'name' => 'Main Location', 'code' => 'MAIN', 'country' => 'BD', 'state' => null]);
    }

    public function test_values_of_only_spaces_count_as_empty(): void
    {
        $this->signIn(User::factory()->create());

        $this->postJson('/api/v1/locations', $this->payload(['name' => '   ']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        // An optional field of nothing but spaces is simply empty.
        $this->postJson('/api/v1/locations', $this->payload(['state' => "\t", 'city' => ' ']))
            ->assertCreated()
            ->assertJsonPath('data.state', null)
            ->assertJsonPath('data.city', null);
    }

    public function test_values_respect_their_column_lengths(): void
    {
        $this->signIn(User::factory()->create());

        $this->postJson('/api/v1/locations', $this->payload([
            'name'        => str_repeat('a', 256),
            'code'        => str_repeat('A', 11),
            'state'       => str_repeat('a', 101),
            'postal_code' => str_repeat('1', 21),
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'code', 'state', 'postal_code']);

        // The longest values the columns hold are accepted - and the activity
        // log entry that names the location must still fit its own column.
        $this->postJson('/api/v1/locations', $this->payload([
            'name'  => str_repeat('a', 255),
            'code'  => str_repeat('A', 10),
            'state' => str_repeat('a', 100),
        ]))->assertCreated();

        $this->assertLessThanOrEqual(255, strlen(ActivityLog::latest('id')->value('description')));
    }

    public function test_a_country_is_one_from_the_list_and_a_time_zone_a_real_one(): void
    {
        $this->signIn(User::factory()->create());

        $this->postJson('/api/v1/locations', $this->payload(['country' => 'Bangladesh', 'timezone' => 'Asia/Nowhere']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.country.0', 'Choose the country from the list.')
            ->assertJsonValidationErrors('timezone');
    }

    public function test_a_code_is_kept_in_capitals_and_unique_within_the_business(): void
    {
        $user = User::factory()->create();
        $this->signIn($user);

        $this->postJson('/api/v1/locations', $this->payload(['code' => ' dhk ']))->assertCreated()->assertJsonPath('data.code', 'DHK');

        $this->postJson('/api/v1/locations', $this->payload(['name' => 'Dhaka Outlet', 'code' => 'Dhk']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'Another of your locations has this code.');
        $this->postJson('/api/v1/locations', $this->payload(['code' => 'DH K']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'Use letters, digits and dashes only.');

        // A location keeps its own code on an update, and another business may use it.
        $id = $user->locations()->value('id');
        $this->putJson("/api/v1/locations/{$id}", $this->payload(['name' => 'Renamed']))->assertOk()->assertJsonPath('data.code', 'DHK');

        $second = $user->workspaces()->create(['name' => 'Second Business']);
        $this->postJson('/api/v1/locations', $this->payload(), [ResolveWorkspace::HEADER => (string) $second->id])->assertCreated();
    }

    /* ---------------------------------------------------------- update */

    public function test_update_changes_the_location(): void
    {
        $user     = User::factory()->create();
        $location = $this->locationOf($user);
        $this->signIn($user);

        $this->putJson("/api/v1/locations/{$location->id}", $this->payload([
            'name' => 'Port Depot', 'code' => 'PORT', 'state' => 'Chattogram', 'phone' => '+880 31 712345', 'email' => 'port@example.com',
            'address_line_1' => 'Plot 4, Port Road', 'city' => 'Chattogram', 'postal_code' => '4100', 'document_header' => "Port Depot\nOpen 9 to 6",
        ]))
            ->assertOk()
            ->assertJsonPath('message', 'Location updated successfully.')
            ->assertJsonPath('data.id', $location->id)
            ->assertJsonPath('data.name', 'Port Depot')
            ->assertJsonPath('data.code', 'PORT')
            ->assertJsonPath('data.state', 'Chattogram')
            ->assertJsonPath('data.document_header', "Port Depot\nOpen 9 to 6");

        $this->assertDatabaseHas('locations', ['id' => $location->id, 'name' => 'Port Depot', 'state' => 'Chattogram', 'city' => 'Chattogram', 'email' => 'port@example.com']);
        $this->assertDatabaseCount('locations', 1);
    }

    public function test_update_is_validated_like_a_create(): void
    {
        $user     = User::factory()->create();
        $location = $this->locationOf($user);
        $this->signIn($user);

        $this->putJson("/api/v1/locations/{$location->id}", $this->payload(['name' => '', 'state' => 'Chattogram']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->assertDatabaseHas('locations', ['id' => $location->id, 'name' => 'Dhaka Warehouse', 'state' => 'Dhaka']);
    }

    public function test_update_can_clear_the_optional_fields(): void
    {
        $user     = User::factory()->create();
        $location = $this->locationOf($user, ['city' => 'Dhaka', 'phone' => '+880 2 9876543']);
        $this->signIn($user);

        $this->putJson("/api/v1/locations/{$location->id}", $this->payload(['state' => '', 'city' => null, 'phone' => '']))
            ->assertOk()
            ->assertJsonPath('data.state', null)
            ->assertJsonPath('data.city', null)
            ->assertJsonPath('data.phone', null);

        $this->assertDatabaseHas('locations', ['id' => $location->id, 'state' => null, 'city' => null, 'phone' => null]);
    }

    public function test_a_user_id_in_an_update_cannot_move_the_location(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $location = $this->locationOf($owner);
        $this->signIn($owner);

        $this->putJson("/api/v1/locations/{$location->id}", $this->payload(['name' => 'Renamed', 'user_id' => $other->id]))->assertOk();

        $this->assertSame($owner->id, $location->fresh()->user_id);
        $this->assertSame('Renamed', $location->fresh()->name);
    }

    public function test_someone_elses_location_cannot_be_updated(): void
    {
        $owner    = User::factory()->create();
        $location = $this->locationOf($owner);
        $this->signIn(User::factory()->create());

        $this->putJson("/api/v1/locations/{$location->id}", $this->payload(['name' => 'Hijacked']))
            ->assertNotFound()
            ->assertExactJson(['message' => 'Location not found.']);

        $this->assertDatabaseHas('locations', ['id' => $location->id, 'name' => 'Dhaka Warehouse', 'user_id' => $owner->id]);
    }

    public function test_updating_a_location_that_does_not_exist_is_a_404(): void
    {
        $this->signIn(User::factory()->create());

        $this->putJson('/api/v1/locations/999999', $this->payload())
            ->assertNotFound()
            ->assertExactJson(['message' => 'Location not found.']);
    }

    /* ---------------------------------------------------------- primary and status */

    public function test_the_first_location_is_the_primary_and_the_primary_stays_active(): void
    {
        $user = User::factory()->create();
        $this->signIn($user);

        $first  = $this->postJson('/api/v1/locations', $this->payload())->assertCreated()->assertJsonPath('data.is_primary', true)->json('data.id');
        $second = $this->postJson('/api/v1/locations', $this->payload(['name' => 'Port Depot', 'code' => 'PORT']))->assertCreated()->assertJsonPath('data.is_primary', false)->json('data.id');

        // The Primary is neither turned off nor deleted: another is made Primary first.
        $this->postJson("/api/v1/locations/{$first}/deactivate")
            ->assertConflict()
            ->assertJsonPath('message', 'This is your Primary Location. Make another location the Primary before deactivating this one.');
        $this->deleteJson("/api/v1/locations/{$first}")
            ->assertConflict()
            ->assertJsonPath('message', 'This is your Primary Location. Make another location the Primary before deleting this one.');

        $this->postJson("/api/v1/locations/{$second}/primary")->assertOk()->assertJsonPath('message', 'Port Depot is now your Primary Location.');
        $this->assertSame([$second], $user->locations()->where('is_primary', true)->pluck('id')->all());

        $this->postJson("/api/v1/locations/{$first}/deactivate")->assertOk()->assertJsonPath('data.is_active', false);

        // Only an active location becomes the Primary; a turned-off one comes back on.
        $this->postJson("/api/v1/locations/{$first}/primary")->assertConflict()->assertJsonPath('message', 'Activate this location before making it the Primary.');
        $this->postJson("/api/v1/locations/{$first}/activate")->assertOk()->assertJsonPath('data.is_active', true);
        $this->postJson("/api/v1/locations/{$first}/primary")->assertOk();
        $this->assertSame([$first], $user->locations()->where('is_primary', true)->pluck('id')->all());
    }

    public function test_status_actions_are_only_for_the_businesss_own_locations(): void
    {
        $theirs = $this->locationOf(User::factory()->create());
        $this->signIn(User::factory()->create());

        foreach (['activate', 'deactivate', 'primary'] as $action) {
            $this->postJson("/api/v1/locations/{$theirs->id}/{$action}")->assertNotFound();
        }

        $this->assertTrue($theirs->fresh()->is_active);
    }

    /* ---------------------------------------------------------- delete */

    public function test_destroy_removes_the_location(): void
    {
        $user     = User::factory()->create();
        $location = $this->locationOf($user);
        $kept     = $this->locationOf($user, ['name' => 'Keep Me']);
        $this->signIn($user);

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
        $this->signIn(User::factory()->create());

        $this->deleteJson("/api/v1/locations/{$location->id}")
            ->assertNotFound()
            ->assertExactJson(['message' => 'Location not found.']);

        $this->assertDatabaseHas('locations', ['id' => $location->id]);
    }

    public function test_deleting_the_same_location_twice_is_a_404(): void
    {
        $user     = User::factory()->create();
        $location = $this->locationOf($user);
        $this->signIn($user);

        $this->deleteJson("/api/v1/locations/{$location->id}")->assertOk();
        $this->deleteJson("/api/v1/locations/{$location->id}")->assertNotFound();
    }

    public function test_an_id_that_is_not_a_number_is_a_plain_404(): void
    {
        $this->signIn(User::factory()->create());

        $this->putJson('/api/v1/locations/abc', $this->payload())->assertNotFound();
        $this->deleteJson('/api/v1/locations/abc')->assertNotFound();
    }

    /* ---------------------------------------------------------- one business at a time */

    public function test_each_business_keeps_its_own_locations(): void
    {
        $user = User::factory()->create();
        $this->locationOf($user, ['name' => 'Dhaka Warehouse']);   // in its first business

        $second = $user->workspaces()->create(['name' => 'Second Business']);
        $second->locations()->create(['user_id' => $user->id, 'name' => 'Port Depot']);

        $this->signIn($user);

        // No business picked: the oldest one's.
        $this->assertSame(['Dhaka Warehouse'], array_column($this->getJson('/api/v1/locations')->assertOk()->json('data'), 'name'));

        $this->assertSame(['Port Depot'], array_column(
            $this->getJson('/api/v1/locations', [ResolveWorkspace::HEADER => (string) $second->id])->assertOk()->json('data'),
            'name',
        ));
    }

    public function test_a_location_is_added_to_the_business_picked_in_the_header(): void
    {
        $user   = $this->signIn(User::factory()->create());
        $second = $user->workspaces()->create(['name' => 'Second Business']);

        $this->postJson('/api/v1/locations', $this->payload(), [ResolveWorkspace::HEADER => (string) $second->id])->assertCreated();

        $this->assertSame(1, $second->locations()->count());
        $this->assertSame(0, $this->businessOf($user)->locations()->count());
    }

    public function test_a_location_cannot_be_reached_from_another_business_of_the_same_owner(): void
    {
        $user     = User::factory()->create();
        $location = $this->locationOf($user);
        $second   = $user->workspaces()->create(['name' => 'Second Business']);
        $header   = [ResolveWorkspace::HEADER => (string) $second->id];
        $this->signIn($user);

        $this->putJson("/api/v1/locations/{$location->id}", $this->payload(['name' => 'Moved']), $header)
            ->assertNotFound()
            ->assertExactJson(['message' => 'Location not found.']);
        $this->deleteJson("/api/v1/locations/{$location->id}", [], $header)->assertNotFound();

        $this->assertDatabaseHas('locations', ['id' => $location->id, 'name' => 'Dhaka Warehouse', 'workspace_id' => $this->businessOf($user)->id]);
    }

    public function test_someone_elses_business_in_the_header_is_a_404(): void
    {
        $theirs = $this->businessOf(User::factory()->create());
        $this->signIn(User::factory()->create());

        $this->getJson('/api/v1/locations', [ResolveWorkspace::HEADER => (string) $theirs->id])
            ->assertNotFound()
            ->assertJsonPath('message', 'Workspace not found.');
    }

    public function test_an_account_with_no_workspace_is_asked_to_create_one(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/locations')
            ->assertNotFound()
            ->assertJsonPath('message', 'Create a workspace first.');
        $this->postJson('/api/v1/locations', $this->payload())->assertNotFound();

        $this->assertDatabaseCount('locations', 0);
    }

    /* ---------------------------------------------------------- activity log */

    public function test_each_write_is_logged_once_by_name(): void
    {
        $user = User::factory()->create();
        $this->locationOf($user, ['name' => 'Head Office', 'is_primary' => true]);
        $this->signIn($user);

        // Not the Primary - the Head Office is - so it can be deleted.
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
