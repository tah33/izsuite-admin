<?php

namespace Tests\Feature;

use App\Models\Admin\Role;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin panel's Staff page: admin staff only. Clients - the accounts that
 * signed up on the website - have their own page.
 */
class AdminStaffTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID, 'first_name' => 'Root', 'last_name' => 'Admin']);
    }

    private function roleId(string $slug): int
    {
        return Role::where('slug', $slug)->value('id');
    }

    public function test_the_list_shows_staff_but_not_clients(): void
    {
        User::factory()->create(['first_name' => 'Karim', 'last_name' => 'Staff', 'role_id' => $this->roleId('staff')]);
        User::factory()->create(['first_name' => 'Rahim', 'last_name' => 'Client']);

        $this->actingAs($this->admin)
            ->get(route('admin.staff.index'))
            ->assertOk()
            ->assertSee('Karim Staff')
            ->assertDontSee('Rahim Client');
    }

    public function test_a_client_cannot_be_opened_or_changed_as_staff(): void
    {
        $client = User::factory()->create();

        $this->actingAs($this->admin);

        $this->get(route('admin.staff.edit', $client->id))->assertNotFound();
        $this->patchJson(route('admin.staff.toggle-status', $client->id))->assertForbidden();
        $this->deleteJson(route('admin.staff.destroy', $client->id))->assertForbidden();

        $this->assertSame('active', $client->fresh()->status);
    }

    public function test_a_staff_member_is_given_a_staff_role_only(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('admin.staff.create'))
            ->assertOk()
            ->assertViewHas('roles', fn ($roles) => $roles->pluck('slug')->intersect(Role::FRONTEND_SLUGS)->isEmpty());

        $store = fn (string $role) => $this->postJson(route('admin.staff.store'), [
            'first_name'            => 'Nadia',
            'email'                 => "nadia.{$role}@example.com",
            'password'              => 'Secret#1234',
            'password_confirmation' => 'Secret#1234',
            'role_id'               => $this->roleId($role),
        ]);

        $store('user')->assertJsonValidationErrors('role_id');
        $store('staff')->assertOk();
    }
}
