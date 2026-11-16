<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\Contact;
use App\Models\User\CrmLead;
use App\Models\User\CrmOpportunity;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 17 Phase C3: merging contacts (section 17.21). One is kept, and the
 * other fills only what it lacks, gives it its leads, opportunities,
 * attachments, price lists and duplicate decisions, keeps its financial
 * records, and is archived, merged into it - logged on both.
 */
class ContactMergeTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    protected User $user;

    private Workspace $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Storage::fake('local');
        Storage::fake('public');

        $this->offerSalesLite();
        $crm = Application::create(['app_key' => 'crm', 'name' => 'CRM', 'family_key' => 'crm', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 20, 'is_active' => true, 'status' => 'available']);
        $crm->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-11-16 10:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->setUpSales();
        $this->postJson('/api/v1/apps/crm/activate')->assertOk();

        $this->customer->update(['phone' => '+880 1711-000000', 'tags' => ['wholesale'], 'notes' => 'Pays on time.']);
    }

    /** Another copy of Uddin Traders, as a supplier with details of its own. */
    private function copy(array $fields = []): Contact
    {
        return $this->business->contacts()->create([
            'contact_type' => 'business', 'role' => 'supplier', 'name' => 'Uddin Trading', 'email' => 'accounts@uddin.example', 'phone' => '01811 000000',
            'website' => 'https://uddin.example', 'billing_line1' => 'Road 1', 'billing_city' => 'Chattogram', 'billing_country' => 'BD',
            'supplier_payment_terms' => 15, 'tags' => ['Wholesale', 'importer'], 'notes' => 'Ask for Karim.', ...$fields,
        ]);
    }

    /** A lead, its id - qualified and converted into an opportunity for $contact when given. */
    private function lead(string $name, ?Contact $contact = null): int
    {
        $id = $this->postJson('/api/v1/crm/leads', [
            'name' => $name, 'email' => strtolower(str_replace(' ', '.', $name)).'@example.com', 'customer_need' => 'Shelving',
            'source_id' => $this->getJson('/api/v1/crm/leads/options')->json('data.sources.0.id'),
        ])->assertCreated()->json('data.id');

        if ($contact) {
            $this->postJson("/api/v1/crm/leads/{$id}/qualify")->assertOk();
            $stage = collect($this->getJson('/api/v1/business-settings/crm')->json('data.pipelines.0.stages'))->firstWhere('name', 'Qualified')['id'];
            $this->postJson("/api/v1/crm/leads/{$id}/convert", ['contact_id' => $contact->id, 'opportunity' => [
                'name' => "{$name} - shelving", 'stage_id' => $stage, 'expected_value' => '1500', 'currency' => $this->business->base_currency, 'expected_close_date' => '2026-12-20',
            ]])->assertOk();
        }

        return $id;
    }

    private function priceList(string $name, array $contacts): int
    {
        $list = DB::table('price_lists')->insertGetId(['workspace_id' => $this->business->id, 'name' => $name, 'currency' => 'USD', 'all_customers' => false, 'created_at' => now(), 'updated_at' => now()]);

        foreach ($contacts as $contact) {
            DB::table('price_list_contacts')->insert(['price_list_id' => $list, 'contact_id' => $contact->id]);
        }

        return $list;
    }

    public function test_the_kept_contact_gains_only_what_it_lacks_and_the_others_records(): void
    {
        $kept   = $this->customer;
        $merged = $this->copy();

        $converted = $this->lead('Rahim Uddin', $merged);
        $linked    = $this->lead('Karim Ali');
        $both      = $this->lead('Selina Haque');
        $this->postJson("/api/v1/crm/leads/{$linked}/duplicates/link", ['type' => 'contact', 'id' => $merged->id])->assertOk();
        $this->postJson("/api/v1/crm/leads/{$both}/duplicates/link", ['type' => 'contact', 'id' => $merged->id])->assertOk();
        $this->postJson("/api/v1/crm/leads/{$both}/duplicates/ignore", ['type' => 'contact', 'id' => $kept->id, 'reason' => 'Another shop'])->assertOk();
        $this->post("/api/v1/contacts/{$merged->id}/attachments", ['files' => [UploadedFile::fake()->create('licence.pdf', 20, 'application/pdf')]], ['Accept' => 'application/json'])->assertCreated();
        $wholesale = $this->priceList('Wholesale', [$merged]);
        $shared    = $this->priceList('Trade', [$kept, $merged]);

        $response = $this->postJson("/api/v1/contacts/{$kept->id}/merge", ['merged_id' => $merged->id])->assertOk()
            ->assertJsonPath('message', 'Contacts merged.')
            ->assertJsonPath('data.merged', false)
            ->assertJsonPath('data.merged_contacts.0.id', $merged->id);

        // What it lacked, from the other: the other's phone as its alternative; its own address, name and phone stay.
        $this->assertSame(
            ['Uddin Traders', 'both', 'accounts@uddin.example', '+880 1711-000000', '01811 000000', 'https://uddin.example', 15, 30, 'Dhaka', ['wholesale', 'importer']],
            [$response->json('data.name'), $response->json('data.role'), $response->json('data.email'), $response->json('data.phone'), $response->json('data.alt_phone'), $response->json('data.website'),
                $response->json('data.supplier_payment_terms'), $response->json('data.customer_payment_terms'), $response->json('data.billing_city'), $response->json('data.tags')],
        );
        $this->assertSame("Pays on time.\n\nMerged from {$merged->contact_number} Uddin Trading:\nAsk for Karim.", $response->json('data.notes'));

        // The other is archived, merged into it - with its own details as they were.
        $merged->refresh();
        $this->assertSame([false, $kept->id, $this->user->id, 'Chattogram'], [$merged->is_active, $merged->merged_into_id, $merged->merged_by, $merged->billing_city]);
        $this->getJson("/api/v1/contacts/{$merged->id}")->assertOk()
            ->assertJsonPath('data.merged', true)
            ->assertJsonPath('data.merged_into.id', $kept->id)
            ->assertJsonPath('data.merged_into.merged_by', $this->user->name);

        // Its lead, opportunity, attachment, price list and decisions are the kept one's; a decision the lead had made about it stays.
        $this->assertSame($kept->id, CrmLead::find($converted)->contact_id);
        $this->assertSame([$kept->id], CrmOpportunity::pluck('contact_id')->all());
        $this->assertSame(['licence.pdf'], array_column($this->getJson("/api/v1/contacts/{$kept->id}/attachments")->json('data'), 'name'));
        $this->assertEqualsCanonicalizing([[$wholesale, $kept->id], [$shared, $kept->id]], DB::table('price_list_contacts')->get()->map(fn ($row) => [$row->price_list_id, $row->contact_id])->all());
        $this->assertEqualsCanonicalizing([[$linked, $kept->id, 'linked'], [$both, $kept->id, 'ignored']], DB::table('crm_duplicate_reviews')->get()->map(fn ($row) => [$row->crm_lead_id, $row->other_id, $row->decision])->all());

        $logged = 'Merged contact "Uddin Trading" ('.$merged->contact_number.') into "Uddin Traders" ('.$kept->contact_number.')';
        $this->assertSame([[Contact::class, $kept->id], [Contact::class, $merged->id]], ActivityLog::where('description', $logged)->orderBy('id')->get()->map(fn ($log) => [$log->model_type, $log->model_id])->all());
    }

    public function test_the_preview_says_what_would_change_and_what_stays_with_the_other(): void
    {
        $merged = $this->copy(['role' => 'customer', 'notes' => str_repeat('x', 4990)]);
        $this->postJson('/api/v1/sales/invoices', $this->invoice(['contact_id' => $merged->id]))->assertCreated();
        $this->lead('Rahim Uddin', $merged);

        $preview = $this->getJson("/api/v1/contacts/{$this->customer->id}/merge?merged_id={$merged->id}")->assertOk()
            ->assertJsonPath('data.merged.name', 'Uddin Trading')
            ->assertJsonPath('data.notes', 'kept')
            ->assertJsonPath('data.moving.leads', 1)
            ->assertJsonPath('data.moving.opportunities', 1)
            ->assertJsonPath('data.staying.sales_documents', 1)
            ->assertJsonPath('data.staying.purchases', 0);
        $this->assertSame(['email', 'website', 'supplier_payment_terms', 'alt_phone', 'tags'], array_column($preview->json('data.changes'), 'field'));

        $this->postJson("/api/v1/contacts/{$this->customer->id}/merge", ['merged_id' => $merged->id])->assertOk()
            ->assertJsonPath('data.notes', 'Pays on time.');

        // The invoice is still the other's: nothing financial moves.
        $this->assertSame([$merged->id], DB::table('sales_documents')->pluck('contact_id')->all());
    }

    public function test_only_two_unmerged_contacts_of_the_business_merge_with_the_permission(): void
    {
        $kept   = $this->customer;
        $merged = $this->copy(['email' => 'UDDIN@traders.example']);
        $kept->update(['email' => 'uddin@traders.example']);

        $this->assertSame([[$merged->id, ['email']]], array_map(fn ($row) => [$row['id'], $row['matched_on']], $this->getJson("/api/v1/contacts/{$kept->id}/merge/candidates")->assertOk()->json('data')));

        $this->postJson("/api/v1/contacts/{$kept->id}/merge", ['merged_id' => $kept->id])->assertJsonValidationErrors(['merged_id' => 'Choose another contact than this one.']);
        $elsewhere = $this->businessOf(User::factory()->create())->contacts()->create(['name' => 'Elsewhere']);
        $this->postJson("/api/v1/contacts/{$kept->id}/merge", ['merged_id' => $elsewhere->id])->assertJsonValidationErrors(['merged_id' => 'Choose one of your contacts.']);

        // Without Merge, even with Edit.
        $member = User::factory()->create();
        $role   = $this->business->roles()->create(['name' => 'Editors', 'permissions' => ['contacts.view', 'contacts.edit', 'taxes.view']]);
        $this->business->members()->create(['user_id' => $member->id, 'status' => 'active', 'role_id' => $role->id]);
        Sanctum::actingAs($member);
        $this->getJson("/api/v1/contacts/{$kept->id}/merge/candidates")->assertForbidden();
        $this->postJson("/api/v1/contacts/{$kept->id}/merge", ['merged_id' => $merged->id])->assertForbidden();

        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/contacts/{$kept->id}/merge", ['merged_id' => $merged->id])->assertOk();

        // Merged, it is neither merged again nor kept, and it no longer changes.
        $third = $this->business->contacts()->create(['name' => 'Third']);
        $this->postJson("/api/v1/contacts/{$third->id}/merge", ['merged_id' => $merged->id])->assertJsonValidationErrors(['merged_id' => 'That contact was already merged into another.']);
        $this->postJson("/api/v1/contacts/{$merged->id}/merge", ['merged_id' => $third->id])->assertStatus(409)
            ->assertJsonPath('message', 'This contact was merged into "Uddin Traders", so it no longer changes.');
        $this->putJson("/api/v1/contacts/{$merged->id}", ['contact_type' => 'business', 'role' => 'supplier', 'name' => 'Renamed'])->assertStatus(409);
        $this->postJson("/api/v1/contacts/{$merged->id}/activate")->assertStatus(409);
        $this->deleteJson("/api/v1/contacts/{$merged->id}")->assertStatus(409);
        $this->post("/api/v1/contacts/{$merged->id}/attachments", ['files' => [UploadedFile::fake()->create('more.pdf', 20, 'application/pdf')]], ['Accept' => 'application/json'])->assertStatus(409);
        $this->assertSame('Uddin Trading', $merged->fresh()->name);

        // Nor is the one it was merged into deleted.
        $this->deleteJson("/api/v1/contacts/{$kept->id}")->assertStatus(409)
            ->assertJsonPath('message', 'Other contacts were merged into this one, so it cannot be deleted. Deactivate it instead.');
    }

    public function test_a_merged_contact_is_no_longer_a_match_or_a_choice(): void
    {
        $merged = $this->copy(['name' => 'Haque Garments', 'email' => 'haque@garments.example', 'phone' => '01911 000000']);
        $this->postJson("/api/v1/contacts/{$this->customer->id}/merge", ['merged_id' => $merged->id])->assertOk();

        // The kept one has its e-mail now - the merged one no longer counts as another copy.
        $this->postJson('/api/v1/contacts', ['contact_type' => 'business', 'role' => 'customer', 'name' => 'Haque Garments'])->assertCreated();
        $this->assertSame([$this->customer->id], array_column($this->postJson('/api/v1/contacts', ['contact_type' => 'business', 'role' => 'customer', 'name' => 'New', 'email' => 'haque@garments.example'])->assertStatus(409)->json('duplicates'), 'id'));

        $this->assertContains($merged->id, array_column($this->getJson('/api/v1/contacts?per_page=50')->json('data'), 'id'));
        $this->assertNotContains($merged->id, array_column($this->getJson('/api/v1/contacts?per_page=50&unmerged=1')->json('data'), 'id'));

        // Leads: not a possible duplicate, not a contact to convert into or link.
        $lead = $this->postJson('/api/v1/crm/leads', ['name' => 'Haque Garments', 'phone' => '+880 1911-000000', 'customer_need' => 'Shelving', 'source_id' => $this->getJson('/api/v1/crm/leads/options')->json('data.sources.0.id')])->json('data.id');
        $this->assertNotContains($merged->id, array_column($this->getJson("/api/v1/crm/leads/{$lead}/duplicates")->json('data'), 'id'));
        $this->postJson("/api/v1/crm/leads/{$lead}/duplicates/link", ['type' => 'contact', 'id' => $merged->id])->assertJsonValidationErrors(['id' => 'Choose one of your contacts.']);
        $this->postJson("/api/v1/crm/leads/{$lead}/qualify")->assertOk();
        $stage = $this->getJson('/api/v1/business-settings/crm')->json('data.pipelines.0.stages.0.id');
        $this->postJson("/api/v1/crm/leads/{$lead}/convert", ['contact_id' => $merged->id, 'opportunity' => ['name' => 'Haque', 'stage_id' => $stage, 'expected_value' => '10', 'currency' => $this->business->base_currency, 'expected_close_date' => '2026-12-20']])
            ->assertJsonValidationErrors(['contact_id' => 'That contact was merged into another. Choose the one it was merged into.']);
    }
}
