<?php

namespace Tests\Feature;

use App\Models\Admin\Role;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffApiTest extends TestCase
{
    use RefreshDatabase;

    private function accountWith(string $slug, array $overrides = []): User
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug), 'permissions' => null]);

        return User::factory()->create(array_merge(['role_id' => $role->id, 'status' => 'active'], $overrides));
    }

    public function test_the_staff_list_requires_a_token(): void
    {
        $this->accountWith('staff');

        $this->getJson('/api/v1/staff')->assertUnauthorized();
    }

    public function test_it_is_empty_when_there_is_no_staff(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/staff')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_it_lists_only_active_staff_accounts_by_name(): void
    {
        $zed   = $this->accountWith('staff', ['first_name' => 'Zed', 'last_name' => 'Zaman']);
        $amina = $this->accountWith('staff', ['first_name' => 'Amina', 'last_name' => 'Akter']);

        $this->accountWith('staff', ['first_name' => 'Off', 'last_name' => 'Duty', 'status' => 'inactive']);
        $this->accountWith('admin', ['first_name' => 'Ad', 'last_name' => 'Min']);
        User::factory()->create(['first_name' => 'Cus', 'last_name' => 'Tomer']);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/v1/staff')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // The staff role and not switched off - not a customer, not an admin.
        $this->assertSame(
            [['id' => $amina->id, 'name' => 'Amina Akter'], ['id' => $zed->id, 'name' => 'Zed Zaman']],
            $response->json('data'),
        );
    }

    public function test_only_an_id_and_a_name_go_out(): void
    {
        $this->accountWith('staff', ['email' => 'private@izsuite.test', 'phone' => '+8801711111111']);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/v1/staff')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name']]]);

        $this->assertSame(['id', 'name'], array_keys($response->json('data.0')));
        $this->assertStringNotContainsString('private@izsuite.test', $response->getContent());
        $this->assertStringNotContainsString('+8801711111111', $response->getContent());
    }
}
