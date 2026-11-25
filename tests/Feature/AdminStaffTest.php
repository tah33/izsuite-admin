<?php

namespace Tests\Feature;

use App\Models\Admin\Role;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            'password'              => 'Secret#123456',
            'password_confirmation' => 'Secret#123456',
            'role_id'               => $this->roleId($role),
        ]);

        $store('user')->assertJsonValidationErrors('role_id');
        $store('staff')->assertOk();
    }

    public function test_a_staff_member_has_a_profile_image_that_can_be_kept_replaced_and_removed(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin);

        $this->postJson(route('admin.staff.store'), [
            'first_name'            => 'Nadia',
            'email'                 => 'nadia@example.com',
            'password'              => 'Secret#123456',
            'password_confirmation' => 'Secret#123456',
            'role_id'               => $this->roleId('staff'),
            'avatar'                => UploadedFile::fake()->image('nadia.png', 200, 200),
        ])->assertOk();

        $staff  = User::where('email', 'nadia@example.com')->firstOrFail();
        $first  = $staff->avatar;
        $this->assertStringStartsWith('avatars/', $first);
        Storage::disk('public')->assertExists($first);

        $this->get(route('admin.staff.edit', $staff->id))->assertOk()->assertSee('storage/'.$first);
        $this->get(route('admin.staff.index'))->assertOk()->assertSee('storage/'.$first);

        $update = fn (array $extra = []) => $this->putJson(route('admin.staff.update', $staff->id), [
            'first_name' => 'Nadia',
            'email'      => 'nadia@example.com',
            'role_id'    => $this->roleId('staff'),
            ...$extra,
        ]);

        // Saved without a new image - an empty file input arrives as null - the old one stays.
        $update(['avatar' => null, 'remove_avatar' => '0'])->assertOk();
        $this->assertSame($first, $staff->fresh()->avatar);
        Storage::disk('public')->assertExists($first);

        // A new one takes the old one's place, which goes.
        $update(['avatar' => UploadedFile::fake()->image('new.jpg')])->assertOk();
        $second = $staff->fresh()->avatar;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertExists($second);
        Storage::disk('public')->assertMissing($first);

        $update(['avatar' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')])->assertJsonValidationErrors('avatar');
        $update(['avatar' => UploadedFile::fake()->create('logo.svg', 1, 'image/svg+xml')])->assertJsonValidationErrors('avatar');
        $this->assertSame($second, $staff->fresh()->avatar);

        $update(['avatar' => null, 'remove_avatar' => '1'])->assertOk();
        $this->assertNull($staff->fresh()->avatar);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_deleting_a_staff_member_deletes_their_profile_image(): void
    {
        Storage::fake('public');
        $path  = UploadedFile::fake()->image('karim.png')->store('avatars', 'public');
        $staff = User::factory()->create(['role_id' => $this->roleId('staff'), 'avatar' => $path]);

        $this->actingAs($this->admin)->deleteJson(route('admin.staff.destroy', $staff->id))->assertOk();

        $this->assertNull(User::find($staff->id));
        Storage::disk('public')->assertMissing($path);
    }
}
