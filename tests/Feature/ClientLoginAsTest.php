<?php

namespace Tests\Feature;

use App\Models\Admin\Role;
use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * "Login as" on the admin panel's Clients page: a one-time link to the
 * website, traded there for a session as the client.
 */
class ClientLoginAsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        config(['app.frontend_url' => 'https://app.example.test/']);

        $this->admin = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID, 'first_name' => 'Root', 'last_name' => 'Admin']);
    }

    /** The one-time code in the link the admin's "Login as" sent them to. */
    private function codeFor(User $client): string
    {
        $link = $this->actingAs($this->admin)
            ->post(route('admin.clients.login-as', $client->id))
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringStartsWith('https://app.example.test/login-as?code=', $link);

        // The website calls the API with no admin session of its own.
        $this->app['auth']->forgetGuards();

        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

        return $query['code'];
    }

    private function redeem(string $code): TestResponse
    {
        return $this->postJson('/api/v1/auth/login-as', ['code' => $code]);
    }

    public function test_the_link_signs_in_as_the_client_once(): void
    {
        $client = User::factory()->create();
        $code   = $this->codeFor($client);

        $token  = $this->redeem($code)
            ->assertOk()
            ->assertJsonPath('user.id', $client->id)
            ->assertJsonPath('token_type', 'Bearer')
            ->json('token');

        // An ordinary session as the client...
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('user.id', $client->id);

        // ...which the same link cannot start a second time.
        $this->redeem($code)->assertUnauthorized();
    }

    public function test_its_session_carries_the_way_back_to_the_admin_panel(): void
    {
        $client = User::factory()->create();

        $token  = $this->redeem($this->codeFor($client))
            ->assertOk()
            ->assertJsonPath('login_as.back_url', route('admin.overview'))
            ->json('token');

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('login_as.back_url', route('admin.overview'));

        // The client's own session has no "Back to admin" button to show.
        $this->app['auth']->forgetGuards();
        $own = $client->createToken('izsuite-web')->plainTextToken;

        $this->withToken($own)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('login_as', null);
    }

    public function test_the_way_back_is_to_the_host_the_admin_is_signed_in_on(): void
    {
        // The admin panel on one host, the API the website calls on another.
        URL::forceRootUrl('http://127.0.0.1:8000');
        $code = $this->codeFor(User::factory()->create());
        URL::forceRootUrl('http://izsuite-admin.test:8080');

        $token = $this->redeem($code)
            ->assertJsonPath('login_as.back_url', 'http://127.0.0.1:8000/admin/overview')
            ->json('token');

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertJsonPath('login_as.back_url', 'http://127.0.0.1:8000/admin/overview');
    }

    public function test_it_is_in_the_clients_activity_log_and_is_not_their_own_login(): void
    {
        $client = User::factory()->create(['last_login_at' => null]);

        $this->redeem($this->codeFor($client))->assertOk();

        $log    = ActivityLog::where('user_id', $client->id)->where('action', 'login')->sole();
        $this->assertSame('Signed in from the admin panel', $log->description);
        $this->assertSame($this->admin->id, $log->properties['admin_id']);
        $this->assertNull($client->fresh()->last_login_at);
    }

    public function test_a_link_works_for_a_minute_only(): void
    {
        $code = $this->codeFor(User::factory()->create());

        $this->travel(61)->seconds();

        $this->redeem($code)->assertUnauthorized();
    }

    public function test_a_client_who_could_not_sign_in_themselves_cannot_be_signed_in_to(): void
    {
        $this->actingAs($this->admin);

        $this->postJson(route('admin.clients.login-as', User::factory()->unverified()->create()->id))
            ->assertStatus(422)
            ->assertJsonPath('message', 'This user has not verified their email yet.');

        $this->postJson(route('admin.clients.login-as', User::factory()->create(['status' => 'inactive'])->id))
            ->assertStatus(422)
            ->assertJsonPath('message', 'This user\'s account is not active.');
    }

    public function test_only_a_client_can_be_signed_in_to(): void
    {
        $staff = User::factory()->create(['role_id' => Role::where('slug', 'staff')->value('id')]);

        $this->actingAs($this->admin)->post(route('admin.clients.login-as', $staff->id))->assertNotFound();
    }

    public function test_the_code_is_refused_in_the_query_string(): void
    {
        $this->postJson('/api/v1/auth/login-as?code=abc', ['code' => 'abc'])->assertStatus(400);
    }

    public function test_the_list_offers_it_to_clients_who_can_sign_in(): void
    {
        $verified   = User::factory()->create();
        $unverified = User::factory()->unverified()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.clients.index'))
            ->assertOk()
            ->assertSee(route('admin.clients.login-as', $verified->id))
            ->assertDontSee(route('admin.clients.login-as', $unverified->id));
    }
}
