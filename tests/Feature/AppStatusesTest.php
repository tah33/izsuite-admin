<?php

namespace Tests\Feature;

use App\Models\Admin\AdminAuditLog;
use App\Models\Admin\Role;
use App\Models\Billing\Package;
use App\Models\Frontend\AppCategory;
use App\Models\Frontend\Application;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceApp;
use App\Services\User\Apps\SyncAppsWithPackage;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Doc 15 Phase E2 (section 15.10): an app's statuses - Draft, Coming Soon,
 * Published, Temporarily Unavailable, Deprecated, Retired - the countries
 * it is offered in, the apps it cannot be on beside, and its release; as
 * the Apps Center shows them, activation refuses them, and the admin sets
 * them. Retiring turns it off everywhere and keeps the records.
 */
class AppStatusesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private Workspace $workspace;

    /** @var array<string, Application> by app_key */
    private array $apps = [];

    private AppCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->category = AppCategory::create(['name' => 'Business', 'sort_order' => 1]);

        // key, name, status, and what else it has
        foreach ([
            ['crm', 'CRM', Application::PUBLISHED, ['release_version' => '2.1.0']],
            ['helpdesk', 'Helpdesk', Application::PUBLISHED, ['conflicts' => ['crm']]],
            ['pos', 'Point of Sale', Application::PUBLISHED, ['countries' => ['IN']]],
            ['inventory', 'Inventory', Application::UNAVAILABLE, []],
            ['reports', 'Reports', Application::DEPRECATED, []],
            ['drafty', 'Drafty', Application::DRAFT, []],
        ] as $order => [$key, $name, $status, $more]) {
            $this->apps[$key] = Application::create([
                'app_key' => $key, 'name' => $name, 'family_key' => $key, 'edition' => 'full', 'category_id' => $this->category->id,
                'sort_order' => $order, 'is_active' => true, 'status' => $status, ...$more,
            ]);
            $this->apps[$key]->packages()->attach(Package::where('key', '!=', 'starter')->pluck('id'));
        }

        $this->owner     = User::factory()->create();
        $this->workspace = $this->owner->workspaces()->create(['name' => 'Karim Stores']);
        $this->workspace->businessSetting()->create([
            'user_id' => $this->owner->id, 'business_name' => 'Karim Stores', 'language' => 'en', 'country' => 'BD', 'state' => 'Dhaka',
            'city'    => 'Dhaka', 'zip_code' => '1207', 'address_line_1' => 'Near City Center', 'timezone' => 'UTC', 'stock_accounting_method' => 'fifo',
        ]);
        app(SyncAppsWithPackage::class)->changePackage($this->workspace, Package::where('key', 'standard')->firstOrFail());
        $this->admin = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]);
    }

    /** @return array<string, array> the Apps Center's editions, by app key */
    private function editions(): array
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->owner);

        return collect($this->getJson('/api/v1/apps-center')->assertOk()->json('data.categories'))
            ->flatMap(fn (array $category) => $category['families'])
            ->flatMap(fn (array $family) => $family['editions'])
            ->keyBy('app_key')
            ->all();
    }

    private function activate(string $key)
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->owner);

        return $this->postJson("/api/v1/apps/{$key}/activate");
    }

    public function test_the_apps_center_shows_each_status_and_activation_refuses_what_cannot_be_turned_on(): void
    {
        $editions = $this->editions();
        $this->assertSame(['available', 'available', 'not_offered', 'unavailable', 'deprecated'], array_column(array_values($editions), 'state'));
        $this->assertArrayNotHasKey('drafty', $editions);
        $this->assertSame('2.1.0', $editions['crm']['release_version']);

        $this->activate('crm')->assertOk();
        $this->assertSame(['conflicts', ['CRM']], [$this->editions()['helpdesk']['state'], $this->editions()['helpdesk']['conflicts_with']]);

        $this->activate('helpdesk')->assertUnprocessable()->assertJsonPath('errors.app.0', 'Helpdesk cannot be on beside CRM. Turn it off first.');
        $this->activate('pos')->assertUnprocessable()->assertJsonPath('errors.app.0', "Point of Sale is not offered in your business's country.");
        $this->activate('inventory')->assertUnprocessable()->assertJsonPath('errors.app.0', 'Inventory is temporarily unavailable. Try again later.');
        $this->activate('reports')->assertUnprocessable()->assertJsonPath('errors.app.0', 'Reports is deprecated: it can no longer be turned on.');
        $this->activate('drafty')->assertUnprocessable()->assertJsonPath('errors.app.0', 'Drafty is not available yet.');

        // An app on stays on when deprecated, and says so; one offered in the business's country turns on.
        $this->apps['crm']->update(['status' => Application::DEPRECATED]);
        $this->assertSame(['active', Application::DEPRECATED], [$this->editions()['crm']['state'], $this->editions()['crm']['status']]);
        $this->apps['pos']->update(['countries' => ['IN', 'BD']]);
        $this->activate('pos')->assertOk();
    }

    public function test_the_admin_sets_the_catalogue_fields_and_retires_an_app_keeping_its_records(): void
    {
        $this->activate('crm')->assertOk();
        $form = ['name' => 'CRM', 'category_id' => $this->category->id, 'status' => Application::PUBLISHED, 'sort_order' => 0, 'is_active' => 1];
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->admin);

        $this->putJson(route('admin.apps.update', $this->apps['crm']->id), [...$form, 'release_version' => '3.0', 'countries' => ['BD', 'IN'], 'conflicts' => ['pos', 'crm']])->assertOk();
        $crm = $this->apps['crm']->fresh();
        $this->assertSame(['3.0', ['BD', 'IN'], ['pos']], [$crm->release_version, $crm->countries, $crm->conflicts]);

        // Retired only by Retire, a critical action.
        $this->putJson(route('admin.apps.update', $crm->id), [...$form, 'status' => Application::RETIRED])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->postJson(route('admin.apps.retire', $crm->id), ['reason' => 'Replaced by the full CRM'])->assertUnprocessable()->assertJsonValidationErrors('confirm_password');
        $nav = $this->workspace->fresh()->nav_version;

        $this->postJson(route('admin.apps.retire', $crm->id), ['confirm_password' => 'password', 'reason' => 'Replaced by the full CRM'])
            ->assertOk()->assertJsonPath('message', 'CRM is retired: it is off for the 1 business that had it on, their records kept.');

        $row = WorkspaceApp::where('workspace_id', $this->workspace->id)->where('app_id', $crm->id)->sole();
        $this->assertSame([WorkspaceApp::STATUS_INACTIVE, Application::RETIRED, false], [$row->status, $crm->fresh()->status, $crm->fresh()->is_active]);
        $this->assertSame($nav + 1, $this->workspace->fresh()->nav_version);
        $this->assertArrayNotHasKey('crm', $this->editions());
        $this->assertSame('Retired app "CRM": off for 1 business', AdminAuditLog::where('description', 'like', 'Retired app%')->value('description'));

        // A retired app may keep its status in the form; the edit page offers no second Retire.
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->admin);
        $this->putJson(route('admin.apps.update', $crm->id), [...$form, 'status' => Application::RETIRED])->assertOk();
        $this->get(route('admin.apps.edit', $crm->id))->assertOk()->assertDontSee('Retire This App');
        $this->get(route('admin.apps.edit', $this->apps['pos']->id))->assertOk()->assertSee('Retire This App');
    }
}
