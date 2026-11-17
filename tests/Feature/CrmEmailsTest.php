<?php

namespace Tests\Feature;

use App\Jobs\SendCrmEmail;
use App\Mail\RecordMail;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\CrmOpportunity;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Repositories\User\CrmEmailRepository;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 17 Phase F3a: e-mail from a lead, an opportunity or a contact - to its
 * address to start, carrying its sales documents' PDFs and its files, sent
 * from the queue under the business's name, and kept on its timeline with
 * whether it went - only from a record in use that the user sees. Monday
 * 16 November 2026, Sales Lite; the customer is MakesSalesDocuments' Uddin
 * Traders.
 */
class CrmEmailsTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private int $opportunity;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        Mail::fake();
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200)]);
        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $crm = Application::create(['app_key' => 'crm', 'name' => 'CRM', 'family_key' => 'crm', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 20, 'is_active' => true, 'status' => 'available']);
        $crm->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-11-16 10:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->business->forceFill(['base_currency' => 'USD'])->save();
        $this->setUpSales();
        $this->customer->update(['email' => 'rahim@uddin.test', 'contact_person' => 'Rahim Uddin']);
        $this->postJson('/api/v1/apps/crm/activate')->assertOk();

        $stage             = $this->getJson('/api/v1/business-settings/crm')->json('data.pipelines.0.stages.0.id');
        $this->opportunity = $this->postJson('/api/v1/crm/opportunities', [
            'name' => 'Uddin Traders - chairs', 'contact_id' => $this->customer->id, 'stage_id' => $stage, 'expected_value' => '2000', 'currency' => 'USD', 'expected_close_date' => '2026-12-20',
        ])->assertCreated()->json('data.id');
    }

    private function email(array $fields): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/crm/emails', ['to' => 'rahim@uddin.test', 'subject' => 'Your quotation', 'body' => 'Dear Rahim, here it is.', ...$fields]);
    }

    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->business->members()->create(['user_id' => $user->id, 'name' => $user->name, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    public function test_an_email_from_an_opportunity_goes_to_its_contact_with_its_documents_and_files_and_stays_on_its_timeline(): void
    {
        $quotation = $this->draft('quotations', ['opportunity_id' => $this->opportunity]);
        $this->postJson("/api/v1/sales/quotations/{$quotation}/send")->assertOk();
        $this->draft('invoices', ['opportunity_id' => $this->opportunity]);
        $file = $this->post("/api/v1/crm/opportunities/{$this->opportunity}/attachments", ['files' => [UploadedFile::fake()->create('brochure.pdf', 40, 'application/pdf')]], ['Accept' => 'application/json'])
            ->assertCreated()->json('data.0.id');

        // To start: its contact's address and name, the user's and business's names, and what has gone out - not the draft invoice.
        $draft = $this->getJson("/api/v1/crm/emails/draft/opportunity/{$this->opportunity}")->assertOk()->json('data');
        $this->assertSame('rahim@uddin.test', $draft['to']);
        $this->assertSame("Dear Rahim Uddin,\n\n\n\nKind regards,\n{$this->user->name}\nMain Business", $draft['body']);
        $this->assertSame([['id' => $quotation, 'kind' => 'quotations', 'number' => 'QUO-00001', 'revision' => 0, 'is_deposit' => false]], $draft['documents']);
        $this->assertSame([[$file, 'brochure.pdf']], array_map(fn (array $row) => [$row['id'], $row['name']], $draft['files']));

        $this->travel(5)->minutes();
        $this->email(['opportunity_id' => $this->opportunity, 'cc' => ['sales@uddin.test'], 'documents' => [$quotation], 'files' => [$file]])->assertCreated()
            ->assertJsonPath('message', 'E-mail to rahim@uddin.test is on its way.');

        // Sent under the business's name, with the quotation's PDF and the file.
        Mail::assertSent(RecordMail::class, fn (RecordMail $mail) => $mail->hasTo('rahim@uddin.test') && $mail->hasCc('sales@uddin.test')
            && [$mail->mailSubject, $mail->senderName, array_column($mail->files, 'name')] === ['Your quotation', 'Main Business', ['Quotation-QUO-00001.pdf', 'brochure.pdf']]);

        // On its timeline, once: the e-mail, not the log entry that names it.
        $timeline = $this->getJson("/api/v1/crm/timeline/opportunity/{$this->opportunity}?show=emails")->assertOk()->json('data');
        $this->assertCount(1, $timeline);
        $this->assertSame(
            ['email', 'Your quotation', 'rahim@uddin.test', ['sales@uddin.test'], ['Quotation QUO-00001', 'brochure.pdf'], 'sent', $this->user->name, 'Dear Rahim, here it is.', null],
            [$timeline[0]['kind'], $timeline[0]['subject'], $timeline[0]['to'], $timeline[0]['cc'], $timeline[0]['attachments'], $timeline[0]['status'], $timeline[0]['by'], $timeline[0]['body'], $timeline[0]['for']],
        );
        $changes = array_column($this->getJson("/api/v1/crm/timeline/opportunity/{$this->opportunity}?show=changes")->json('data'), 'text');
        $this->assertSame([], array_values(array_filter($changes, fn (string $text) => str_starts_with($text, 'E-mailed'))));
        $this->assertTrue(ActivityLog::query()->where('model_type', CrmOpportunity::class)->where('description', 'E-mailed rahim@uddin.test from opportunity "Uddin Traders - chairs": "Your quotation"')->exists());

        // One that did not go says so, and why in a word.
        $failing = app(CrmEmailRepository::class)->create($this->business, ['opportunity_id' => $this->opportunity, 'to' => 'x@uddin.test', 'subject' => 'Gone', 'body' => 'x', 'sent_by' => $this->user->id]);
        (new SendCrmEmail($failing->id))->failed(new \RuntimeException('550 rejected'));
        $this->assertSame(['failed', 'The mail server did not accept it.'], [$failing->fresh()->status, $failing->fresh()->failure]);
    }

    public function test_one_goes_only_from_a_record_in_use_the_user_sees_carrying_only_its_own(): void
    {
        $stage = $this->getJson('/api/v1/business-settings/crm')->json('data.pipelines.0.stages.0.id');
        $other = $this->postJson('/api/v1/crm/opportunities', ['name' => 'Uddin Traders - desks', 'contact_id' => $this->customer->id, 'stage_id' => $stage, 'expected_value' => '500', 'currency' => 'USD', 'expected_close_date' => '2026-12-20'])->json('data.id');
        $theirs = $this->draft('quotations', ['opportunity_id' => $other]);
        $this->postJson("/api/v1/sales/quotations/{$theirs}/send")->assertOk();
        $draftInvoice = $this->draft('invoices', ['opportunity_id' => $this->opportunity]);

        $this->email(['opportunity_id' => $this->opportunity, 'documents' => [$theirs]])->assertUnprocessable()->assertJsonValidationErrors(['documents' => 'Choose among its sales documents that have gone out.']);
        $this->email(['opportunity_id' => $this->opportunity, 'documents' => [$draftInvoice]])->assertUnprocessable()->assertJsonValidationErrors('documents');
        $this->email(['opportunity_id' => $this->opportunity, 'contact_id' => $this->customer->id])->assertUnprocessable()->assertJsonValidationErrors(['regarding' => 'Send it from one lead, opportunity or contact.']);
        $this->email(['opportunity_id' => $this->opportunity, 'subject' => "Two\nlines"])->assertUnprocessable()->assertJsonValidationErrors(['subject' => 'Keep the subject to one line.']);

        // A lead: to its own address, with no documents - and not once converted.
        $lead = $this->postJson('/api/v1/crm/leads', ['name' => 'Karim Ahmed', 'email' => 'karim@stores.test', 'source_id' => $this->getJson('/api/v1/crm/leads/options')->json('data.sources.0.id'), 'customer_need' => 'Shelving'])->assertCreated()->json('data.id');
        $this->assertSame(['karim@stores.test', []], [$this->getJson("/api/v1/crm/emails/draft/lead/{$lead}")->json('data.to'), $this->getJson("/api/v1/crm/emails/draft/lead/{$lead}")->json('data.documents')]);
        $this->postJson("/api/v1/crm/leads/{$lead}/qualify")->assertOk();
        $stageId = $this->getJson("/api/v1/crm/leads/{$lead}/conversion")->json('data.defaults.stage_id');
        $this->postJson("/api/v1/crm/leads/{$lead}/convert", ['contact_id' => $this->customer->id, 'opportunity' => ['name' => 'Karim - shelving', 'stage_id' => $stageId, 'expected_value' => '900', 'currency' => 'USD', 'expected_close_date' => '2026-12-31']])->assertOk();
        $this->email(['lead_id' => $lead, 'to' => 'karim@stores.test'])->assertStatus(409)->assertJsonPath('message', 'This lead was converted: e-mail its contact or opportunity instead.');

        // Not from an archived opportunity.
        $this->postJson("/api/v1/crm/opportunities/{$other}/archive")->assertOk();
        $this->email(['opportunity_id' => $other])->assertStatus(409)->assertJsonPath('message', 'This opportunity is archived: restore it to e-mail from it.');

        // Not without Send E-mail, and not from a record the user does not see.
        Sanctum::actingAs($this->member(['crm.opportunities.view']));
        $this->getJson("/api/v1/crm/emails/draft/opportunity/{$this->opportunity}")->assertForbidden();
        Sanctum::actingAs($this->member(['crm.opportunities.view', 'crm.emails.send']));
        $this->email(['opportunity_id' => $this->opportunity])->assertNotFound();
        Mail::assertNothingSent();
    }

    public function test_a_contacts_emails_stay_on_its_timeline_move_when_it_is_merged_and_keep_it_from_being_deleted(): void
    {
        $other = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin Trading', 'email' => 'info@uddintrading.test']);
        $this->assertSame(['info@uddintrading.test', "Dear Uddin Trading,\n\n\n\nKind regards,\n{$this->user->name}\nMain Business"], array_values(array_intersect_key($this->getJson("/api/v1/crm/emails/draft/contact/{$other->id}")->json('data'), array_flip(['to', 'body']))));
        $this->email(['contact_id' => $other->id, 'to' => 'info@uddintrading.test', 'subject' => 'Catalogue'])->assertCreated();

        $this->deleteJson("/api/v1/contacts/{$other->id}")->assertStatus(409)->assertJsonPath('message', 'This contact has CRM e-mails, so it cannot be deleted. Deactivate it instead.');

        // Merged into the customer, it goes with it; the opportunity's e-mail shows there too, naming it.
        $this->email(['opportunity_id' => $this->opportunity, 'subject' => 'Delivery dates'])->assertCreated();
        $this->getJson("/api/v1/contacts/{$this->customer->id}/merge?merged_id={$other->id}")->assertOk()->assertJsonPath('data.moving.emails', 1);
        $this->postJson("/api/v1/contacts/{$this->customer->id}/merge", ['merged_id' => $other->id])->assertOk();

        $emails = $this->getJson("/api/v1/crm/timeline/contact/{$this->customer->id}?show=emails")->assertOk()->json('data');
        $this->assertSame([['Delivery dates', ['kind' => 'opportunity', 'id' => $this->opportunity, 'name' => 'Uddin Traders - chairs']], ['Catalogue', null]], array_map(fn (array $entry) => [$entry['subject'], $entry['for']], $emails));
        $this->email(['contact_id' => $other->id, 'to' => 'info@uddintrading.test'])->assertStatus(409);
    }
}
