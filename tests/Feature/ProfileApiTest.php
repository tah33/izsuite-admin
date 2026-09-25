<?php

namespace Tests\Feature;

use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use RefreshDatabase;

    /* ---------------------------------------------------------- show */

    public function test_profile_requires_a_token(): void
    {
        $this->getJson('/api/v1/profile')->assertUnauthorized();
        $this->putJson('/api/v1/profile', ['first_name' => 'Nobody'])->assertUnauthorized();
    }

    public function test_profile_returns_the_signed_in_user(): void
    {
        $user = User::factory()->create(['first_name' => 'Ayesha', 'last_name' => 'Rahman']);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'first_name', 'last_name', 'email', 'role', 'status', 'last_login_at', 'created_at'],
            ])
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.first_name', 'Ayesha')
            ->assertJsonPath('user.last_name', 'Rahman')
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.role.slug', 'user')
            ->assertJsonMissingPath('user.password');
    }

    /* ---------------------------------------------------------- update */

    public function test_profile_update_saves_the_name(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/profile', ['first_name' => 'Nadia', 'last_name' => 'Islam'])
            ->assertOk()
            ->assertJsonPath('message', 'Profile updated successfully.')
            ->assertJsonPath('user.first_name', 'Nadia')
            ->assertJsonPath('user.last_name', 'Islam');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'first_name' => 'Nadia', 'last_name' => 'Islam']);
    }

    public function test_last_name_can_be_cleared(): void
    {
        $user = User::factory()->create(['last_name' => 'Rahman']);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/profile', ['first_name' => $user->first_name, 'last_name' => ''])
            ->assertOk()
            ->assertJsonPath('user.last_name', null);
    }

    public function test_first_name_is_required(): void
    {
        $user = User::factory()->create(['first_name' => 'Ayesha']);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/profile', ['first_name' => '', 'last_name' => 'Rahman'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('first_name');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'first_name' => 'Ayesha']);
    }

    public function test_email_cannot_be_changed_through_the_profile(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.test']);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/profile', ['first_name' => 'Owner', 'email' => 'someone-else@example.test'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'cannot be changed']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'owner@example.test']);
    }

    public function test_update_is_logged_once_with_the_changed_fields(): void
    {
        $user = User::factory()->create(['first_name' => 'Ayesha', 'last_name' => 'Rahman']);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/profile', ['first_name' => 'Nadia', 'last_name' => 'Rahman'])->assertOk();

        // One descriptive entry, and no generic `api_auto` one on top of it.
        $logs = ActivityLog::where('user_id', $user->id)->get();

        $this->assertCount(1, $logs);
        $this->assertSame('Updated their profile', $logs[0]->description);
        $this->assertSame(['first_name'], $logs[0]->properties['fields']);
    }

    public function test_fields_outside_the_form_are_ignored(): void
    {
        $user  = User::factory()->create(['status' => 'active']);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/profile', [
            'first_name' => 'Nadia',
            'status'     => 'inactive',
            'role_id'    => 999,
        ])->assertOk();

        $fresh = $user->fresh();

        $this->assertSame('Nadia', $fresh->first_name);
        $this->assertSame('active', $fresh->status);
        $this->assertSame($user->role_id, $fresh->role_id);
    }
}
