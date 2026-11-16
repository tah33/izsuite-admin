<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\Contact;
use App\Models\User\CrmOpportunity;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 17 Phase C1: converting a qualified lead into a contact - one that
 * matches, any other, or a new one made from it - and an opportunity at an
 * open stage of the pipeline, carrying the lead's details. A converted lead
 * is final, and what an opportunity holds stays.
 */
class CrmLeadConversionTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $crm = Application::create(['app_key' => 'crm', 'name' => 'CRM', 'family_key' => 'crm', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 20, 'is_active' => true, 'status' => 'available']);
        $crm->packages()->attach(Package::pluck('id'));

        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->postJson('/api/v1/apps/crm/activate')->assertOk();
    }

    /** A lead, qualified unless $qualified is false: its id. */
    private function lead(bool $qualified = true, array $fields = []): int
    {
        $source = $this->getJson('/api/v1/crm/leads/options')->json('data.sources.0.id');
        $id     = $this->postJson('/api/v1/crm/leads', [
            'name' => 'Rahim Uddin', 'business_name' => 'Uddin Traders', 'job_title' => 'Owner', 'email' => 'rahim@uddin.example', 'phone' => '+880 1711-000000',
            'source_id' => $source, 'address_city' => 'Dhaka', 'priority' => 'high', 'estimated_value' => '1500', 'expected_purchase_date' => '2026-12-20',
            'customer_need' => 'Shelving for a new shop', 'next_action' => 'Send a quotation', 'notes' => 'Prefers e-mail.', ...$fields,
        ])->assertCreated()->json('data.id');

        if ($qualified) {
            $this->postJson("/api/v1/crm/leads/{$id}/qualify")->assertOk();
        }

        return $id;
    }

    /** The pipeline's stage called $name: its id. */
    private function stage(string $name): int
    {
        return collect($this->getJson('/api/v1/business-settings/crm')->json('data.pipelines.0.stages'))->firstWhere('name', $name)['id'];
    }

    private function convert(int $lead, ?int $contact, array $opportunity = []): TestResponse
    {
        return $this->postJson("/api/v1/crm/leads/{$lead}/convert", ['contact_id' => $contact, 'opportunity' => [
            'name' => 'Uddin Traders - shelving', 'stage_id' => $opportunity['stage_id'] ?? $this->stage('Qualified'), 'expected_value' => '1500', 'currency' => $this->business->base_currency, 'expected_close_date' => '2026-12-20', ...$opportunity,
        ]]);
    }

    public function test_a_qualified_lead_becomes_a_new_contact_and_an_opportunity(): void
    {
        $open = $this->lead(false);
        $this->getJson("/api/v1/crm/leads/{$open}/conversion")->assertStatus(409)->assertJsonPath('message', 'A lead is qualified before it is converted.');

        $lead    = $this->lead();
        $preview = $this->getJson("/api/v1/crm/leads/{$lead}/conversion")->assertOk()->json('data');

        $this->assertSame([], $preview['matches']);
        $this->assertSame(['New', 'Qualified', 'Proposal', 'Negotiation'], array_column($preview['stages'], 'name'));
        $this->assertSame(['name' => 'Uddin Traders', 'stage_id' => $this->stage('New'), 'expected_value' => '1500.00', 'currency' => $this->business->base_currency, 'expected_close_date' => '2026-12-20'], $preview['defaults']);
        $this->assertTrue($preview['can_create_contact']);

        $this->convert($lead, null)->assertOk()
            ->assertJsonPath('data.status', 'converted')
            ->assertJsonPath('data.actions', [])
            ->assertJsonPath('data.conversion.contact.name', 'Uddin Traders')
            ->assertJsonPath('data.conversion.opportunity.name', 'Uddin Traders - shelving')
            ->assertJsonPath('data.conversion.opportunity.stage', 'Qualified');

        $contact = Contact::query()->where('workspace_id', $this->business->id)->sole();
        $this->assertSame(['business', 'customer', 'Rahim Uddin', 'Owner', 'rahim@uddin.example', 'Dhaka'], [$contact->contact_type, $contact->role, $contact->contact_person, $contact->job_title, $contact->email, $contact->billing_city]);

        $opportunity = CrmOpportunity::sole();
        $this->assertSame(
            [$contact->id, $lead, 25, '1500.00', '2026-12-20', $this->user->id, 'high', 'Shelving for a new shop', 'Send a quotation', 'Prefers e-mail.', 'open'],
            [$opportunity->contact_id, $opportunity->lead_id, $opportunity->probability, $opportunity->expected_value, $opportunity->expected_close_date->toDateString(), $opportunity->owner_id, $opportunity->priority, $opportunity->requirements, $opportunity->next_action, $opportunity->notes, $opportunity->status],
        );
        $this->assertTrue(ActivityLog::query()->where('description', 'Converted lead "Rahim Uddin" into opportunity "Uddin Traders - shelving" for contact "Uddin Traders"')->exists());
        $this->assertTrue(ActivityLog::query()->where('description', 'Added opportunity "Uddin Traders - shelving" from lead "Rahim Uddin"')->exists());

        // Converted once, for good; the Leads page finds it among the converted.
        $this->convert($lead, $contact->id)->assertStatus(409)->assertJsonPath('message', 'This lead is converted already.');
        $this->getJson('/api/v1/crm/leads?status=converted')->assertJsonPath('data.0.id', $lead);
    }

    public function test_a_lead_can_become_a_contact_it_matches_which_becomes_a_customer(): void
    {
        $supplier = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'supplier', 'name' => 'Uddin Traders', 'email' => 'RAHIM@uddin.example']);
        $lead     = $this->lead();

        $this->assertSame([['id' => $supplier->id, 'name' => 'Uddin Traders', 'matched_on' => ['name', 'email']]], array_map(
            fn (array $match) => ['id' => $match['id'], 'name' => $match['name'], 'matched_on' => $match['matched_on']],
            $this->getJson("/api/v1/crm/leads/{$lead}/conversion")->json('data.matches'),
        ));

        // Not another business's contact, not a closed stage, and the choice is said.
        $theirs = $this->businessOf(User::factory()->create())->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Theirs']);
        $this->convert($lead, $theirs->id)->assertUnprocessable()->assertJsonValidationErrors(['contact_id' => 'Choose one of your contacts.']);
        $this->convert($lead, $supplier->id, ['stage_id' => $this->stage('Won')])->assertUnprocessable()->assertJsonValidationErrors(['opportunity.stage_id' => "Choose one of the pipeline's open stages."]);
        $this->postJson("/api/v1/crm/leads/{$lead}/convert", ['opportunity' => ['name' => 'X']])->assertUnprocessable()
            ->assertJsonValidationErrors(['contact_id' => 'Choose a contact, or a new one from the lead.', 'opportunity.stage_id', 'opportunity.expected_value', 'opportunity.expected_close_date']);

        $this->convert($lead, $supplier->id)->assertOk()->assertJsonPath('data.conversion.contact.id', $supplier->id);

        $this->assertSame('both', $supplier->fresh()->role);
        $this->assertSame(1, Contact::query()->where('workspace_id', $this->business->id)->count());
        $this->assertTrue(ActivityLog::query()->where('description', 'Made contact "Uddin Traders" a customer as well, converting lead "Rahim Uddin"')->exists());
    }

    public function test_converting_needs_its_permission_and_a_new_contact_needs_create_contacts(): void
    {
        $lead     = $this->lead();
        $existing = $this->business->contacts()->create(['contact_type' => 'individual', 'role' => 'customer', 'name' => 'Karim Ali']);
        // Read as the owner: the members cannot open Settings > CRM.
        $stage    = ['stage_id' => $this->stage('Qualified')];

        $member = function (array $permissions): User {
            $user = User::factory()->create();
            $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
            $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id]);

            return $user;
        };

        Sanctum::actingAs($member(['crm.leads.view', 'crm.leads.manage', 'crm.records.all']));
        $this->getJson("/api/v1/crm/leads/{$lead}/conversion")->assertForbidden();

        Sanctum::actingAs($member(['crm.leads.view', 'crm.leads.manage', 'crm.leads.convert', 'crm.records.all', 'contacts.view']));
        $this->getJson("/api/v1/crm/leads/{$lead}/conversion")->assertOk()->assertJsonPath('data.can_create_contact', false);
        $this->convert($lead, null, $stage)->assertUnprocessable()->assertJsonValidationErrors(['contact_id' => 'Choose one of your contacts: making a new one needs Create Contacts.']);
        $this->convert($lead, $existing->id, $stage)->assertOk()->assertJsonPath('data.conversion.contact.name', 'Karim Ali');
    }

    public function test_what_an_opportunity_holds_stays(): void
    {
        $location = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka', 'code' => 'DHK'])->id;
        $this->convert($this->lead(true, ['location_id' => $location]), null)->assertOk();
        $contact  = Contact::query()->where('workspace_id', $this->business->id)->sole();

        $this->deleteJson("/api/v1/contacts/{$contact->id}")->assertStatus(409)->assertJsonPath('message', 'This contact has CRM opportunities, so it cannot be deleted. Deactivate it instead.');

        // The lead keeps the location too; the opportunity's own refusal is checked last.
        $this->assertSame($location, CrmOpportunity::sole()->location_id);

        $pipeline = $this->getJson('/api/v1/business-settings/crm')->json('data.pipelines.0');
        $without  = array_values(array_filter($pipeline['stages'], fn (array $stage) => $stage['name'] !== 'Qualified'));
        $this->putJson("/api/v1/business-settings/crm/pipelines/{$pipeline['id']}", ['name' => $pipeline['name'], 'stages' => $without])->assertUnprocessable()
            ->assertJsonValidationErrors(['stages' => 'Opportunities are in "Qualified", so it stays: switch it off instead.']);
    }
}
