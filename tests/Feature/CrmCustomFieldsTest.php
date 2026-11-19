<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\Contact;
use App\Models\User\CrmLead;
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
 * Doc 17 Phase I4: a business's own fields for leads and opportunities
 * (sections 17.6, 17.12 and 17.26) - kept in Settings → CRM in their order,
 * each text, a number, a date, a choice or yes or no, optional or required -
 * asked by the record's form, checked to be of their type, and shown on its
 * page. One switched off keeps its values, hidden; one with values stays.
 */
class CrmCustomFieldsTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const FIELDS = '/api/v1/business-settings/crm/custom-fields';

    private User $user;

    private Workspace $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $crm = Application::create(['app_key' => 'crm', 'name' => 'CRM', 'family_key' => 'crm', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 20, 'is_active' => true, 'status' => 'available']);
        $crm->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-11-18 06:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->business->forceFill(['base_currency' => 'USD'])->save();
        $this->business->businessSetting()->create([
            'user_id' => $this->user->id, 'business_name' => 'Main Business', 'language' => 'en', 'country' => 'BD', 'state' => 'Dhaka',
            'city'    => 'Dhaka', 'zip_code' => '1207', 'address_line_1' => 'Road 1', 'timezone' => 'Asia/Dhaka', 'stock_accounting_method' => 'fifo',
        ]);
        $this->postJson('/api/v1/apps/crm/activate')->assertOk();
    }

    /** A member whose role holds $permissions. */
    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    private function field(string $record, string $name, string $type, array $fields = []): int
    {
        return $this->postJson(self::FIELDS, ['record' => $record, 'name' => $name, 'type' => $type, ...$fields])->assertCreated()->json('data.id');
    }

    /** A lead from the first source with $custom's values: the API's answer. */
    private function lead(array $custom, string $name = 'Karim Ahmed'): \Illuminate\Testing\TestResponse
    {
        $source = $this->getJson('/api/v1/crm/leads/options')->json('data.sources.0.id');

        return $this->postJson('/api/v1/crm/leads', ['name' => $name, 'email' => 'karim@stores.test', 'source_id' => $source, 'custom_fields' => $custom]);
    }

    public function test_custom_fields_are_kept_in_settings_in_their_order(): void
    {
        $this->getJson(self::FIELDS)->assertOk()->assertExactJson(['data' => []]);

        $this->postJson(self::FIELDS, ['record' => 'lead', 'name' => 'Industry', 'type' => 'text', 'is_required' => true])->assertCreated()
            ->assertJsonPath('message', 'Field added.')
            ->assertJsonPath('data.record', 'lead')
            ->assertJsonPath('data.type', 'text')
            ->assertJsonPath('data.options', [])
            ->assertJsonPath('data.is_required', true)
            ->assertJsonPath('data.is_active', true);
        $size = $this->postJson(self::FIELDS, ['record' => 'lead', 'name' => 'Company Size', 'type' => 'choice', 'options' => ['Small', ' Medium ', 'Large', '']])->assertCreated()
            ->assertJsonPath('data.options', ['Small', 'Medium', 'Large'])
            ->json('data.id');
        $this->field('opportunity', 'Industry', 'text');
        $this->assertTrue(ActivityLog::query()->where('description', 'Added the custom field "Industry" for leads')->exists());
        $this->assertTrue(ActivityLog::query()->where('description', 'Added the custom field "Industry" for opportunities')->exists());

        // Refused: a choice without options, options on another type, an option twice, a name its record has, a record changed, an unknown type.
        $this->postJson(self::FIELDS, ['record' => 'lead', 'name' => 'Tier', 'type' => 'choice', 'options' => ['', ' ']])->assertUnprocessable()->assertJsonValidationErrors(['options' => 'Give the choice its options, one to a line.']);
        $this->postJson(self::FIELDS, ['record' => 'lead', 'name' => 'Notes', 'type' => 'text', 'options' => ['A']])->assertUnprocessable()->assertJsonValidationErrors(['options' => 'Only a choice has options.']);
        $this->postJson(self::FIELDS, ['record' => 'lead', 'name' => 'Tier', 'type' => 'choice', 'options' => ['Gold', 'Gold']])->assertUnprocessable()->assertJsonValidationErrors('options.1');
        $this->postJson(self::FIELDS, ['record' => 'lead', 'name' => 'Industry', 'type' => 'number'])->assertUnprocessable()->assertJsonValidationErrors(['name' => 'Another field for leads has this name.']);
        $this->putJson(self::FIELDS."/{$size}", ['record' => 'opportunity', 'name' => 'Company Size', 'type' => 'choice', 'options' => ['Small']])->assertUnprocessable()->assertJsonValidationErrors(['record' => 'A field stays with leads or opportunities, as it was added.']);
        $this->postJson(self::FIELDS, ['record' => 'lead', 'name' => 'Colour', 'type' => 'colour'])->assertUnprocessable()->assertJsonValidationErrors('type');

        // Put in another order - every one of the record's, once each.
        $industry = $this->getJson(self::FIELDS)->json('data.0.id');
        $this->putJson(self::FIELDS.'/order', ['record' => 'lead', 'ids' => [$size, $industry]])->assertOk()
            ->assertJsonPath('message', 'Order saved.')
            ->assertJsonPath('data.0.name', 'Company Size');
        $this->putJson(self::FIELDS.'/order', ['record' => 'lead', 'ids' => [$size]])->assertUnprocessable()->assertJsonValidationErrors(['ids' => 'Put every field in the order, once each.']);
        $this->assertSame(['Company Size', 'Industry'], array_column($this->getJson('/api/v1/crm/leads/options')->json('data.custom_fields'), 'name'));

        // Manage CRM Settings keeps them.
        Sanctum::actingAs($this->member(['settings.view', 'crm.rules.manage']));
        $this->getJson(self::FIELDS)->assertForbidden();
        Sanctum::actingAs($this->member(['settings.view', 'crm.settings.manage']));
        $this->getJson(self::FIELDS)->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_a_lead_s_custom_fields_are_asked_checked_and_shown(): void
    {
        $industry  = $this->field('lead', 'Industry', 'text', ['is_required' => true]);
        $size      = $this->field('lead', 'Company Size', 'choice', ['options' => ['Small', 'Medium', 'Large']]);
        $employees = $this->field('lead', 'Employees', 'number');
        $met       = $this->field('lead', 'Met On', 'date');
        $budget    = $this->field('lead', 'Has a Budget', 'yes_no', ['is_required' => true]);

        $this->getJson('/api/v1/crm/leads/options')->assertOk()
            ->assertJsonPath('data.custom_fields.1', ['id' => $size, 'name' => 'Company Size', 'type' => 'choice', 'options' => ['Small', 'Medium', 'Large'], 'is_required' => false])
            ->assertJsonCount(5, 'data.custom_fields');

        // Refused: the required ones left empty, and each of another type.
        $this->lead([])->assertUnprocessable()->assertJsonValidationErrors(["custom_fields.{$industry}" => 'Fill in Industry.', "custom_fields.{$budget}" => 'Answer Has a Budget.']);
        $this->lead([$industry => 'Retail', $budget => 'maybe', $employees => 'many', $met => '2026-02-30', $size => 'Huge'])->assertUnprocessable()->assertJsonValidationErrors([
            "custom_fields.{$budget}"    => 'Answer yes or no.',
            "custom_fields.{$employees}" => 'Enter a number.',
            "custom_fields.{$met}"       => 'Enter a date.',
            "custom_fields.{$size}"      => 'Choose one of its options.',
        ]);

        // Kept by type, shown in their order - no as an answer too.
        $lead = $this->lead([$industry => ' Retail ', $size => 'Medium', $employees => '25', $met => '2026-11-01', $budget => false])->assertCreated()->json('data');
        $this->assertSame([
            ['id' => $industry, 'name' => 'Industry', 'type' => 'text', 'value' => 'Retail'],
            ['id' => $size, 'name' => 'Company Size', 'type' => 'choice', 'value' => 'Medium'],
            ['id' => $employees, 'name' => 'Employees', 'type' => 'number', 'value' => '25'],
            ['id' => $met, 'name' => 'Met On', 'type' => 'date', 'value' => '2026-11-01'],
            ['id' => $budget, 'name' => 'Has a Budget', 'type' => 'yes_no', 'value' => false],
        ], $lead['custom_fields']);

        // Changed: left out, they stay; given, each takes its value - an emptied one goes.
        $form = ['name' => 'Karim Ahmed', 'email' => 'karim@stores.test', 'source_id' => $lead['source']['id']];
        $lead = $this->putJson("/api/v1/crm/leads/{$lead['id']}", [...$form, 'version' => $lead['version']])->assertOk()->json('data');
        $this->assertSame('Retail', $lead['custom_fields'][0]['value']);
        $lead = $this->putJson("/api/v1/crm/leads/{$lead['id']}", [...$form, 'version' => $lead['version'], 'custom_fields' => [$industry => 'Wholesale', $size => 'Medium', $employees => '', $met => '2026-11-01', $budget => true]])->assertOk()->json('data');
        $this->assertSame(['Wholesale', 'Medium', null, '2026-11-01', true], array_column($lead['custom_fields'], 'value'));
        $this->assertSame([$industry => 'Wholesale', $size => 'Medium', $met => '2026-11-01', $budget => true], CrmLead::query()->find($lead['id'])->custom_fields);
        $this->assertContains('custom_fields', ActivityLog::query()->where('description', 'Updated lead "Karim Ahmed"')->latest('id')->first()->properties['fields']);

        // An option taken away: the lead keeps its own, which another cannot have.
        $this->putJson(self::FIELDS."/{$size}", ['name' => 'Company Size', 'type' => 'choice', 'options' => ['Small', 'Large']])->assertOk();
        $lead = $this->putJson("/api/v1/crm/leads/{$lead['id']}", [...$form, 'version' => $lead['version'], 'custom_fields' => [$industry => 'Wholesale', $size => 'Medium', $met => '2026-11-01', $budget => true]])->assertOk()->json('data');
        $this->lead([$industry => 'Retail', $size => 'Medium', $budget => true], 'Rahim Uddin')->assertUnprocessable()->assertJsonValidationErrors("custom_fields.{$size}");

        // Switched off, a field leaves the form and page and keeps its values; with values, it keeps its type and stays.
        $this->putJson(self::FIELDS."/{$met}", ['name' => 'Met On', 'type' => 'date', 'is_active' => false])->assertOk();
        $this->assertNotContains('Met On', array_column($this->getJson("/api/v1/crm/leads/{$lead['id']}")->json('data.custom_fields'), 'name'));
        $this->assertSame('2026-11-01', CrmLead::query()->find($lead['id'])->custom_fields[$met]);
        $this->putJson(self::FIELDS."/{$met}", ['name' => 'Met On', 'type' => 'text', 'is_active' => false])->assertUnprocessable()->assertJsonValidationErrors(['type' => 'Leads have values for it, so its type stays.']);
        $this->deleteJson(self::FIELDS."/{$met}")->assertStatus(409)->assertJsonPath('message', 'Leads have values for it, so it stays. Switch it off instead.');
        $this->putJson(self::FIELDS."/{$employees}", ['name' => 'Employees', 'type' => 'text'])->assertOk()->assertJsonPath('data.type', 'text');
        $this->deleteJson(self::FIELDS."/{$employees}")->assertOk()->assertJsonPath('message', 'Field deleted.');
        $this->assertTrue(ActivityLog::query()->where('description', 'Deleted the custom field "Employees" for leads')->exists());
    }

    public function test_an_opportunity_s_custom_fields(): void
    {
        $seats = $this->field('opportunity', 'Seats', 'number', ['is_required' => true]);
        $nda   = $this->field('opportunity', 'Signed NDA', 'yes_no');
        $this->field('lead', 'Industry', 'text', ['is_required' => true]);

        $this->assertSame(['Seats', 'Signed NDA'], array_column($this->getJson('/api/v1/crm/opportunities/options')->assertOk()->json('data.custom_fields'), 'name'));

        $contact = Contact::create(['workspace_id' => $this->business->id, 'contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin Traders']);
        $stage   = $this->getJson('/api/v1/business-settings/crm')->json('data.pipelines.0.stages.0.id');
        $form    = ['name' => 'Uddin Traders - racking', 'contact_id' => $contact->id, 'stage_id' => $stage, 'expected_value' => '2000', 'currency' => 'USD', 'expected_close_date' => '2026-12-20'];

        $this->postJson('/api/v1/crm/opportunities', $form)->assertUnprocessable()->assertJsonValidationErrors(["custom_fields.{$seats}" => 'Fill in Seats.']);
        $opportunity = $this->postJson('/api/v1/crm/opportunities', [...$form, 'custom_fields' => [$seats => 12, $nda => '1']])->assertCreated()->json('data');
        $this->assertSame([['id' => $seats, 'name' => 'Seats', 'type' => 'number', 'value' => '12'], ['id' => $nda, 'name' => 'Signed NDA', 'type' => 'yes_no', 'value' => true]], $opportunity['custom_fields']);

        // A lead's fields are not an opportunity's.
        $this->assertSame(['Seats', 'Signed NDA'], array_column($this->getJson("/api/v1/crm/opportunities/{$opportunity['id']}")->json('data.custom_fields'), 'name'));

        // Changed without them they stay; given without the required one, refused.
        $edit        = array_diff_key($form, ['stage_id' => true]);
        $opportunity = $this->putJson("/api/v1/crm/opportunities/{$opportunity['id']}", [...$edit, 'version' => $opportunity['version']])->assertOk()->json('data');
        $this->assertSame('12', $opportunity['custom_fields'][0]['value']);
        $this->putJson("/api/v1/crm/opportunities/{$opportunity['id']}", [...$edit, 'version' => $opportunity['version'], 'custom_fields' => [$nda => false]])->assertUnprocessable()->assertJsonValidationErrors("custom_fields.{$seats}");
        $this->deleteJson(self::FIELDS."/{$seats}")->assertStatus(409)->assertJsonPath('message', 'Opportunities have values for it, so it stays. Switch it off instead.');
    }
}
