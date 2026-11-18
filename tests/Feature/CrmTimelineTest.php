<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 17 Phase E3: the timeline of a lead, an opportunity and a contact -
 * the activities done or cancelled, notes, changes, files and a contact's
 * sales documents, newest first, or one of them at a time, as the user may
 * see them. Monday 16 November 2026, from 10:00 UTC; the customer is
 * MakesSalesDocuments' Uddin Traders.
 */
class CrmTimelineTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    /** @var array<string, int> the activity types by key */
    private array $types;

    private int $opportunity;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $crm = Application::create(['app_key' => 'crm', 'name' => 'CRM', 'family_key' => 'crm', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 20, 'is_active' => true, 'status' => 'available']);
        $crm->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-11-16 10:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->business->forceFill(['base_currency' => 'USD'])->save();
        $this->business->businessSetting()->create([
            'user_id' => $this->user->id, 'business_name' => 'Main Business', 'language' => 'en', 'country' => 'BD', 'state' => 'Dhaka',
            'city'    => 'Dhaka', 'zip_code' => '1207', 'address_line_1' => 'Road 1', 'timezone' => 'UTC', 'stock_accounting_method' => 'fifo',
        ]);
        $this->setUpSales();
        $this->postJson('/api/v1/apps/crm/activate')->assertOk();

        $this->types       = collect($this->getJson('/api/v1/crm/activities/options')->json('data.types'))->pluck('id', 'key')->all();
        $stage             = $this->getJson('/api/v1/business-settings/crm')->json('data.pipelines.0.stages.0.id');
        $this->opportunity = $this->postJson('/api/v1/crm/opportunities', [
            'name' => 'Uddin Traders - racking', 'contact_id' => $this->customer->id, 'stage_id' => $stage, 'expected_value' => '2000', 'currency' => 'USD', 'expected_close_date' => '2026-12-20',
        ])->assertCreated()->json('data.id');
    }

    /** An activity for the record $for names, a call due today at 15:00 unless $fields say otherwise - its id. */
    private function plan(array $for, array $fields = []): int
    {
        return $this->postJson('/api/v1/crm/activities', [...$for, 'type_id' => $this->types['call'], 'subject' => 'Call Rahim', 'due_on' => '2026-11-16', 'due_time' => '15:00', ...$fields])
            ->assertCreated()->json('data.id');
    }

    private function timeline(string $kind, int $id, string $show = ''): array
    {
        return $this->getJson("/api/v1/crm/timeline/{$kind}/{$id}".($show ? "?show={$show}" : ''))->assertOk()->json('data');
    }

    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->business->members()->create(['user_id' => $user->id, 'name' => $user->name, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    public function test_a_leads_timeline_shows_its_activities_done_notes_changes_and_files_newest_first(): void
    {
        $lead = $this->postJson('/api/v1/crm/leads', ['name' => 'Karim Ahmed', 'phone' => '+8801711000000', 'source_id' => $this->getJson('/api/v1/crm/leads/options')->json('data.sources.0.id')])
            ->assertCreated()->json('data.id');

        $this->travel(5)->minutes();
        $call = $this->plan(['lead_id' => $lead], ['call_direction' => 'outbound', 'duration_minutes' => 15]);
        $this->plan(['lead_id' => $lead], ['type_id' => $this->types['task'], 'subject' => 'Send the brochure', 'due_on' => '2026-11-18']);

        $this->travel(5)->minutes();
        $this->postJson("/api/v1/crm/leads/{$lead}/comments", ['body' => 'Wants racking for two floors.'])->assertCreated();

        $this->travel(5)->minutes();
        $this->postJson("/api/v1/crm/activities/{$call}/complete", ['outcome' => 'Agreed to a site visit.'])->assertOk();

        $this->travel(5)->minutes();
        $meeting = $this->plan(['lead_id' => $lead], ['type_id' => $this->types['meeting'], 'subject' => 'Meet at the shop', 'due_on' => '2026-11-17']);
        $this->postJson("/api/v1/crm/activities/{$meeting}/cancel")->assertOk();

        $this->travel(5)->minutes();
        $this->post("/api/v1/crm/leads/{$lead}/attachments", ['files' => [UploadedFile::fake()->create('floor-plan.pdf', 50, 'application/pdf')]], ['Accept' => 'application/json'])->assertCreated();

        // Newest first. The planned task is the Activities card's; no step of an activity, and no comment, shows twice.
        $timeline = $this->timeline('lead', $lead);
        $this->assertSame(['file', 'activity', 'activity', 'note', 'change'], array_column($timeline, 'kind'));

        [$file, $cancelled, $done, $note, $added] = $timeline;
        $this->assertSame(['Attached "floor-plan.pdf" to lead "Karim Ahmed"', $this->user->name, false], [$file['text'], $file['by'], $file['system']]);
        $this->assertSame(['Meeting', 'Meet at the shop', 'cancelled', $this->user->name, '2026-11-16T10:20:00+00:00'], [$cancelled['type'], $cancelled['subject'], $cancelled['status'], $cancelled['by'], $cancelled['at']]);
        $this->assertSame(
            ['Call', 'call', 'Call Rahim', 'completed', 'outbound', 'Agreed to a site visit.', 15, null, '2026-11-16T10:15:00+00:00'],
            [$done['type'], $done['type_key'], $done['subject'], $done['status'], $done['direction'], $done['outcome'], $done['duration_minutes'], $done['for'], $done['at']],
        );
        $this->assertSame(['Wants racking for two floors.', $this->user->name, [], null], [$note['text'], $note['by'], $note['mentions'], $note['for']]);
        $this->assertSame(['Added lead "Karim Ahmed"', null], [$added['text'], $added['mark']]);

        // One part at a time.
        $this->assertSame(['Meet at the shop', 'Call Rahim'], array_column($this->timeline('lead', $lead, 'activities'), 'subject'));
        $this->assertSame(['note'], array_column($this->timeline('lead', $lead, 'notes'), 'kind'));
        $this->assertSame(['file'], array_column($this->timeline('lead', $lead, 'files'), 'kind'));
        $this->assertSame(['change'], array_column($this->timeline('lead', $lead, 'changes'), 'kind'));
        $this->assertSame([], $this->timeline('lead', $lead, 'documents'));
        $this->getJson("/api/v1/crm/timeline/lead/{$lead}?show=everything")->assertUnprocessable()->assertJsonValidationErrors('show');

        // Its activities need View Activities; the timeline, View Leads and seeing the lead.
        Sanctum::actingAs($this->member(['crm.leads.view', 'crm.records.all']));
        $this->assertSame(['file', 'note', 'change'], array_column($this->timeline('lead', $lead), 'kind'));
        Sanctum::actingAs($this->member(['crm.leads.view', 'crm.activities.view']));
        $this->getJson("/api/v1/crm/timeline/lead/{$lead}")->assertNotFound();
        Sanctum::actingAs($this->member(['contacts.view', 'crm.opportunities.view']));
        $this->getJson("/api/v1/crm/timeline/lead/{$lead}")->assertForbidden();
    }

    public function test_an_opportunitys_timeline_marks_its_stage_moves_owners_and_how_it_ended(): void
    {
        $options = $this->getJson('/api/v1/crm/opportunities/options')->json('data');
        $stages  = collect($options['pipelines'][0]['stages'])->pluck('id', 'name');
        $seller  = $this->member(['crm.opportunities.view']);

        $this->travel(1)->minutes();
        $this->postJson("/api/v1/crm/opportunities/{$this->opportunity}/stage", ['stage_id' => $stages['Proposal']])->assertOk();
        $this->travel(1)->minutes();
        $this->putJson("/api/v1/crm/opportunities/{$this->opportunity}", [
            'name' => 'Uddin Traders - racking', 'contact_id' => $this->customer->id, 'owner_id' => $seller->id, 'expected_value' => '2000', 'currency' => 'USD', 'expected_close_date' => '2026-12-20',
        ])->assertOk();
        $this->travel(1)->minutes();
        $this->postJson("/api/v1/crm/opportunities/{$this->opportunity}/lose", ['closed_on' => '2026-11-16', 'lost_reason_id' => $options['lost_reasons'][0]['id']])->assertOk();
        $this->travel(1)->minutes();
        $this->postJson("/api/v1/crm/opportunities/{$this->opportunity}/reopen", ['stage_id' => $stages['Negotiation']])->assertOk();
        $this->travel(1)->minutes();
        $this->postJson("/api/v1/crm/opportunities/{$this->opportunity}/win", ['closed_on' => '2026-11-16'])->assertOk();

        // Given away, the save's own entry comes before the one that says to whom.
        $timeline = $this->timeline('opportunity', $this->opportunity);
        $this->assertSame(['won', 'status', 'lost', 'owner', null, 'stage', null], array_column($timeline, 'mark'));
        $this->assertSame(['Marked opportunity "Uddin Traders - racking" won', "Gave opportunity \"Uddin Traders - racking\" to {$seller->name}"], [$timeline[0]['text'], $timeline[3]['text']]);
        $this->assertSame([['setting' => 'stage', 'from' => 'New', 'to' => 'Proposal'], ['setting' => 'probability', 'from' => '10', 'to' => '50']], $timeline[5]['changes']);

        // Whom it was given to sees it too; someone who does not, gets nothing.
        Sanctum::actingAs($seller);
        $this->assertCount(7, $this->timeline('opportunity', $this->opportunity));
        Sanctum::actingAs($this->member(['crm.opportunities.view']));
        $this->getJson("/api/v1/crm/timeline/opportunity/{$this->opportunity}")->assertNotFound();
    }

    public function test_a_contacts_timeline_gathers_its_opportunities_leads_and_sales_documents_as_the_user_sees_them(): void
    {
        $this->travel(1)->minutes();
        $this->postJson("/api/v1/crm/opportunities/{$this->opportunity}/comments", ['body' => 'They need it before Eid.'])->assertCreated();
        $this->travel(1)->minutes();
        $call = $this->plan(['opportunity_id' => $this->opportunity]);
        $this->postJson("/api/v1/crm/activities/{$call}/complete", ['outcome' => 'Sent the price list.'])->assertOk();
        $this->travel(1)->minutes();
        $email = $this->plan(['contact_id' => $this->customer->id], ['type_id' => $this->types['email'], 'subject' => 'Send the catalogue']);
        $this->postJson("/api/v1/crm/activities/{$email}/complete")->assertOk();
        $this->travel(1)->minutes();
        $quotation = $this->draft('quotations');
        $this->travel(1)->minutes();
        $invoice = $this->issuedInvoice();

        $timeline = $this->timeline('contact', $this->customer->id);
        $this->assertSame(['document', 'document', 'document', 'activity', 'activity', 'note', 'change'], array_column($timeline, 'kind'));

        // The invoice issued as it was made (Phase F1): its issue above its making, naming no one.
        [$issued, $made, $draft, $mine, $theirs, $note, $added] = $timeline;
        $this->assertSame([['kind' => 'invoices', 'id' => $invoice, 'number' => 'INV-00001', 'revision' => 0, 'is_deposit' => false], 'issued', 'issued', null, '335.50', 'USD'], [$issued['document'], $issued['step'], $issued['status'], $issued['by'], $issued['total'], $issued['currency']]);
        $this->assertSame(['invoices', 'made', $this->user->name], [$made['document']['kind'], $made['step'], $made['by']]);
        $this->assertSame([['kind' => 'quotations', 'id' => $quotation, 'number' => null, 'revision' => 0, 'is_deposit' => false], 'made', 'draft', $this->user->name], [$draft['document'], $draft['step'], $draft['status'], $draft['by']]);
        // The contact's own activity is about nothing else; the opportunity's call and note name it.
        $this->assertSame(['Send the catalogue', null], [$mine['subject'], $mine['for']]);
        $named = ['kind' => 'opportunity', 'id' => $this->opportunity, 'name' => 'Uddin Traders - racking'];
        $this->assertSame([['Call Rahim', $named], ['They need it before Eid.', $named]], [[$theirs['subject'], $theirs['for']], [$note['text'], $note['for']]]);
        $this->assertSame('Added opportunity "Uddin Traders - racking" for contact "Uddin Traders"', $added['text']);
        $this->assertSame(['invoices', 'invoices', 'quotations'], array_column(array_column($this->timeline('contact', $this->customer->id, 'documents'), 'document'), 'kind'));

        // A lead converted into it brings its own story.
        $lead = $this->postJson('/api/v1/crm/leads', ['name' => 'Karim Ahmed', 'business_name' => 'Uddin Traders', 'phone' => '+8801711000000', 'source_id' => $this->getJson('/api/v1/crm/leads/options')->json('data.sources.0.id'), 'customer_need' => 'Shelving'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/crm/leads/{$lead}/qualify")->assertOk();
        $stage = $this->getJson("/api/v1/crm/leads/{$lead}/conversion")->json('data.defaults.stage_id');
        $this->postJson("/api/v1/crm/leads/{$lead}/convert", ['contact_id' => $this->customer->id, 'opportunity' => ['name' => 'Uddin Traders - shelving', 'stage_id' => $stage, 'expected_value' => '900', 'currency' => 'USD', 'expected_close_date' => '2026-12-31']])->assertOk();
        $this->assertSame([
            'Added opportunity "Uddin Traders - shelving" from lead "Karim Ahmed"',
            'Converted lead "Karim Ahmed" into opportunity "Uddin Traders - shelving" for contact "Uddin Traders"',
            'Qualified lead "Karim Ahmed"',
            'Added lead "Karim Ahmed"',
            'Added opportunity "Uddin Traders - racking" for contact "Uddin Traders"',
        ], array_column($this->timeline('contact', $this->customer->id, 'changes'), 'text'));

        // A member sees only what they may: none of the owner's opportunities and leads, activities only with View Activities,
        // documents of the kinds they may view, and their totals with Sales' values.
        Sanctum::actingAs($this->member(['contacts.view', 'crm.opportunities.view', 'crm.leads.view']));
        $this->assertSame([], $this->timeline('contact', $this->customer->id));
        Sanctum::actingAs($this->member(['contacts.view', 'sales.invoices.view']));
        $this->assertSame([['invoices', null], ['invoices', null]], array_map(fn (array $entry) => [$entry['document']['kind'], $entry['total']], $this->timeline('contact', $this->customer->id)));
        Sanctum::actingAs($this->member(['contacts.view', 'sales.invoices.view', 'sales.values.view', 'crm.activities.view']));
        $this->assertSame(['document', 'document', 'activity'], array_column($this->timeline('contact', $this->customer->id), 'kind'));
        $this->assertSame('335.50', $this->timeline('contact', $this->customer->id, 'documents')[0]['total']);
        Sanctum::actingAs($this->member(['crm.opportunities.view', 'crm.records.all']));
        $this->getJson("/api/v1/crm/timeline/contact/{$this->customer->id}")->assertForbidden();

        // Another business's contact is not found. With CRM off its records stay readable, as an app's do once turned off.
        Sanctum::actingAs($this->user);
        $theirs = $this->businessOf(User::factory()->create())->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Theirs']);
        $this->getJson("/api/v1/crm/timeline/contact/{$theirs->id}")->assertNotFound();
        $this->postJson('/api/v1/apps/crm/deactivate')->assertOk();
        $this->assertCount(11, $this->timeline('contact', $this->customer->id));
    }
}
