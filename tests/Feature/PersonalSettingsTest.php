<?php

namespace Tests\Feature;

use App\Models\Admin\Language;
use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use App\Models\User\WorkspaceMember;
use App\Support\SupportAccess;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use App\Support\DeviceName;
use App\Support\Permissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Personal settings and active sessions (Doc 12 sections 22 and 23) - Doc 12
 * Phase E1: a user's name, phone, language, time zone and profile image are
 * their own, the same in every business; they see the devices signed in to
 * their account and sign any other one out; and nobody changes the role
 * they hold.
 */
class PersonalSettingsTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const CHROME  = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';

    private const FIREFOX = 'Mozilla/5.0 (X11; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    /** $user as a member of another owner's business, as its admin entered them. */
    private function memberElsewhere(User $user, array $details = []): WorkspaceMember
    {
        $business = $this->businessOf(User::factory()->create());
        $role     = $business->roles()->create(['name' => 'Desk', 'permissions' => ['dashboard.view']]);

        return $business->members()->create([
            'user_id' => $user->id, 'role_id' => $role->id, 'name' => 'R. Uddin', 'email' => $user->email,
            'status'  => WorkspaceMember::ACTIVE, 'accepted_at' => now(), ...$details,
        ]);
    }

    /** Signs $user in through the API from $agent at $ip: the plain-text token. */
    private function signInFrom(User $user, string $agent, string $ip): string
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->withHeader('User-Agent', $agent)
            ->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->json('token');
    }

    /** The next request with $token alone - the guard forgets the last one's. */
    private function using(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function idOf(string $token): int
    {
        return (int) explode('|', $token)[0];
    }

    public function test_my_details_are_mine_in_every_business(): void
    {
        Language::create(['name' => 'Bangla', 'code' => 'bn']);
        $user    = $this->signIn(User::factory()->create(['first_name' => 'Rahim', 'last_name' => 'Uddin']));
        $there   = $this->memberElsewhere($user, ['job_title' => 'Cashier', 'timezone' => 'Europe/London']);
        $removed = $this->memberElsewhere($user, ['status' => WorkspaceMember::REMOVED, 'removed_at' => now()]);

        $this->putJson('/api/v1/profile', ['first_name' => 'Rahim', 'last_name' => 'Hossain', 'phone' => '+880 1711 000000', 'language' => 'bn', 'timezone' => 'Asia/Dhaka'])
            ->assertOk()
            ->assertJsonPath('data.phone', '+880 1711 000000')
            ->assertJsonPath('data.language', 'bn')
            ->assertJsonPath('data.timezone', 'Asia/Dhaka');

        $there->refresh();
        $this->assertSame(['Rahim Hossain', '+880 1711 000000', 'bn', 'Asia/Dhaka', 'Cashier'], [$there->name, $there->phone, $there->language, $there->timezone, $there->job_title]);
        $this->assertSame([null, 'R. Uddin'], [$removed->fresh()->timezone, $removed->fresh()->name]);

        // What the user leaves alone stays as each business has it.
        $there->update(['timezone' => 'Europe/London']);
        $this->putJson('/api/v1/profile', ['first_name' => 'Rahim', 'last_name' => 'Uddin'])->assertOk()->assertJsonPath('data.timezone', 'Asia/Dhaka');
        $this->assertSame(['Rahim Uddin', 'Europe/London'], [$there->fresh()->name, $there->fresh()->timezone]);

        // Empty is each business's own time zone; nothing else is taken.
        $this->putJson('/api/v1/profile', ['first_name' => 'Rahim', 'timezone' => null])->assertOk()->assertJsonPath('data.timezone', null);
        $this->assertNull($there->fresh()->timezone);
        $this->putJson('/api/v1/profile', ['first_name' => 'Rahim', 'language' => 'xx', 'timezone' => 'Mars/Olympus'])->assertJsonValidationErrors(['language', 'timezone']);
    }

    public function test_a_profile_image_can_be_changed_and_removed(): void
    {
        Storage::fake('public');
        $user   = $this->signIn();

        $this->postJson('/api/v1/profile/image', ['image' => UploadedFile::fake()->image('me.png', 200, 200)])->assertOk()->assertJsonPath('message', 'Profile image saved.');
        $first  = $user->fresh()->avatar;
        Storage::disk('public')->assertExists($first);
        $this->assertStringEndsWith($first, $this->getJson('/api/v1/profile')->json('data.avatar_url'));

        // A new one takes the old one's place, which goes.
        $this->postJson('/api/v1/profile/image', ['image' => UploadedFile::fake()->image('me.jpg')])->assertOk();
        Storage::disk('public')->assertMissing($first);

        $this->postJson('/api/v1/profile/image', ['image' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')])->assertJsonValidationErrors('image');

        $latest = $user->fresh()->avatar;
        $this->deleteJson('/api/v1/profile/image')->assertOk()->assertJsonPath('data.avatar_url', null);
        Storage::disk('public')->assertMissing($latest);
        $this->assertSame('Removed their profile image', ActivityLog::where('user_id', $user->id)->latest('id')->value('description'));
    }

    public function test_i_see_my_devices_and_sign_any_other_one_out(): void
    {
        $user    = User::factory()->create();
        $chrome  = $this->signInFrom($user, self::CHROME, '203.0.113.7');
        $firefox = $this->signInFrom($user, self::FIREFOX, '198.51.100.20');
        $user->createToken(SupportAccess::TOKEN_PREFIX.'1', ['*'], now()->addHour());
        $user->createToken('izsuite-frontend', ['*'], now()->subMinute());
        $other   = User::factory()->create()->createToken('izsuite-frontend');

        // The live ones, this one marked; an expired one and another user's are not there.
        $rows    = collect($this->using($chrome)->getJson('/api/v1/profile/sessions')->assertOk()->json('data'));
        $this->assertCount(3, $rows);
        $this->assertSame(['Chrome on Windows', '203.0.113.7', false], [$rows->firstWhere('current', true)['device'], $rows->firstWhere('current', true)['ip_address'], $rows->firstWhere('current', true)['support']]);
        $this->assertSame(['Firefox on Linux', '198.51.100.20'], [$rows->firstWhere('id', $this->idOf($firefox))['device'], $rows->firstWhere('id', $this->idOf($firefox))['ip_address']]);
        $this->assertNull($rows->firstWhere('support', true)['device']);

        $this->using($chrome)->postJson("/api/v1/profile/sessions/{$this->idOf($firefox)}/sign-out")->assertOk();
        $this->assertNull(PersonalAccessToken::find($this->idOf($firefox)));
        $this->assertSame('Signed out a device: Firefox on Linux, 198.51.100.20', ActivityLog::where('user_id', $user->id)->latest('id')->value('description'));
        $this->using($firefox)->getJson('/api/v1/profile/sessions')->assertUnauthorized();

        // The device in hand signs out from the menu; another user's is not found.
        $this->using($chrome)->postJson("/api/v1/profile/sessions/{$this->idOf($chrome)}/sign-out")->assertStatus(409);
        $this->using($chrome)->postJson("/api/v1/profile/sessions/{$other->accessToken->id}/sign-out")->assertNotFound();

        $this->using($chrome)->postJson('/api/v1/profile/sessions/sign-out-others')->assertOk()->assertJsonPath('message', 'Every other device is signed out.');
        $this->assertSame([$this->idOf($chrome)], $user->tokens()->pluck('id')->all());
        $this->assertNotNull(PersonalAccessToken::find($other->accessToken->id));
    }

    public function test_devices_are_named_from_their_browsers(): void
    {
        $this->assertSame('Chrome on Windows', DeviceName::from(self::CHROME));
        $this->assertSame('Safari on iPhone', DeviceName::from('Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1'));
        $this->assertSame('Edge on Windows', DeviceName::from('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 Edg/130.0.0.0'));
        $this->assertSame('Chrome on Android', DeviceName::from('Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36'));
        $this->assertSame('Safari on macOS', DeviceName::from('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15'));
        $this->assertNull(DeviceName::from('curl/8.4.0'));
        $this->assertNull(DeviceName::from(null));
    }

    public function test_nobody_changes_the_role_they_hold(): void
    {
        $owner    = $this->signIn();
        $business = $this->businessOf($owner);
        app(SetUpNewWorkspace::class)->addRoles($business, $owner, 'api');
        $managers = $business->roles()->create(['name' => 'Managers', 'permissions' => Permissions::expand(['settings.view', 'settings.users'])]);
        $viewers  = $business->roles()->where('template', 'view_only')->sole();
        $member   = User::factory()->create();
        $business->members()->create(['user_id' => $member->id, 'role_id' => $managers->id, 'name' => $member->name, 'email' => $member->email, 'status' => WorkspaceMember::ACTIVE]);

        Sanctum::actingAs($member);
        $pick     = $this->pick($business);

        $this->getJson("/api/v1/roles/{$managers->id}", $pick)->assertOk()->assertJsonPath('data.yours', true);
        $this->putJson("/api/v1/roles/{$managers->id}", ['name' => 'Managers', 'permissions' => [...$managers->permissions, 'reports.view']], $pick)
            ->assertForbidden()
            ->assertJsonPath('message', 'You cannot change the role you hold. Someone with another role can.');
        $this->assertNotContains('reports.view', $managers->fresh()->permissions);

        $this->getJson("/api/v1/roles/{$viewers->id}", $pick)->assertJsonPath('data.yours', false);
        $this->putJson("/api/v1/roles/{$viewers->id}", ['name' => $viewers->name, 'permissions' => $viewers->permissions], $pick)->assertOk();

        // The owner, whose own is Business Owner, changes any other.
        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/roles/{$managers->id}", ['name' => 'Managers', 'permissions' => [...$managers->permissions, 'reports.view']], $pick)
            ->assertOk()
            ->assertJsonPath('data.yours', false);
    }
}
