<?php

namespace Tests\Feature;

use App\Models\Admin\Role;
use App\Models\User\User;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin panel's Clients: the accounts that signed up on the website, and
 * each one's businesses.
 */
class AdminClientsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID, 'first_name' => 'Root', 'last_name' => 'Admin']);
    }

    private function client(string $name, string $business): User
    {
        $client = User::factory()->create(['first_name' => $name, 'last_name' => 'Client']);
        $client->workspaces()->create(['name' => $business]);

        return $client;
    }

    private function staff(string $name): User
    {
        return User::factory()->create(['first_name' => $name, 'last_name' => 'Staff', 'role_id' => Role::where('slug', 'staff')->value('id')]);
    }

    public function test_the_list_shows_clients_and_their_businesses_but_not_staff(): void
    {
        $this->client('Rahim', 'Acme Traders');
        $this->staff('Karim');

        $this->actingAs($this->admin)
            ->get(route('admin.clients.index'))
            ->assertOk()
            ->assertSee('Rahim Client')
            ->assertSee('Acme Traders')
            ->assertDontSee('Karim Staff');
    }

    public function test_the_list_is_searched_by_a_business_name(): void
    {
        $this->client('Rahim', 'Acme Traders');
        $this->client('Selim', 'Bolt Foods');

        $this->actingAs($this->admin)
            ->get(route('admin.clients.index', ['search' => 'Bolt']))
            ->assertOk()
            ->assertSee('Selim Client')
            ->assertDontSee('Rahim Client');
    }

    public function test_a_clients_page_shows_their_businesses_and_staff_have_none(): void
    {
        $client = $this->client('Rahim', 'Acme Traders');
        $staff  = $this->staff('Karim');

        $this->actingAs($this->admin);

        $this->get(route('admin.clients.show', $client->id))->assertOk()->assertSee('Acme Traders');
        $this->get(route('admin.clients.show', $staff->id))->assertNotFound();
    }

    public function test_a_users_page_counts_what_their_businesses_hold(): void
    {
        $client = $this->client('Rahim', 'Acme Traders');
        $client->workspaces()->create(['name' => 'Bolt Foods']);

        // What every new business is given: its Main Location and its owner on its staff.
        foreach ($client->workspaces()->get() as $workspace) {
            app(SetUpNewWorkspace::class)->handle($workspace, $client, 'api');
        }

        $this->actingAs($this->admin)
            ->get(route('admin.clients.show', $client->id))
            ->assertOk()
            ->assertViewHas('totals', ['businesses' => 2, 'staff' => 2, 'locations' => 2, 'contacts' => 0, 'items' => 0, 'storage' => 0])
            ->assertSee('1 location');
    }
}
