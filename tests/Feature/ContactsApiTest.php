<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Admin\Language;
use App\Models\Shared\ActivityLog;
use App\Models\User\Contact;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * A business's customers and suppliers (Doc 03): one record per contact,
 * numbered within the business, shared by every app of that business and no
 * other.
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

        Currency::create(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);
        Currency::create(['name' => 'Euro', 'code' => 'EUR', 'symbol' => '€', 'is_default' => false, 'is_active' => true]);
        Language::create(['name' => 'English', 'code' => 'en', 'is_active' => true]);
        Language::create(['name' => 'French', 'code' => 'fr', 'is_active' => false]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'contact_type'  => 'business',
            'role'          => 'customer',
            'name'          => 'Uddin Traders Ltd',
            'trading_name'  => 'Uddin Traders',
            'contact_person' => 'Rahim Uddin',
            'email'         => 'rahim@uddin.test',
            'phone'         => '+880 1711-000000',
            'billing_city'  => 'Dhaka',
            'billing_country' => 'BD',
        ], $overrides);
    }

    private function contactOf(User $user, array $overrides = []): Contact
    {
        return $this->businessOf($user)->contacts()->create($this->payload($overrides));
    }

    private function usingTaxes(User $user, string $mode = 'enabled'): void
    {
        $this->businessOf($user)->forceFill(['tax_mode' => $mode])->save();
    }

    /* ---------------------------------------------------------- access */

    public function test_every_endpoint_needs_a_token_and_a_business(): void
    {
        $contact = $this->contactOf(User::factory()->create());

        $this->getJson('/api/v1/contacts')->assertUnauthorized();
        $this->getJson("/api/v1/contacts/{$contact->id}")->assertUnauthorized();
        $this->postJson('/api/v1/contacts', $this->payload())->assertUnauthorized();
        $this->putJson("/api/v1/contacts/{$contact->id}", $this->payload(['name' => 'Hijacked']))->assertUnauthorized();
        $this->postJson("/api/v1/contacts/{$contact->id}/deactivate")->assertUnauthorized();
        $this->deleteJson("/api/v1/contacts/{$contact->id}")->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/contacts')
            ->assertNotFound()
            ->assertJsonPath('message', 'Create a workspace first.');

        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'name' => 'Uddin Traders Ltd', 'is_active' => true]);
    }

    /* ---------------------------------------------------------- add */

    public function test_a_contact_is_added_with_its_number_and_every_field(): void
    {
        $user = $this->signIn();
        $this->putJson('/api/v1/business-settings/currencies', ['base_currency' => 'USD', 'additional_currencies' => ['EUR']])->assertOk();

        $response = $this->postJson('/api/v1/contacts', $this->payload([
            'role'                   => 'both',
            'job_title'              => 'Owner',
            'alt_phone'              => '02 5566 7788',
            'website'                => 'https://uddin.test',
            'preferred_language'     => 'en',
            'tags'                   => ['Wholesale', 'VIP'],
            'billing_line1'          => 'House 12, Road 5',
            'billing_line2'          => 'Mirpur-11',
            'billing_state'          => 'Dhaka Division',
            'billing_postal_code'    => '1216',
            'sales_currency'         => 'eur',
            'customer_payment_terms' => 30,
            'supplier_payment_terms' => 0,
            'notes'                  => 'Pays by bank transfer.',
        ]))
            ->assertCreated()
            ->assertJsonPath('message', 'Contact added successfully.')
            ->assertJsonPath('data.contact_number', 'C-00001')
            ->assertJsonPath('data.contact_type', 'business')
            ->assertJsonPath('data.role', 'both')
            ->assertJsonPath('data.tags', ['Wholesale', 'VIP'])
            ->assertJsonPath('data.billing_country', 'BD')
            ->assertJsonPath('data.shipping_same_as_billing', true)
            ->assertJsonPath('data.sales_currency', 'EUR')
            ->assertJsonPath('data.customer_payment_terms', 30)
            ->assertJsonPath('data.purchase_currency', null)
            ->assertJsonPath('data.supplier_payment_terms', 0)
            ->assertJsonPath('data.is_active', true);

        $this->assertSame([
            'id', 'contact_number', 'contact_type', 'role', 'name', 'trading_name', 'contact_person', 'job_title',
            'phone', 'alt_phone', 'email', 'website', 'preferred_language', 'tags',
            'billing_line1', 'billing_line2', 'billing_city', 'billing_state', 'billing_postal_code', 'billing_country',
            'shipping_same_as_billing', 'shipping_line1', 'shipping_line2', 'shipping_city', 'shipping_state', 'shipping_postal_code', 'shipping_country',
            'sales_currency', 'customer_payment_terms', 'purchase_currency', 'supplier_payment_terms',
            'tax_number', 'tax_country', 'tax_exemption_number', 'notes', 'is_active',
        ], array_keys($response->json('data')));

        $this->assertDatabaseHas('contacts', ['id' => $response->json('data.id'), 'workspace_id' => $this->businessOf($user)->id]);
    }

    public function test_only_the_type_role_and_name_are_required(): void
    {
        $this->signIn();

        $this->postJson('/api/v1/contacts', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['contact_type', 'role', 'name'])
            ->assertJsonMissingValidationErrors(['email', 'phone', 'billing_country']);

        $this->postJson('/api/v1/contacts', ['contact_type' => 'individual', 'role' => 'supplier', 'name' => 'Karim Ahmed'])
            ->assertCreated()
            ->assertJsonPath('data.email', null)
            ->assertJsonPath('data.tags', []);

        $this->postJson('/api/v1/contacts', $this->payload(['contact_type' => 'company', 'role' => 'vendor']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'contact_type' => 'Choose business or individual.',
                'role'         => 'Choose customer, supplier or both.',
            ]);
    }

    public function test_numbers_count_up_within_each_business_and_are_never_reused(): void
    {
        $user   = $this->signIn();
        $second = $user->workspaces()->create(['name' => 'Second Business']);

        $first = $this->postJson('/api/v1/contacts', $this->payload(['name' => 'One', 'email' => null, 'phone' => null, 'trading_name' => null]))->json('data');
        $this->postJson('/api/v1/contacts', $this->payload(['name' => 'Two', 'email' => null, 'phone' => null, 'trading_name' => null]))->assertJsonPath('data.contact_number', 'C-00002');
        $this->postJson('/api/v1/contacts', $this->payload(['name' => 'Elsewhere']), $this->pick($second))->assertJsonPath('data.contact_number', 'C-00001');

        $this->deleteJson("/api/v1/contacts/{$first['id']}")->assertOk();

        // A number is not the caller's to choose, and a deleted one is not given again.
        $this->postJson('/api/v1/contacts', $this->payload(['name' => 'Three', 'email' => null, 'phone' => null, 'trading_name' => null, 'contact_number' => 'C-00001']))
            ->assertJsonPath('data.contact_number', 'C-00003');
    }

    public function test_it_belongs_to_the_business_of_the_request_whatever_the_body_says(): void
    {
        $user   = $this->signIn();
        $theirs = $this->businessOf(User::factory()->create());

        $this->postJson('/api/v1/contacts', $this->payload(['workspace_id' => $theirs->id]))->assertCreated();

        $this->assertSame(1, $this->businessOf($user)->contacts()->count());
        $this->assertSame(0, $theirs->contacts()->count());
    }

    public function test_fields_are_checked_against_what_they_can_hold(): void
    {
        $this->signIn();

        $this->postJson('/api/v1/contacts', $this->payload([
            'email'                  => 'not-an-email',
            'phone'                  => 'call me',
            'website'                => 'ftp://uddin.test',
            'preferred_language'     => 'fr',
            'billing_country'        => 'XX',
            'tax_country'            => 'Bangladesh',
            'sales_currency'         => 'EUR',
            'customer_payment_terms' => 400,
            'billing_postal_code'    => str_repeat('1', 21),
            'tags'                   => array_map(fn ($i) => "tag {$i}", range(1, 21)),
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email',
                'phone'                  => 'Use digits, spaces and + ( ) - . only.',
                'website'                => 'http:// or https://',
                'preferred_language'     => 'Choose one of the available languages.',
                'billing_country'        => 'Choose a country from the list.',
                'tax_country'            => 'Choose a country from the list.',
                'sales_currency'         => 'Settings > Currencies',
                'customer_payment_terms' => 'at most 365 days',
                'billing_postal_code',
                'tags'                   => 'up to 20 tags',
            ]);

        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_tags_are_kept_once_each_and_codes_upper_case(): void
    {
        $this->signIn();

        $this->postJson('/api/v1/contacts', $this->payload(['tags' => [' VIP ', 'vip', '', 'Wholesale'], 'billing_country' => 'bd', 'tax_country' => 'gb']))
            ->assertCreated()
            ->assertJsonPath('data.tags', ['VIP', 'Wholesale'])
            ->assertJsonPath('data.billing_country', 'BD')
            ->assertJsonPath('data.tax_country', 'GB');
    }

    public function test_a_shipping_address_of_its_own_is_kept_only_when_it_is_not_the_billing_one(): void
    {
        $this->signIn();

        $shipping = ['shipping_line1' => 'Warehouse 7', 'shipping_city' => 'Chattogram', 'shipping_country' => 'BD'];

        $this->postJson('/api/v1/contacts', $this->payload([...$shipping, 'shipping_same_as_billing' => true]))
            ->assertCreated()
            ->assertJsonPath('data.shipping_line1', null)
            ->assertJsonPath('data.shipping_city', null);

        $id = $this->postJson('/api/v1/contacts', $this->payload([...$shipping, 'name' => 'Other', 'trading_name' => null, 'email' => null, 'phone' => null, 'shipping_same_as_billing' => false]))
            ->assertCreated()
            ->assertJsonPath('data.shipping_line1', 'Warehouse 7')
            ->assertJsonPath('data.shipping_country', 'BD')
            ->json('data.id');

        // Switched back to the billing address, its own is cleared.
        $this->putJson("/api/v1/contacts/{$id}", $this->payload(['name' => 'Other', 'trading_name' => null, 'email' => null, 'phone' => null, 'shipping_same_as_billing' => true]))
            ->assertOk()
            ->assertJsonPath('data.shipping_line1', null);
    }

    /* ---------------------------------------------------------- list */

    public function test_a_new_business_has_no_contacts(): void
    {
        $this->signIn();

        $this->getJson('/api/v1/contacts')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('pagination.total', 0);
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
            ->assertJsonPath('pagination.total', 11)
            ->assertJsonPath('pagination.has_next_page', true);

        $this->getJson('/api/v1/contacts?per_page=10&page=2')->assertJsonPath('data.0.name', 'Zaman');
        $this->getJson('/api/v1/contacts?per_page=7')->assertUnprocessable()->assertJsonValidationErrors('per_page');
    }

    public function test_the_list_is_searched_by_name_number_email_and_phone(): void
    {
        $user = $this->signIn();

        $this->contactOf($user, ['name' => 'Uddin Traders Ltd']);
        $this->contactOf($user, ['name' => 'Bose Electronics', 'trading_name' => 'Bose', 'email' => 'sales@bose.test', 'phone' => '02-9988 7766']);
        $this->contactOf($user, ['name' => 'Karim Ahmed', 'contact_type' => 'individual', 'trading_name' => null, 'email' => null, 'phone' => null]);

        $names = fn (string $query) => array_column($this->getJson("/api/v1/contacts?{$query}")->assertOk()->json('data'), 'name');

        $this->assertSame(['Uddin Traders Ltd'], $names('search=uddin'));
        $this->assertSame(['Bose Electronics'], $names('search=C-00002'));
        $this->assertSame(['Bose Electronics'], $names('search=sales%40bose'));
        // However the number was typed, with or without its spaces and dashes.
        $this->assertSame(['Uddin Traders Ltd'], $names('search=1711000000'));
        $this->assertSame(['Bose Electronics'], $names('search=9988+7766'));
    }

    public function test_the_tax_id_is_searched_only_while_taxes_are_on(): void
    {
        $user = $this->signIn();
        $this->contactOf($user, ['name' => 'Taxed Co', 'tax_number' => 'BIN-998877']);

        $names = fn () => array_column($this->getJson('/api/v1/contacts?search=998877')->json('data'), 'name');

        $this->assertSame([], $names());

        $this->usingTaxes($user);
        $this->assertSame(['Taxed Co'], $names());
    }

    public function test_the_list_is_filtered_by_role_type_and_status(): void
    {
        $user = $this->signIn();

        $this->contactOf($user, ['name' => 'Customer Co', 'role' => 'customer']);
        $this->contactOf($user, ['name' => 'Supplier Co', 'role' => 'supplier', 'email' => null, 'phone' => null, 'trading_name' => null]);
        $this->contactOf($user, ['name' => 'Both Ltd', 'role' => 'both', 'email' => null, 'phone' => null, 'trading_name' => null]);
        $this->contactOf($user, ['name' => 'Jane Doe', 'contact_type' => 'individual', 'email' => null, 'phone' => null, 'trading_name' => null, 'is_active' => false]);

        $names = fn (string $query) => array_column($this->getJson("/api/v1/contacts?{$query}")->assertOk()->json('data'), 'name');

        // A role filter is the role exactly: "customer" leaves out those that are both.
        $this->assertSame(['Customer Co', 'Jane Doe'], $names('role=customer'));
        $this->assertSame(['Supplier Co'], $names('role=supplier'));
        $this->assertSame(['Both Ltd'], $names('role=both'));
        $this->assertSame(['Jane Doe'], $names('type=individual'));
        $this->assertSame(['Jane Doe'], $names('status=inactive'));
        $this->assertSame(['Both Ltd', 'Customer Co', 'Supplier Co'], $names('type=business&status=active'));

        $this->getJson('/api/v1/contacts?role=vendor&type=company')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role', 'type']);
    }

    /* ---------------------------------------------------------- one contact */

    public function test_one_contact_is_shown_whole(): void
    {
        $user    = $this->signIn();
        $contact = $this->contactOf($user, ['notes' => 'Prefers e-mail.']);

        $this->getJson("/api/v1/contacts/{$contact->id}")
            ->assertOk()
            ->assertJsonPath('data.contact_number', 'C-00001')
            ->assertJsonPath('data.notes', 'Prefers e-mail.');
    }

    /* ---------------------------------------------------------- edit */

    public function test_a_contact_is_updated(): void
    {
        $user    = $this->signIn();
        $contact = $this->contactOf($user);

        $this->putJson("/api/v1/contacts/{$contact->id}", $this->payload(['name' => 'Uddin Traders PLC', 'role' => 'both', 'is_active' => false]))
            ->assertOk()
            ->assertJsonPath('message', 'Contact updated successfully.')
            ->assertJsonPath('data.name', 'Uddin Traders PLC')
            ->assertJsonPath('data.role', 'both')
            ->assertJsonPath('data.contact_number', 'C-00001')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_a_field_left_out_is_kept_so_hidden_tax_details_survive(): void
    {
        $user    = $this->signIn();
        $contact = $this->contactOf($user, ['tax_number' => 'BIN-001', 'tax_country' => 'BD', 'purchase_currency' => 'USD']);

        // A form with taxes off sends no tax fields, and one for a customer no supplier defaults.
        $this->putJson("/api/v1/contacts/{$contact->id}", ['contact_type' => 'business', 'role' => 'customer', 'name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.tax_number', 'BIN-001')
            ->assertJsonPath('data.tax_country', 'BD')
            ->assertJsonPath('data.purchase_currency', 'USD');

        // Sent empty, a field is cleared.
        $this->putJson("/api/v1/contacts/{$contact->id}", ['contact_type' => 'business', 'role' => 'customer', 'name' => 'Renamed', 'tax_number' => ''])
            ->assertOk()
            ->assertJsonPath('data.tax_number', null);
    }

    public function test_a_contact_is_activated_and_deactivated(): void
    {
        $user    = $this->signIn();
        $contact = $this->contactOf($user);

        $this->postJson("/api/v1/contacts/{$contact->id}/deactivate")
            ->assertOk()
            ->assertJsonPath('message', 'Contact deactivated.')
            ->assertJsonPath('data.is_active', false);

        $this->postJson("/api/v1/contacts/{$contact->id}/activate")
            ->assertOk()
            ->assertJsonPath('message', 'Contact activated.')
            ->assertJsonPath('data.is_active', true);

        $this->assertSame(
            ['Deactivated contact "Uddin Traders Ltd"', 'Activated contact "Uddin Traders Ltd"'],
            ActivityLog::where('user_id', $user->id)->orderBy('id')->pluck('description')->all(),
        );
    }

    /* ---------------------------------------------------------- duplicates */

    public function test_a_contact_that_looks_like_another_is_warned_of_before_it_is_saved(): void
    {
        $user     = $this->signIn();
        $existing = $this->contactOf($user);

        // The same e-mail, and the same phone number written another way.
        $this->postJson('/api/v1/contacts', ['contact_type' => 'individual', 'role' => 'customer', 'name' => 'R. Uddin', 'email' => 'RAHIM@uddin.test', 'phone' => '01711000000'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'This looks like a contact you already have.')
            ->assertJsonPath('duplicates.0.id', $existing->id)
            ->assertJsonPath('duplicates.0.contact_number', 'C-00001')
            ->assertJsonPath('duplicates.0.matched_on', ['phone', 'email']);

        $this->assertDatabaseCount('contacts', 1);

        // Seen, and saved anyway: a shared e-mail or phone does not block it.
        $this->postJson('/api/v1/contacts', ['contact_type' => 'individual', 'role' => 'customer', 'name' => 'R. Uddin', 'email' => 'rahim@uddin.test', 'allow_duplicates' => true])
            ->assertCreated();
    }

    public function test_names_trading_names_and_tax_ids_are_compared_too(): void
    {
        $user = $this->signIn();
        $this->contactOf($user, ['name' => 'Bose Electronics Ltd', 'trading_name' => 'Bose', 'email' => null, 'phone' => null, 'tax_number' => 'BIN 12-345']);

        $this->postJson('/api/v1/contacts', ['contact_type' => 'business', 'role' => 'customer', 'name' => ' bose '])
            ->assertStatus(409)
            ->assertJsonPath('duplicates.0.matched_on', ['name']);

        $this->postJson('/api/v1/contacts', ['contact_type' => 'business', 'role' => 'supplier', 'name' => 'New Name', 'tax_number' => 'bin12345'])
            ->assertStatus(409)
            ->assertJsonPath('duplicates.0.matched_on', ['tax_id']);

        // A short number or a different one is no match.
        $this->postJson('/api/v1/contacts', ['contact_type' => 'business', 'role' => 'supplier', 'name' => 'Someone New', 'phone' => '12345'])->assertCreated();
    }

    public function test_an_edit_is_warned_of_only_when_it_changes_what_matches(): void
    {
        $user  = $this->signIn();
        $first = $this->contactOf($user);
        $other = $this->contactOf($user, ['name' => 'Other Co', 'trading_name' => null, 'email' => 'other@co.test', 'phone' => null]);

        // Its own details never match itself, and a new address repeats no warning.
        $this->putJson("/api/v1/contacts/{$first->id}", $this->payload(['billing_city' => 'Chattogram']))->assertOk();

        $this->putJson("/api/v1/contacts/{$other->id}", $this->payload(['name' => 'Other Co', 'trading_name' => null, 'phone' => null]))
            ->assertStatus(409)
            ->assertJsonPath('duplicates.0.id', $first->id);

        $this->putJson("/api/v1/contacts/{$other->id}", $this->payload(['name' => 'Other Co', 'trading_name' => null, 'phone' => null, 'allow_duplicates' => true]))->assertOk();
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

        $this->assertSame(['Uddin Traders Ltd'], array_column($this->getJson('/api/v1/contacts')->json('data'), 'name'));
        $this->assertSame(['In Second'], array_column($this->getJson('/api/v1/contacts', $this->pick($second))->json('data'), 'name'));

        foreach ([$sibling, $foreign] as $contact) {
            $this->getJson("/api/v1/contacts/{$contact->id}")->assertNotFound()->assertJsonPath('message', 'Contact not found.');
            $this->putJson("/api/v1/contacts/{$contact->id}", $this->payload(['name' => 'Hijacked']))->assertNotFound();
            $this->postJson("/api/v1/contacts/{$contact->id}/deactivate")->assertNotFound();
            $this->deleteJson("/api/v1/contacts/{$contact->id}")->assertNotFound();
        }

        // Another business's contacts are no duplicates of this one's.
        $this->putJson("/api/v1/contacts/{$sibling->id}", $this->payload(['name' => 'Renamed']), $this->pick($second))->assertOk();
        $this->putJson("/api/v1/contacts/{$mine->id}", $this->payload(), $this->pick($second))->assertNotFound();

        $this->assertSame('Someone Else', $foreign->fresh()->name);
    }

    public function test_deleting_a_business_deletes_its_contacts_and_their_counter(): void
    {
        $user   = $this->signIn();
        $second = $user->workspaces()->create(['name' => 'Second Business']);
        $second->contacts()->create($this->payload());
        $this->contactOf($user);

        $second->delete();

        $this->assertSame(1, Contact::count());
        $this->assertDatabaseMissing('workspace_sequences', ['workspace_id' => $second->id]);
    }

    /* ---------------------------------------------------------- activity */

    public function test_each_write_is_logged_once_by_name(): void
    {
        $user = $this->signIn();

        $id = $this->postJson('/api/v1/contacts', $this->payload())->json('data.id');
        $this->putJson("/api/v1/contacts/{$id}", $this->payload(['phone' => '+880 1711-000001']))->assertOk();
        $this->deleteJson("/api/v1/contacts/{$id}")->assertOk();

        $logs = ActivityLog::where('user_id', $user->id)->orderBy('id')->get();

        $this->assertSame(
            ['Added contact "Uddin Traders Ltd"', 'Updated contact "Uddin Traders Ltd"', 'Deleted contact "Uddin Traders Ltd"'],
            $logs->pluck('description')->all(),
        );
        $this->assertSame(['phone'], $logs[1]->properties['fields']);
    }

    /* ---------------------------------------------------------- currencies */

    public function test_a_default_currency_the_business_drops_falls_back_to_the_base_one(): void
    {
        $user = $this->signIn();
        $this->putJson('/api/v1/business-settings/currencies', ['base_currency' => 'USD', 'additional_currencies' => ['EUR']])->assertOk();
        $contact = $this->contactOf($user, ['sales_currency' => 'EUR', 'purchase_currency' => 'USD']);

        $this->putJson('/api/v1/business-settings/currencies', ['base_currency' => 'USD', 'additional_currencies' => []])->assertOk();

        $this->assertSame([null, 'USD'], [$contact->fresh()->sales_currency, $contact->fresh()->purchase_currency]);
        $this->assertSame('USD', Workspace::find($contact->workspace_id)->base_currency);
    }
}
