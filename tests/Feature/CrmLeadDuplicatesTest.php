<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\CrmLead;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 17 Phase C2: possible duplicate leads - other leads with the same
 * e-mail, phone or business name, and contacts matching as conversion does -
 * flagged on a lead's page and in the list until each is ignored with a
 * reason or linked; a linked contact comes first on conversion.
 */
class CrmLeadDuplicatesTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private int $source;

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
        $this->source = $this->getJson('/api/v1/crm/leads/options')->json('data.sources.0.id');
    }

    private function lead(string $name, array $fields = []): int
    {
        return $this->postJson('/api/v1/crm/leads', ['name' => $name, 'email' => strtolower(str_replace(' ', '.', $name)).'@example.com', 'source_id' => $this->source, ...$fields])->assertCreated()->json('data.id');
    }

    /** $id's possible duplicates as [type, name, matched on, decision]. */
    private function duplicates(int $id): array
    {
        return array_map(fn (array $row) => [$row['type'], $row['name'], $row['matched_on'], $row['decision']], $this->getJson("/api/v1/crm/leads/{$id}/duplicates")->assertOk()->json('data'));
    }

    public function test_leads_and_contacts_sharing_an_email_phone_or_business_name_are_possible_duplicates(): void
    {
        $rahim = $this->lead('Rahim Uddin', ['business_name' => 'Uddin Traders', 'email' => 'rahim@uddin.example', 'phone' => '+880 1711-000000']);
        $this->lead('Karim Ali', ['business_name' => ' uddin TRADERS ']);
        $this->lead('Selina Haque', ['email' => 'RAHIM@uddin.example']);
        $this->lead('Nadia Rahman', ['phone' => '01711000000']);
        $this->lead('Farhana Akter');
        $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'supplier', 'name' => 'Uddin Traders']);
        $this->business->contacts()->create(['contact_type' => 'individual', 'role' => 'customer', 'name' => 'Jamal Hossain', 'email' => 'rahim@uddin.example']);

        $this->assertSame('711000000', CrmLead::find($rahim)->match_phone);
        $this->assertEqualsCanonicalizing([
            ['lead', 'Karim Ali', ['business_name'], null],
            ['lead', 'Selina Haque', ['email'], null],
            ['lead', 'Nadia Rahman', ['phone'], null],
            ['contact', 'Uddin Traders', ['name'], null],
            ['contact', 'Jamal Hossain', ['email'], null],
        ], $this->duplicates($rahim));

        // The list marks those with one not decided about.
        $flags = collect($this->getJson('/api/v1/crm/leads')->assertOk()->json('data'))->pluck('possible_duplicate', 'name')->all();
        $this->assertSame(['Farhana Akter' => false, 'Nadia Rahman' => true, 'Selina Haque' => true, 'Karim Ali' => true, 'Rahim Uddin' => true], $flags);
    }

    public function test_a_possible_duplicate_is_ignored_with_a_reason_or_linked_and_can_be_taken_back(): void
    {
        $rahim  = $this->lead('Rahim Uddin', ['business_name' => 'Uddin Traders', 'customer_need' => 'Shelving']);
        $karim  = $this->lead('Karim Ali', ['business_name' => 'Uddin Traders']);
        $contact = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin Traders']);
        $decide = fn (string $decision, array $body) => $this->postJson("/api/v1/crm/leads/{$rahim}/duplicates/{$decision}", $body);

        $decide('ignore', ['type' => 'lead', 'id' => $karim])->assertUnprocessable()->assertJsonValidationErrors(['reason' => 'Say why it is not a duplicate.']);
        $decide('ignore', ['type' => 'lead', 'id' => $rahim, 'reason' => 'Itself'])->assertUnprocessable()->assertJsonValidationErrors(['id' => 'Choose one of the leads you see.']);
        $theirs = $this->businessOf(User::factory()->create())->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin Traders']);
        $decide('link', ['type' => 'contact', 'id' => $theirs->id])->assertUnprocessable()->assertJsonValidationErrors(['id' => 'Choose one of your contacts.']);

        $decide('ignore', ['type' => 'lead', 'id' => $karim, 'reason' => 'Another branch of the family business'])->assertOk()
            ->assertJsonPath('message', 'Marked as not a duplicate.')
            ->assertJsonPath('data.1.reason', 'Another branch of the family business');
        $decide('link', ['type' => 'contact', 'id' => $contact->id])->assertOk()->assertJsonPath('message', 'Linked.');

        // Decided on both leads; nothing left to flag.
        $this->assertSame([['contact', 'Uddin Traders', ['name'], 'linked'], ['lead', 'Karim Ali', ['business_name'], 'ignored']], $this->duplicates($rahim));
        $this->assertSame([['contact', 'Uddin Traders', ['name'], null], ['lead', 'Rahim Uddin', ['business_name'], 'ignored']], $this->duplicates($karim));
        $this->assertFalse(collect($this->getJson('/api/v1/crm/leads')->json('data'))->firstWhere('id', $rahim)['possible_duplicate']);

        // A linked contact comes first on conversion.
        $this->postJson("/api/v1/crm/leads/{$rahim}/qualify")->assertOk();
        $this->assertSame([[$contact->id, ['linked']]], array_map(fn (array $match) => [$match['id'], $match['matched_on']], $this->getJson("/api/v1/crm/leads/{$rahim}/conversion")->json('data.matches')));

        // Taken back, on both leads.
        $decide('clear', ['type' => 'lead', 'id' => $karim])->assertOk()->assertJsonPath('message', 'Decision taken back.');
        $this->assertSame([['lead', 'Rahim Uddin', ['business_name'], null], ['contact', 'Uddin Traders', ['name'], null]], $this->duplicates($karim));

        foreach (['Marked lead "Karim Ali" as not a duplicate of lead "Rahim Uddin"', 'Linked lead "Rahim Uddin" to contact "Uddin Traders"', 'Took back the decision about lead "Karim Ali" on lead "Rahim Uddin"'] as $logged) {
            $this->assertTrue(ActivityLog::query()->where('description', $logged)->exists(), $logged);
        }
    }

    public function test_only_the_leads_and_contacts_a_member_sees_count(): void
    {
        $mine   = $this->lead('Rahim Uddin', ['business_name' => 'Uddin Traders']);
        $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin Traders']);

        $member = function (array $permissions): User {
            $user = User::factory()->create();
            $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
            $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id]);

            return $user;
        };

        // Their own lead matches the owner's lead and a contact, but they see neither.
        Sanctum::actingAs($member(['crm.leads.view', 'crm.leads.manage']));
        $theirs = $this->lead('Karim Ali', ['business_name' => 'Uddin Traders']);
        $this->assertSame([], $this->duplicates($theirs));
        $this->assertFalse($this->getJson('/api/v1/crm/leads')->json('data.0.possible_duplicate'));

        // Seeing every lead and the contacts, they find both - but deciding needs Create and Edit.
        Sanctum::actingAs($member(['crm.leads.view', 'crm.records.all', 'contacts.view']));
        $this->assertEqualsCanonicalizing([['lead', 'Rahim Uddin', ['business_name'], null], ['contact', 'Uddin Traders', ['name'], null]], $this->duplicates($theirs));
        $this->postJson("/api/v1/crm/leads/{$theirs}/duplicates/link", ['type' => 'lead', 'id' => $mine])->assertForbidden();
    }
}
