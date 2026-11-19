<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\Contact;
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
 * Doc 17 Phase I3: marketing consent (section 17.25), apart from sales
 * follow-up - a lead's and a contact's answers, each for a purpose (Settings
 * → CRM's list) on a channel, given or refused, where from, when, by whom,
 * its expiry and evidence; a consent given withdrawn, its history kept; a
 * lead's going to its contact when it is converted, and a merged contact's
 * to the one it was merged into.
 */
class CrmMarketingConsentTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    /** The consent purposes by name, read once by the business's owner. */
    private array $purposes = [];

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

    private function lead(string $name): int
    {
        $source = $this->getJson('/api/v1/crm/leads/options')->json('data.sources.0.id');

        return $this->postJson('/api/v1/crm/leads', ['name' => $name, 'email' => 'karim@stores.test', 'source_id' => $source, 'customer_need' => 'Shelving'])->assertCreated()->json('data.id');
    }

    private function purpose(string $name): int
    {
        return ($this->purposes[$name] ??= collect($this->getJson('/api/v1/business-settings/crm')->json('data.lists.consent_purpose'))->firstWhere('name', $name)['id']);
    }

    /** An answer for $path's record (crm/leads/1, crm/contacts/2): Newsletters by e-mail, given on a web form, unless $fields say otherwise. */
    private function answer(string $path, array $fields = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/api/v1/{$path}/consents", ['purpose_id' => $this->purpose('Newsletters'), 'channel' => 'email', 'status' => 'given', 'source' => 'web_form', ...$fields]);
    }

    public function test_a_lead_s_consent_is_recorded_withdrawn_and_kept_as_its_history(): void
    {
        $lead = $this->lead('Karim Ahmed');

        $this->getJson("/api/v1/crm/leads/{$lead}/consents")->assertOk()
            ->assertJsonPath('data.consents', [])
            ->assertJsonPath('data.purposes.*.name', ['Promotions and Offers', 'Newsletters', 'Product Updates', 'Events and Invitations', 'Feedback and Surveys']);

        // Given on the web form three days ago at 10:30 in Dhaka, until next November.
        $this->answer("crm/leads/{$lead}", ['recorded_on' => '2026-11-15', 'recorded_time' => '10:30', 'expires_on' => '2027-11-15', 'evidence' => 'Form #A12'])->assertCreated()
            ->assertJsonPath('message', 'Consent recorded.')
            ->assertJsonPath('data.consents.0.purpose.name', 'Newsletters')
            ->assertJsonPath('data.consents.0.status', 'given')
            ->assertJsonPath('data.consents.0.standing', true)
            ->assertJsonPath('data.consents.0.recorded_at', '2026-11-15T04:30:00+00:00')
            ->assertJsonPath('data.consents.0.recorded_by', $this->user->name)
            ->assertJsonPath('data.consents.0.expires_on', '2027-11-15')
            ->assertJsonPath('data.consents.0.evidence', 'Form #A12');
        $promotions = $this->purpose('Promotions and Offers');
        $this->answer("crm/leads/{$lead}", ['purpose_id' => $promotions, 'channel' => 'sms', 'status' => 'refused', 'source' => 'phone'])->assertCreated()
            ->assertJsonPath('data.consents.0.status', 'refused')
            ->assertJsonPath('data.consents.1.status', 'given');
        $this->assertTrue(ActivityLog::query()->where('description', 'Recorded marketing consent given for "Newsletters" by e-mail')->exists());
        $this->assertTrue(ActivityLog::query()->where('description', 'Recorded marketing consent refused for "Promotions and Offers" by SMS')->exists());

        // Refused: an expiry on a refusal, an expiry not after the day, a moment to come, what is not a consent purpose, an unknown channel.
        $this->answer("crm/leads/{$lead}", ['status' => 'refused', 'expires_on' => '2027-01-01'])->assertUnprocessable()->assertJsonValidationErrors(['expires_on' => 'Only a consent given expires.']);
        $this->answer("crm/leads/{$lead}", ['recorded_on' => '2026-11-15', 'expires_on' => '2026-11-15'])->assertUnprocessable()->assertJsonValidationErrors(['expires_on' => 'A consent expires after the day it was given.']);
        $this->answer("crm/leads/{$lead}", ['recorded_on' => '2026-11-19'])->assertUnprocessable()->assertJsonValidationErrors(['recorded_on' => 'That moment has not come yet.']);
        $source = $this->getJson('/api/v1/crm/leads/options')->json('data.sources.0.id');
        $this->answer("crm/leads/{$lead}", ['purpose_id' => $source])->assertUnprocessable()->assertJsonValidationErrors(['purpose_id' => 'Choose one of the consent purposes switched on in Settings → CRM.']);
        $this->answer("crm/leads/{$lead}", ['channel' => 'fax'])->assertUnprocessable()->assertJsonValidationErrors('channel');

        // Withdrawn by its reply: kept, with when, by whom and how - not twice, and never a refusal.
        $consents = $this->getJson("/api/v1/crm/leads/{$lead}/consents")->json('data.consents');
        [$refused, $given] = [$consents[0]['id'], $consents[1]['id']];
        $this->postJson("/api/v1/crm/leads/{$lead}/consents/{$given}/withdraw", ['withdrawn_on' => '2026-11-14'])->assertUnprocessable()->assertJsonValidationErrors(['withdrawn_on' => 'A consent is withdrawn after it was given.']);
        $this->postJson("/api/v1/crm/leads/{$lead}/consents/{$given}/withdraw", ['note' => 'Replied STOP'])->assertOk()
            ->assertJsonPath('message', 'Consent withdrawn.')
            ->assertJsonPath('data.consents.1.status', 'withdrawn')
            ->assertJsonPath('data.consents.1.withdrawn_at', '2026-11-18T06:00:00+00:00')
            ->assertJsonPath('data.consents.1.withdrawn_by', $this->user->name)
            ->assertJsonPath('data.consents.1.withdrawal_note', 'Replied STOP');
        $this->assertTrue(ActivityLog::query()->where('description', 'Withdrew marketing consent for "Newsletters" by e-mail')->exists());
        $this->postJson("/api/v1/crm/leads/{$lead}/consents/{$given}/withdraw")->assertStatus(409)->assertJsonPath('message', 'Only a consent given that still stands for its purpose and channel is withdrawn.');
        $this->postJson("/api/v1/crm/leads/{$lead}/consents/{$refused}/withdraw")->assertStatus(409);

        // Given again later: the latest stands, the withdrawn one is its history - and only the standing one is withdrawn.
        $this->travel(1)->hours();
        $again = $this->answer("crm/leads/{$lead}", ['expires_on' => '2026-11-20'])->assertCreated()->json('data.consents');
        $this->assertSame([['given', true], ['refused', true], ['withdrawn', false]], array_map(fn (array $consent) => [$consent['status'], $consent['standing']], $again));
        $this->postJson("/api/v1/crm/leads/{$lead}/consents/{$given}/withdraw")->assertStatus(409);

        // Past its day, a consent given reads expired.
        $this->travel(3)->days();
        $this->getJson("/api/v1/crm/leads/{$lead}/consents")->assertJsonPath('data.consents.0.status', 'expired');

        // An archived lead still takes a refusal; whoever only views leads reads its consent but records none.
        $this->postJson("/api/v1/crm/leads/{$lead}/archive")->assertOk();
        $this->answer("crm/leads/{$lead}", ['status' => 'refused'])->assertCreated();
        Sanctum::actingAs($this->member(['crm.leads.view', 'crm.records.all']));
        $this->getJson("/api/v1/crm/leads/{$lead}/consents")->assertOk()->assertJsonCount(4, 'data.consents');
        $this->answer("crm/leads/{$lead}", ['status' => 'refused'])->assertForbidden();
        Sanctum::actingAs($this->member(['crm.leads.view']));
        $this->getJson("/api/v1/crm/leads/{$lead}/consents")->assertNotFound();
    }

    public function test_a_converted_lead_s_consent_goes_to_its_contact_and_a_merged_contact_s_to_the_one_kept(): void
    {
        $lead = $this->lead('Karim Ahmed');
        $this->answer("crm/leads/{$lead}", ['purpose_id' => $this->purpose('Promotions and Offers')])->assertCreated();
        $this->postJson("/api/v1/crm/leads/{$lead}/qualify")->assertOk();
        $stage   = collect($this->getJson('/api/v1/business-settings/crm')->json('data.pipelines.0.stages'))->firstWhere('name', 'Qualified')['id'];
        $contact = $this->postJson("/api/v1/crm/leads/{$lead}/convert", ['contact_id' => null, 'opportunity' => [
            'name' => 'Karim - shelving', 'stage_id' => $stage, 'expected_value' => '900', 'currency' => 'USD', 'expected_close_date' => '2026-12-31',
        ]])->assertOk()->json('data.conversion.contact.id');

        // The contact has it now; the converted lead keeps showing it, but changes go to the contact.
        $this->getJson("/api/v1/crm/contacts/{$contact}/consents")->assertOk()
            ->assertJsonPath('data.consents.0.purpose.name', 'Promotions and Offers')
            ->assertJsonPath('data.consents.0.standing', true);
        $this->getJson("/api/v1/crm/leads/{$lead}/consents")->assertOk()->assertJsonCount(1, 'data.consents');
        $this->answer("crm/leads/{$lead}")->assertStatus(409)->assertJsonPath('message', 'This lead was converted: its marketing consent went with it to its contact, so record it there.');

        // The contact refuses later: that answer stands.
        $this->travel(1)->hours();
        $this->answer("crm/contacts/{$contact}", ['purpose_id' => $this->purpose('Promotions and Offers'), 'status' => 'refused', 'source' => 'email'])->assertCreated()
            ->assertJsonPath('data.consents.0.status', 'refused')
            ->assertJsonPath('data.consents.0.standing', true)
            ->assertJsonPath('data.consents.1.standing', false);

        // Merged into the contact, another's answers move with it - and the merged one takes none.
        $copy = Contact::create(['workspace_id' => $this->business->id, 'contact_type' => 'individual', 'role' => 'customer', 'name' => 'Karim A.']);
        $this->answer("crm/contacts/{$copy->id}", ['channel' => 'phone', 'source' => 'in_person'])->assertCreated();
        $this->getJson("/api/v1/contacts/{$contact}/merge?merged_id={$copy->id}")->assertOk()->assertJsonPath('data.moving.consents', 1);
        $this->postJson("/api/v1/contacts/{$contact}/merge", ['merged_id' => $copy->id])->assertOk();
        $this->assertCount(3, $this->getJson("/api/v1/crm/contacts/{$contact}/consents")->json('data.consents'));
        $this->answer("crm/contacts/{$copy->id}")->assertStatus(409)->assertJsonPath('message', 'This contact was merged into another: record its marketing consent there.');

        // A contact with consent records keeps them: deactivated, not deleted.
        $other = Contact::create(['workspace_id' => $this->business->id, 'contact_type' => 'individual', 'role' => 'customer', 'name' => 'Rahim Uddin']);
        $this->answer("crm/contacts/{$other->id}", ['status' => 'refused'])->assertCreated();
        $this->deleteJson("/api/v1/contacts/{$other->id}")->assertStatus(409)->assertJsonPath('message', 'This contact has marketing consent records, so it cannot be deleted. Deactivate it instead.');

        // Whoever views contacts reads their consent; recording it needs Edit Contacts.
        Sanctum::actingAs($this->member(['contacts.view']));
        $this->getJson("/api/v1/crm/contacts/{$other->id}/consents")->assertOk()->assertJsonCount(1, 'data.consents');
        $this->answer("crm/contacts/{$other->id}")->assertForbidden();
        Sanctum::actingAs($this->member(['contacts.view', 'contacts.edit']));
        $this->answer("crm/contacts/{$other->id}", ['channel' => 'sms'])->assertCreated();
    }

    public function test_the_consent_purposes_are_a_crm_list(): void
    {
        $this->assertSame(
            ['Promotions and Offers', 'Newsletters', 'Product Updates', 'Events and Invitations', 'Feedback and Surveys'],
            array_column($this->getJson('/api/v1/business-settings/crm')->assertOk()->json('data.lists.consent_purpose'), 'name'),
        );

        $seasonal = $this->postJson('/api/v1/business-settings/crm/options', ['kind' => 'consent_purpose', 'name' => 'Seasonal Sales'])->assertCreated()->json('data.id');
        $lead     = $this->lead('Karim Ahmed');
        $this->answer("crm/leads/{$lead}", ['purpose_id' => $seasonal])->assertCreated();

        // Answered about, it stays; switched off, it is offered no more, and its answers keep its name.
        $this->deleteJson("/api/v1/business-settings/crm/options/{$seasonal}")->assertStatus(409)->assertJsonPath('message', 'Consent was recorded for this purpose, so it stays. Switch it off instead.');
        $this->putJson("/api/v1/business-settings/crm/options/{$seasonal}", ['name' => 'Seasonal Sales', 'is_active' => false])->assertOk();
        $this->answer("crm/leads/{$lead}", ['purpose_id' => $seasonal])->assertUnprocessable()->assertJsonValidationErrors('purpose_id');
        $this->getJson("/api/v1/crm/leads/{$lead}/consents")
            ->assertJsonPath('data.consents.0.purpose.name', 'Seasonal Sales')
            ->assertJsonMissingPath('data.purposes.5');

        // A purpose nothing was recorded for is deleted.
        $events = $this->postJson('/api/v1/business-settings/crm/options', ['kind' => 'consent_purpose', 'name' => 'Trade Fairs'])->assertCreated()->json('data.id');
        $this->deleteJson("/api/v1/business-settings/crm/options/{$events}")->assertOk();
    }
}
