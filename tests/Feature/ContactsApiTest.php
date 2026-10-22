<?php

namespace Tests\Feature;

use App\Models\Shared\ActivityLog;
use App\Models\User\Contact;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * A business's customers and vendors (Doc 01 section 10): one record per
 * contact, shared by every app of that business and no other.
 */
class ContactsApiTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded first, so super-admin holds id 1 and the owner is not taken for an admin.
        $this->seed(RoleSeeder::class);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'is_customer'  => true,
            'is_vendor'    => false,
            'name'         => 'Rahim Uddin',
            'company_name' => 'Uddin Traders',
            'email'        => 'rahim@example.com',
            'phone'        => '+880 1711 000000',
            'city'         => 'Dhaka',
            'country'      => 'Bangladesh',
        ], $overrides);
    }

    private function contactOf(User $user, array $overrides = []): Contact
    {
        return $this->businessOf($user)->contacts()->create($this->payload($overrides));
    }

    /* ---------------------------------------------------------- access */

    public function test_every_endpoint_needs_a_token(): void
    {
        $contact = $this->contactOf(User::factory()->create());

        $this->getJson('/api/v1/contacts')->assertUnauthorized();
        $this->postJson('/api/v1/contacts', $this->payload())->assertUnauthorized();
        $this->putJson("/api/v1/contacts/{$contact->id}", $this->payload(['name' => 'Hijacked']))->assertUnauthorized();
        $this->deleteJson("/api/v1/contacts/{$contact->id}")->assertUnauthorized();

        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'name' => 'Rahim Uddin']);
    }

    public function test_an_account_without_a_business_is_told_to_make_one(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/contacts')
            ->assertNotFound()
            ->assertJsonPath('message', 'Create a workspace first.');
        $this->postJson('/api/v1/contacts', $this->payload())->assertNotFound();

        $this->assertDatabaseCount('contacts', 0);
    }

    /* ---------------------------------------------------------- add */

    public function test_a_contact_is_added_to_the_business(): void
    {
        $user = $this->signIn();

        $response = $this->postJson('/api/v1/contacts', $this->payload(['is_vendor' => true, 'tax_number' => 'BIN-001']))
            ->assertCreated()
            ->assertJsonPath('message', 'Contact added successfully.')
            ->assertJsonPath('data.name', 'Rahim Uddin')
            ->assertJsonPath('data.is_customer', true)
            ->assertJsonPath('data.is_vendor', true)
            ->assertJsonPath('data.tax_number', 'BIN-001')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.notes', null);

        $this->assertSame([
            'id', 'is_customer', 'is_vendor', 'name', 'company_name', 'email', 'phone', 'address',
            'city', 'state', 'country', 'postal_code', 'tax_number', 'notes', 'is_active',
        ], array_keys($response->json('data')));

        $this->assertDatabaseHas('contacts', [
            'id'           => $response->json('data.id'),
            'workspace_id' => $this->businessOf($user)->id,
            'is_vendor'    => true,
        ]);
    }

    public function test_it_belongs_to_the_business_of_the_request_whatever_the_body_says(): void
    {
        $user   = $this->signIn();
        $other  = User::factory()->create();
        $theirs = $this->businessOf($other);

        $this->postJson('/api/v1/contacts', $this->payload(['workspace_id' => $theirs->id]))->assertCreated();

        $this->assertSame(1, $this->businessOf($user)->contacts()->count());
        $this->assertSame(0, $theirs->contacts()->count());
    }

    public function test_only_the_name_and_what_it_is_are_required(): void
    {
        $this->signIn();

        $this->postJson('/api/v1/contacts', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_customer', 'is_vendor', 'name'])
            ->assertJsonMissingValidationErrors(['email', 'phone', 'tax_number']);

        $this->postJson('/api/v1/contacts', ['is_customer' => true, 'is_vendor' => false, 'name' => 'Walk-in'])
            ->assertCreated()
            ->assertJsonPath('data.email', null);
    }

    public function test_a_contact_is_a_customer_a_vendor_or_both_never_neither(): void
    {
        $this->signIn();

        $this->postJson('/api/v1/contacts', $this->payload(['is_customer' => false, 'is_vendor' => false]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_customer' => 'Choose customer, vendor, or both.']);

        $this->postJson('/api/v1/contacts', $this->payload(['is_customer' => false, 'is_vendor' => true]))->assertCreated();
    }

    public function test_fields_are_checked_against_what_the_columns_hold(): void
    {
        $this->signIn();

        $this->postJson('/api/v1/contacts', $this->payload([
            'email'       => 'not-an-email',
            'phone'       => str_repeat('1', 31),
            'postal_code' => str_repeat('1', 21),
            'tax_number'  => str_repeat('A', 51),
            'name'        => str_repeat('A', 256),
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'phone', 'postal_code', 'tax_number', 'name']);

        $this->assertDatabaseCount('contacts', 0);
    }

    /* ---------------------------------------------------------- list */

    public function test_a_new_business_has_no_contacts(): void
    {
        $this->signIn();

        $this->getJson('/api/v1/contacts')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('pagination.total', 0)
            ->assertJsonPath('pagination.current_page', 1);
    }

    public function test_the_list_is_alphabetical_and_paginated(): void
    {
        $user = $this->signIn();

        foreach (['Zaman', 'Ahmed', 'Karim', 'Bose', 'Chowdhury', 'Das', 'Islam', 'Hasan', 'Faruk', 'Ghosh', 'Elahi'] as $name) {
            $this->contactOf($user, ['name' => $name]);
        }

        $this->getJson('/api/v1/contacts?per_page=10')
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.name', 'Ahmed')
            ->assertJsonPath('data.9.name', 'Karim')
            ->assertJsonPath('pagination.total', 11)
            ->assertJsonPath('pagination.last_page', 2)
            ->assertJsonPath('pagination.has_next_page', true);

        $this->getJson('/api/v1/contacts?per_page=10&page=2')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Zaman');

        // 15 a page unless asked; a size the list does not offer is refused.
        $this->getJson('/api/v1/contacts')->assertJsonPath('pagination.per_page', 15);
        $this->getJson('/api/v1/contacts?per_page=7')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    public function test_the_list_can_be_searched_and_filtered(): void
    {
        $user = $this->signIn();

        $this->contactOf($user, ['name' => 'Rahim', 'company_name' => 'Uddin Traders', 'email' => 'r@uddin.test']);
        $this->contactOf($user, ['name' => 'Supplier Co', 'company_name' => null, 'email' => null, 'is_customer' => false, 'is_vendor' => true]);
        $this->contactOf($user, ['name' => 'Both Ltd', 'company_name' => null, 'email' => null, 'is_customer' => true, 'is_vendor' => true]);
        $this->contactOf($user, ['name' => 'Gone', 'company_name' => null, 'email' => null, 'phone' => '0199', 'is_active' => false]);

        $names = fn (string $query) => array_column($this->getJson("/api/v1/contacts?{$query}")->assertOk()->json('data'), 'name');

        $this->assertSame(['Rahim'], $names('search=uddin'));         // company name
        $this->assertSame(['Rahim'], $names('search=r%40uddin'));     // e-mail
        $this->assertSame(['Gone'], $names('search=0199'));           // phone
        $this->assertSame(['Both Ltd', 'Gone', 'Rahim'], $names('type=customer'));
        $this->assertSame(['Both Ltd', 'Supplier Co'], $names('type=vendor'));
        $this->assertSame(['Gone'], $names('status=inactive'));
        $this->assertSame(['Both Ltd'], $names('type=vendor&status=active&search=ltd'));

        $this->getJson('/api/v1/contacts?type=staff&status=deleted')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'status']);
    }

    /* ---------------------------------------------------------- edit */

    public function test_a_contact_is_updated(): void
    {
        $user    = $this->signIn();
        $contact = $this->contactOf($user);

        $this->putJson("/api/v1/contacts/{$contact->id}", $this->payload(['name' => 'Rahim U.', 'is_active' => false]))
            ->assertOk()
            ->assertJsonPath('message', 'Contact updated successfully.')
            ->assertJsonPath('data.name', 'Rahim U.')
            ->assertJsonPath('data.is_active', false);

        $this->assertSame('Rahim U.', $contact->fresh()->name);
    }

    public function test_a_field_left_out_is_kept_so_a_hidden_tax_number_survives(): void
    {
        $user    = $this->signIn();
        $contact = $this->contactOf($user, ['tax_number' => 'BIN-001']);

        // The form with taxes switched off has no tax number field and sends none.
        $this->putJson("/api/v1/contacts/{$contact->id}", ['is_customer' => true, 'is_vendor' => false, 'name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.tax_number', 'BIN-001');

        // Sent empty, it is cleared.
        $this->putJson("/api/v1/contacts/{$contact->id}", ['is_customer' => true, 'is_vendor' => false, 'name' => 'Renamed', 'tax_number' => ''])
            ->assertOk()
            ->assertJsonPath('data.tax_number', null);
    }

    /* ---------------------------------------------------------- delete */

    public function test_a_contact_is_deleted(): void
    {
        $user    = $this->signIn();
        $contact = $this->contactOf($user);

        $this->deleteJson("/api/v1/contacts/{$contact->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Contact deleted successfully.');

        $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
        $this->deleteJson("/api/v1/contacts/{$contact->id}")->assertNotFound();
    }

    /* ---------------------------------------------------------- one business only */

    public function test_another_business_contact_is_not_found(): void
    {
        $user    = $this->signIn();
        $second  = $user->workspaces()->create(['name' => 'Second Business']);
        $mine    = $this->contactOf($user);
        $sibling = $second->contacts()->create($this->payload(['name' => 'In Second']));
        $foreign = $this->contactOf(User::factory()->create(), ['name' => 'Someone Else']);

        $this->assertSame(['Rahim Uddin'], array_column($this->getJson('/api/v1/contacts')->json('data'), 'name'));
        $this->assertSame(['In Second'], array_column($this->getJson('/api/v1/contacts', $this->pick($second))->json('data'), 'name'));

        foreach ([$sibling, $foreign] as $contact) {
            $this->putJson("/api/v1/contacts/{$contact->id}", $this->payload(['name' => 'Hijacked']))
                ->assertNotFound()
                ->assertJsonPath('message', 'Contact not found.');
            $this->deleteJson("/api/v1/contacts/{$contact->id}")->assertNotFound();
        }

        // The sibling is reachable once its own business is picked; mine is then not.
        $this->putJson("/api/v1/contacts/{$sibling->id}", $this->payload(['name' => 'Renamed']), $this->pick($second))->assertOk();
        $this->putJson("/api/v1/contacts/{$mine->id}", $this->payload(), $this->pick($second))->assertNotFound();

        $this->assertSame('Someone Else', $foreign->fresh()->name);
        $this->getJson('/api/v1/contacts', $this->pick($this->businessOf(User::factory()->create())))->assertNotFound();
    }

    public function test_deleting_a_business_deletes_its_contacts(): void
    {
        $user   = $this->signIn();
        $second = $user->workspaces()->create(['name' => 'Second Business']);
        $second->contacts()->create($this->payload());
        $this->contactOf($user);

        $second->delete();

        $this->assertSame(1, Contact::count());
    }

    /* ---------------------------------------------------------- activity */

    public function test_each_write_is_logged_once_by_name(): void
    {
        $user = $this->signIn();

        $id = $this->postJson('/api/v1/contacts', $this->payload())->json('data.id');
        $this->putJson("/api/v1/contacts/{$id}", $this->payload(['phone' => '0100']))->assertOk();
        $this->deleteJson("/api/v1/contacts/{$id}")->assertOk();

        $logs = ActivityLog::where('user_id', $user->id)->orderBy('id')->get();

        $this->assertSame(
            ['Added contact "Rahim Uddin"', 'Updated contact "Rahim Uddin"', 'Deleted contact "Rahim Uddin"'],
            $logs->pluck('description')->all(),
        );
        $this->assertSame(['phone'], $logs[1]->properties['fields']);
    }
}
