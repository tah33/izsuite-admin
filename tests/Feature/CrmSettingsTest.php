<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\AppCategory;
use App\Models\Frontend\Application;
use App\Models\Frontend\MenuItem;
use App\Models\Shared\ActivityLog;
use App\Models\User\CrmOption;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 17 Phase A1: CRM's sidebar in Doc 17's order, and Settings > CRM - the
 * operating mode, which hides Leads for a business using opportunities only,
 * and the lead sources, lost reasons and activity types, which start with
 * Doc 17's examples.
 */
class CrmSettingsTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $category = AppCategory::create(['name' => 'Sales & Commerce', 'sort_order' => 1]);
        $crm      = Application::create(['app_key' => 'crm', 'name' => 'CRM', 'family_key' => 'crm', 'edition' => 'full', 'category_id' => $category->id, 'sort_order' => 20, 'is_active' => true, 'status' => 'available']);
        $crm->packages()->attach(Package::pluck('id'));

        // As AppCatalogSeeder makes them, out of Doc 17's order to show it is kept.
        foreach ([['Customers', '/contacts?role=customer', null, 'contacts.view'], ['Opportunities', '/crm/opportunities', null, null], ['Leads', '/crm/leads', 'crm_uses_leads', null]] as $index => [$label, $route, $showIf, $permission]) {
            MenuItem::create(['section' => 'apps', 'family_key' => 'crm', 'min_edition' => 'lite', 'label' => $label, 'route' => $route, 'show_if' => $showIf, 'permission_key' => $permission, 'sort_order' => 10 + $index]);
        }

        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
    }

    private function crmOn(): void
    {
        $this->postJson('/api/v1/apps/crm/activate')->assertOk();
    }

    /** @return list<string> the CRM entry's pages, as the sidebar lists them */
    private function crmMenu(): array
    {
        $crm = collect($this->getJson('/api/v1/navigation')->assertOk()->json('data.menu.apps'))->firstWhere('family', 'crm');

        return array_column($crm['children'] ?? [], 'label');
    }

    private function option(string $kind, string $name): CrmOption
    {
        return CrmOption::query()->where('workspace_id', $this->business->id)->where('kind', $kind)->where('name', $name)->firstOrFail();
    }

    public function test_settings_come_with_crm_and_start_with_doc_17s_lists(): void
    {
        $this->getJson('/api/v1/business-settings/crm')->assertForbidden();

        $this->crmOn();

        $settings = $this->getJson('/api/v1/business-settings/crm')->assertOk()->json('data');

        $this->assertSame('leads', $settings['mode']);
        // Phase I3: and the consent purposes.
        $this->assertSame([12, 9, 8, 5], [count($settings['lists']['lead_source']), count($settings['lists']['lost_reason']), count($settings['lists']['activity_type']), count($settings['lists']['consent_purpose'])]);
        $this->assertSame(['name' => 'Website', 'is_active' => true, 'starter' => true], array_diff_key($settings['lists']['lead_source'][0], ['id' => true]));
        $this->assertSame(['Price', 'No Budget', 'Chose Competitor'], array_slice(array_column($settings['lists']['lost_reason'], 'name'), 0, 3));
        $this->assertSame('Site Visit', $settings['lists']['activity_type'][6]['name']);

        // Read again, nothing is added twice.
        $this->getJson('/api/v1/business-settings/crm')->assertOk();
        $this->assertSame(34, CrmOption::query()->where('workspace_id', $this->business->id)->count());
    }

    public function test_the_mode_hides_leads_and_is_logged(): void
    {
        $this->crmOn();

        $this->assertSame(['Leads', 'Opportunities', 'Customers'], $this->crmMenu());

        $this->putJson('/api/v1/business-settings/crm', ['mode' => 'opportunities'])->assertOk()
            ->assertJsonPath('message', 'CRM settings saved.')
            ->assertJsonPath('data.mode', 'opportunities');

        // Nothing deleted: back to leads, they are there again.
        $this->assertSame(['Opportunities', 'Customers'], $this->crmMenu());
        $this->assertSame(['mode'], ActivityLog::query()->where('description', 'Updated the CRM settings')->latest('id')->value('properties')['fields']);

        $this->putJson('/api/v1/business-settings/crm', ['mode' => 'leads'])->assertOk();
        $this->assertSame(['Leads', 'Opportunities', 'Customers'], $this->crmMenu());

        $this->putJson('/api/v1/business-settings/crm', ['mode' => 'everything'])->assertUnprocessable()->assertJsonValidationErrors('mode');
    }

    public function test_a_list_is_added_to_renamed_switched_off_and_its_own_deleted(): void
    {
        $this->crmOn();
        $this->getJson('/api/v1/business-settings/crm')->assertOk();

        $added = $this->postJson('/api/v1/business-settings/crm/options', ['kind' => 'lead_source', 'name' => 'Trade Show'])->assertCreated()
            ->assertJsonPath('data.starter', false)
            ->assertJsonPath('data.is_active', true)
            ->json('data.id');
        $this->assertSame('Trade Show', last($this->getJson('/api/v1/business-settings/crm')->json('data.lists.lead_source'))['name']);

        // A name once in each list.
        $this->postJson('/api/v1/business-settings/crm/options', ['kind' => 'lead_source', 'name' => 'Trade Show'])
            ->assertUnprocessable()->assertJsonValidationErrors(['name' => 'This list has one of that name already.']);
        $this->postJson('/api/v1/business-settings/crm/options', ['kind' => 'lost_reason', 'name' => 'Trade Show'])->assertCreated();
        $this->postJson('/api/v1/business-settings/crm/options', ['kind' => 'stage', 'name' => 'Won'])->assertUnprocessable()->assertJsonValidationErrors('kind');

        // A starting one is renamed and switched off, never deleted.
        $website = $this->option('lead_source', 'Website');
        $this->putJson("/api/v1/business-settings/crm/options/{$website->id}", ['name' => 'Our Website', 'is_active' => false])->assertOk()
            ->assertJsonPath('data.name', 'Our Website')
            ->assertJsonPath('data.is_active', false);
        $this->assertSame('website', $website->fresh()->key);
        $this->putJson("/api/v1/business-settings/crm/options/{$website->id}", ['name' => 'Trade Show'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->putJson("/api/v1/business-settings/crm/options/{$website->id}", ['kind' => 'lost_reason', 'name' => 'Web'])->assertUnprocessable()->assertJsonValidationErrors('kind');
        $this->deleteJson("/api/v1/business-settings/crm/options/{$website->id}")->assertStatus(409)
            ->assertJsonPath('message', 'izSuite uses this one, so it cannot be deleted. Switch it off instead.');

        $this->deleteJson("/api/v1/business-settings/crm/options/{$added}")->assertOk();
        $this->assertNotContains('Trade Show', array_column($this->getJson('/api/v1/business-settings/crm')->json('data.lists.lead_source'), 'name'));
        $this->assertTrue(ActivityLog::query()->where('description', 'Deleted lead source "Trade Show"')->exists());

        // Another business's entry is not found.
        $other = $this->businessOf(User::factory()->create())->crmOptions()->create(['kind' => 'lead_source', 'name' => 'Theirs']);
        $this->putJson("/api/v1/business-settings/crm/options/{$other->id}", ['name' => 'Mine'])->assertNotFound();
    }

    public function test_a_role_reads_them_with_settings_view_and_changes_them_with_the_permission(): void
    {
        $this->crmOn();

        $member = User::factory()->create();
        $role   = $this->business->roles()->create(['name' => 'Office', 'permissions' => ['settings.view']]);
        $this->business->members()->create(['user_id' => $member->id, 'status' => 'active', 'role_id' => $role->id]);
        Sanctum::actingAs($member);

        $this->getJson('/api/v1/business-settings/crm')->assertOk();
        $this->putJson('/api/v1/business-settings/crm', ['mode' => 'opportunities'])->assertForbidden();
        $this->postJson('/api/v1/business-settings/crm/options', ['kind' => 'lead_source', 'name' => 'Trade Show'])->assertForbidden();

        $role->forceFill(['permissions' => [...$role->permissions, 'crm.settings.manage']])->save();
        $this->putJson('/api/v1/business-settings/crm', ['mode' => 'opportunities'])->assertOk();
    }
}
