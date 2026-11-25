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
 * My Profile in the admin panel: the signed-in admin's own name, email and
 * profile image.
 */
class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('public');

        // An Admin: deleting their own account takes admin.profile.destroy, which Staff lacks.
        $this->me = User::factory()->create([
            'role_id'    => Role::where('slug', 'admin')->value('id'),
            'first_name' => 'Nadia',
            'last_name'  => null,
            'email'      => 'nadia@example.com',
        ]);
    }

    private function save(array $extra = [])
    {
        return $this->actingAs($this->me)->put(route('admin.profile.update'), [
            'first_name' => 'Nadia',
            'email'      => 'nadia@example.com',
            ...$extra,
        ]);
    }

    public function test_my_profile_image_can_be_added_kept_replaced_and_removed(): void
    {
        $this->save(['avatar' => UploadedFile::fake()->image('me.png', 200, 200)])->assertSessionHasNoErrors();
        $first  = $this->me->fresh()->avatar;
        $this->assertStringStartsWith('avatars/', $first);
        Storage::disk('public')->assertExists($first);

        // The picker shows it, and so does the top bar.
        $this->get(route('admin.profile'))
            ->assertOk()
            ->assertSee('Current profile image')
            ->assertSee('<img src="'.asset('storage/'.$first).'" alt="Nadia"', false);

        // Saved without a new image - an empty file input arrives as null - it stays.
        $this->save(['avatar' => null, 'remove_avatar' => '0'])->assertSessionHasNoErrors();
        $this->assertSame($first, $this->me->fresh()->avatar);
        Storage::disk('public')->assertExists($first);

        // A new one takes the old one's place, which goes.
        $this->save(['avatar' => UploadedFile::fake()->image('new.jpg')])->assertSessionHasNoErrors();
        $second = $this->me->fresh()->avatar;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertExists($second);
        Storage::disk('public')->assertMissing($first);

        $this->save(['avatar' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')])->assertSessionHasErrors('avatar');
        $this->assertSame($second, $this->me->fresh()->avatar);

        $this->save(['avatar' => null, 'remove_avatar' => '1'])->assertSessionHasNoErrors();
        $this->assertNull($this->me->fresh()->avatar);
        Storage::disk('public')->assertMissing($second);

        // No image: the top bar shows the first letter of the name again.
        $this->get(route('admin.profile'))->assertOk()->assertDontSee('alt="Nadia"', false);
    }

    public function test_deleting_my_account_deletes_my_profile_image(): void
    {
        $this->save(['avatar' => UploadedFile::fake()->image('me.png')])->assertSessionHasNoErrors();
        $path = $this->me->fresh()->avatar;

        $this->actingAs($this->me)->delete(route('admin.profile.destroy'), ['password' => 'password'])->assertRedirect(route('login'));

        $this->assertNull(User::find($this->me->id));
        Storage::disk('public')->assertMissing($path);
    }
}
