<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 17 Phase F3b: Settings → CRM's e-mail templates - a name, a subject and
 * a message, kept by whoever manages CRM settings - offered on an e-mail's
 * form with their placeholders filled in for the lead, opportunity or
 * contact and the sender. Monday 16 November 2026.
 */
class CrmEmailTemplatesTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private int $opportunity;

    private const FOLLOW_UP = [
        'name'    => 'Quotation follow-up',
        'subject' => 'Your quotation for {opportunity_name}',
        'body'    => "Dear {contact_name},\n\nHow did {company_name} find our quotation? {not_a_placeholder}\n\n{user_name}\n{business_name}",
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $crm = Application::create(['app_key' => 'crm', 'name' => 'CRM', 'family_key' => 'crm', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 20, 'is_active' => true, 'status' => 'available']);
        $crm->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-11-16 10:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->business->forceFill(['base_currency' => 'USD'])->save();
        $this->postJson('/api/v1/apps/crm/activate')->assertOk();

        $contact           = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin Traders', 'contact_person' => 'Rahim Uddin', 'email' => 'rahim@uddin.test']);
        $stage             = $this->getJson('/api/v1/business-settings/crm')->json('data.pipelines.0.stages.0.id');
        $this->opportunity = $this->postJson('/api/v1/crm/opportunities', [
            'name' => 'Uddin Traders - chairs', 'contact_id' => $contact->id, 'stage_id' => $stage, 'expected_value' => '2000', 'currency' => 'USD', 'expected_close_date' => '2026-12-20',
        ])->assertCreated()->json('data.id');
    }

    public function test_settings_keep_the_templates_and_an_email_starts_from_one_filled_in_for_its_record(): void
    {
        $id = $this->postJson('/api/v1/business-settings/crm/email-templates', self::FOLLOW_UP)->assertCreated()
            ->assertJsonPath('message', 'Template added.')
            ->assertJsonPath('data', ['id' => $this->business->crmEmailTemplates()->value('id'), ...self::FOLLOW_UP])
            ->json('data.id');
        $this->postJson('/api/v1/business-settings/crm/email-templates', ['name' => 'A thank you', 'subject' => 'Thank you, {contact_name}', 'body' => 'Thank you for your order.'])->assertCreated();
        $this->assertSame(['A thank you', 'Quotation follow-up'], array_column($this->getJson('/api/v1/business-settings/crm')->assertOk()->json('data.email_templates'), 'name'));

        // One name each, and a subject of one line.
        $this->postJson('/api/v1/business-settings/crm/email-templates', [...self::FOLLOW_UP, 'name' => ' Quotation follow-up '])->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'The business has a template by that name already.']);
        $this->putJson("/api/v1/business-settings/crm/email-templates/{$id}", [...self::FOLLOW_UP, 'subject' => "Two\nlines"])->assertUnprocessable()
            ->assertJsonValidationErrors(['subject' => 'Keep the subject to one line.']);
        $this->putJson("/api/v1/business-settings/crm/email-templates/{$id}", [...self::FOLLOW_UP, 'name' => 'Quotation chase'])->assertOk()
            ->assertJsonPath('message', 'Template saved.')->assertJsonPath('data.name', 'Quotation chase');

        // An opportunity's e-mail: its contact person and company, its name, the sender and the business. An unknown placeholder stays.
        $templates = $this->getJson("/api/v1/crm/emails/draft/opportunity/{$this->opportunity}")->assertOk()->json('data.templates');
        $this->assertSame(['A thank you', 'Quotation chase'], array_column($templates, 'name'));
        $this->assertSame(
            ['Your quotation for Uddin Traders - chairs', "Dear Rahim Uddin,\n\nHow did Uddin Traders find our quotation? {not_a_placeholder}\n\n{$this->user->name}\nMain Business"],
            [$templates[1]['subject'], $templates[1]['body']],
        );

        // A lead's: its own name and business, and no opportunity.
        $lead     = $this->postJson('/api/v1/crm/leads', ['name' => 'Karim Ahmed', 'business_name' => 'Karim Stores', 'email' => 'karim@stores.test', 'source_id' => $this->getJson('/api/v1/crm/leads/options')->json('data.sources.0.id')])->assertCreated()->json('data.id');
        $template = $this->getJson("/api/v1/crm/emails/draft/lead/{$lead}")->json('data.templates.1');
        $this->assertSame(['Your quotation for ', "Dear Karim Ahmed,\n\nHow did Karim Stores find our quotation? {not_a_placeholder}\n\n{$this->user->name}\nMain Business"], [$template['subject'], $template['body']]);

        $this->deleteJson("/api/v1/business-settings/crm/email-templates/{$id}")->assertOk()->assertJsonPath('message', 'Template deleted.');
        $this->assertSame(['A thank you'], array_column($this->getJson("/api/v1/crm/emails/draft/opportunity/{$this->opportunity}")->json('data.templates'), 'name'));
        $this->assertTrue(ActivityLog::query()->where('description', 'Added the e-mail template "Quotation follow-up"')->exists());
        $this->assertTrue(ActivityLog::query()->where('description', 'Deleted the e-mail template "Quotation chase"')->exists());
    }

    public function test_only_whoever_manages_crm_settings_changes_them_and_whoever_sends_uses_them(): void
    {
        $id = $this->postJson('/api/v1/business-settings/crm/email-templates', self::FOLLOW_UP)->assertCreated()->json('data.id');

        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Seller', 'permissions' => ['crm.opportunities.view', 'crm.records.all', 'crm.emails.send']]);
        $this->business->members()->create(['user_id' => $user->id, 'name' => $user->name, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/business-settings/crm/email-templates', [...self::FOLLOW_UP, 'name' => 'Mine'])->assertForbidden();
        $this->putJson("/api/v1/business-settings/crm/email-templates/{$id}", self::FOLLOW_UP)->assertForbidden();
        $this->deleteJson("/api/v1/business-settings/crm/email-templates/{$id}")->assertForbidden();
        $this->assertStringContainsString($user->name, $this->getJson("/api/v1/crm/emails/draft/opportunity/{$this->opportunity}")->assertOk()->json('data.templates.0.body'));

        // Another business's template is not found.
        Sanctum::actingAs($this->user);
        $theirs = $this->businessOf(User::factory()->create())->crmEmailTemplates()->create(self::FOLLOW_UP);
        $this->putJson("/api/v1/business-settings/crm/email-templates/{$theirs->id}", [...self::FOLLOW_UP, 'name' => 'Theirs'])->assertNotFound();
    }
}
