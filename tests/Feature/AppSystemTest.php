<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\AppCategory;
use App\Models\Frontend\AppDependency;
use App\Models\Frontend\Application;
use App\Models\Frontend\MenuItem;
use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceApp;
use App\Services\User\Apps\SyncAppsWithPackage;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The app system ("izSuite Apps & Menus"): what a business starts with, the
 * Apps Center's states, turning apps on and off, plan changes, the sidebar,
 * and the guard on an app's API. Each row of the plan's "What changes in each
 * case" table is a test here.
 */
class AppSystemTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    /** @var array<string, Application> by app_key */
    private array $apps = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded first, so super-admin holds id 1 and the owner is not taken for an admin.
        $this->seed(RoleSeeder::class);

        $category = AppCategory::create(['name' => 'Sales & Commerce', 'sort_order' => 1]);

        // key, name, family, edition, order, lowest plan, status
        foreach ([
            ['sales_lite', 'Sales Lite', 'sales', 'lite', 10, 'starter'],
            ['sales', 'Sales', 'sales', 'full', 10, 'standard'],
            ['purchases_lite', 'Purchases Lite', 'purchases', 'lite', 20, 'starter'],
            ['purchases', 'Purchases', 'purchases', 'full', 20, 'standard'],
            ['inventory', 'Inventory', 'inventory', 'full', 30, 'standard'],
            ['crm', 'CRM', 'crm', 'full', 40, 'premium'],
            ['pos', 'Point of Sale', 'pos', 'full', 50, 'standard', 'coming_soon'],
        ] as $row) {
            [$key, $name, $family, $edition, $order, $lowest] = $row;

            $app = Application::create([
                'app_key'     => $key,
                'name'        => $name,
                'family_key'  => $family,
                'edition'     => $edition,
                'category_id' => $category->id,
                'sort_order'  => $order,
                'is_active'   => true,
                'status'      => $row[6] ?? 'available',
            ]);

            $app->packages()->attach(Package::where('rank', '>=', Package::where('key', $lowest)->value('rank'))->pluck('id'));

            $this->apps[$key] = $app;
        }

        AppDependency::create(['app_id' => $this->apps['inventory']->id, 'requires_family' => 'purchases', 'min_edition' => 'lite']);

        MenuItem::create(['section' => 'core', 'label' => 'Dashboard', 'route' => '/dashboard', 'sort_order' => 1]);
        MenuItem::create(['section' => 'core', 'label' => 'Payment Accounts', 'route' => '/payment-accounts', 'sort_order' => 2, 'show_if' => 'has_payment_account']);
        MenuItem::create(['section' => 'core', 'label' => 'Hidden', 'route' => '/hidden', 'sort_order' => 3, 'is_active' => false]);
        MenuItem::create(['section' => 'apps', 'family_key' => 'sales', 'min_edition' => 'lite', 'label' => 'Invoices', 'route' => '/sales/invoices', 'sort_order' => 1]);
        MenuItem::create(['section' => 'apps', 'family_key' => 'sales', 'min_edition' => 'full', 'label' => 'Sales Orders', 'route' => '/sales/orders', 'sort_order' => 2]);
        MenuItem::create(['section' => 'apps', 'family_key' => 'crm', 'min_edition' => 'lite', 'label' => 'Leads', 'route' => '/crm/leads', 'sort_order' => 1]);
        MenuItem::create(['section' => 'bottom', 'label' => 'Apps Center', 'route' => '/apps-center', 'sort_order' => 1]);

        $this->owner     = User::factory()->create();
        $this->workspace = $this->owner->workspaces()->create(['name' => 'Main Business']);

        Sanctum::actingAs($this->owner);
    }

    /* ------------------------------------------------------------ helpers */

    private function onPlan(string $key): void
    {
        app(SyncAppsWithPackage::class)->changePackage($this->workspace, Package::where('key', $key)->firstOrFail());
    }

    private function row(string $family): ?WorkspaceApp
    {
        return $this->workspace->workspaceApps()->with('app')->where('family_key', $family)->first();
    }

    private function activate(string $appKey): TestResponse
    {
        return $this->postJson("/api/v1/apps/{$appKey}/activate");
    }

    private function deactivate(string $appKey): TestResponse
    {
        return $this->postJson("/api/v1/apps/{$appKey}/deactivate");
    }

    private function navigation(): array
    {
        return $this->getJson('/api/v1/navigation')->assertOk()->json('data');
    }

    /** One edition's entry in the Apps Center. */
    private function state(string $appKey): array
    {
        foreach ($this->getJson('/api/v1/apps-center')->assertOk()->json('data.categories') as $category) {
            foreach ($category['families'] as $family) {
                foreach ($family['editions'] as $edition) {
                    if ($edition['app_key'] === $appKey) {
                        return $edition;
                    }
                }
            }
        }

        $this->fail("{$appKey} is not in the Apps Center.");
    }

    private function labels(array $items): array
    {
        return array_column($items, 'label');
    }

    /** Routes standing in for an app's API: Sales (either edition), and Full only. */
    private function salesRoutes(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'workspace', 'app:sales'])->group(function () {
            Route::get('/api/v1/test-sales/invoices', fn () => 'invoices');
            Route::post('/api/v1/test-sales/invoices', fn () => 'saved');
        });

        Route::middleware(['api', 'auth:sanctum', 'workspace', 'app:sales,full'])
            ->get('/api/v1/test-sales/orders', fn () => 'orders');

        Route::middleware(['api', 'auth:sanctum', 'workspace', 'app:crm'])
            ->get('/api/v1/test-crm/leads', fn () => 'leads');
    }

    /* ------------------------------------------------ a new business */

    public function test_a_new_business_starts_on_billing_starter_with_its_lite_apps_on(): void
    {
        $this->assertSame('starter', $this->workspace->package->key);
        $this->assertSame(2, $this->workspace->workspaceApps()->count());
        $this->assertSame('sales_lite', $this->row('sales')->app->app_key);
        $this->assertSame('purchases_lite', $this->row('purchases')->app->app_key);
        $this->assertTrue($this->row('sales')->isActive());
        $this->assertTrue($this->row('purchases')->isActive());
    }

    public function test_the_sidebar_has_core_active_apps_and_bottom_groups(): void
    {
        $nav = $this->navigation();

        // Payment Accounts waits for its Show if rule, and Hidden is inactive.
        $this->assertSame(['Dashboard'], $this->labels($nav['menu']['core']));
        $this->assertSame(['Sales Lite', 'Purchases Lite'], $this->labels($nav['menu']['apps']));

        // Sales Orders is Full only.
        $this->assertSame(['Invoices'], $this->labels($nav['menu']['apps'][0]['children']));
        $this->assertSame(['Apps Center'], $this->labels($nav['menu']['bottom']));

        // No currencies are set up here, so the business is on the fallback, USD.
        $this->assertSame([
            'id'            => $this->workspace->id,
            'name'          => 'Main Business',
            'package'       => ['key' => 'starter', 'name' => 'Billing Starter'],
            'base_currency' => 'USD',
            'currencies'    => ['USD'],
            'tax_mode'      => 'later',
        ], $nav['workspace']);
        $this->assertSame(
            ['app_key' => 'sales_lite', 'name' => 'Sales Lite', 'edition' => 'lite', 'status' => 'active', 'has_history' => true],
            $nav['apps']['sales'],
        );
    }

    public function test_a_user_without_a_business_gets_the_core_and_bottom_items_only(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $nav = $this->navigation();

        $this->assertNull($nav['workspace']);
        $this->assertSame([], $nav['menu']['apps']);
        $this->assertSame(['Dashboard'], $this->labels($nav['menu']['core']));
        $this->getJson('/api/v1/apps-center')->assertNotFound()->assertJsonPath('message', 'Create a workspace first.');
    }

    /* ------------------------------------------------ the Apps Center */

    public function test_the_apps_center_gives_each_edition_its_state(): void
    {
        $this->assertSame('active', $this->state('sales_lite')['state']);
        $this->assertSame('upgrade_required', $this->state('sales')['state']);
        $this->assertSame('upgrade_required', $this->state('crm')['state']);
        $this->assertSame('coming_soon', $this->state('pos')['state']);

        $this->onPlan('standard');

        $sales = $this->state('sales');
        $this->assertSame('upgrade', $sales['state']);
        $this->assertSame('Sales Lite', $sales['replaces']);
        $this->assertSame('available', $this->state('inventory')['state']);
        $this->assertSame(['Purchases Lite'], $this->state('inventory')['requires']);
    }

    public function test_the_apps_center_says_what_turning_an_app_off_would_do(): void
    {
        $this->onPlan('standard');
        $this->activate('sales')->assertOk();
        $this->activate('inventory')->assertOk();

        $this->assertSame('Sales Lite', $this->state('sales')['falls_back_to']);
        $this->assertSame('included', $this->state('sales_lite')['state']);
        $this->assertSame(['Inventory'], $this->state('purchases_lite')['required_by']);
    }

    /* ---------------------------------- "What changes in each case" */

    public function test_upgrading_the_plan_turns_nothing_on(): void
    {
        $before = $this->workspace->workspaceApps()->pluck('app_id', 'family_key')->all();

        $this->onPlan('standard');

        $this->assertSame($before, $this->workspace->workspaceApps()->pluck('app_id', 'family_key')->all());
        $this->assertSame(['Sales Lite', 'Purchases Lite'], $this->labels($this->navigation()['menu']['apps']));
    }

    public function test_turning_on_sales_replaces_sales_lite_in_place(): void
    {
        $this->onPlan('standard');

        $response = $this->activate('sales')->assertOk()->assertJsonPath('message', 'Sales replaced Sales Lite.');

        $this->assertSame(1, $this->workspace->workspaceApps()->where('family_key', 'sales')->count());
        $this->assertSame('sales', $this->row('sales')->app->app_key);
        $this->assertDatabaseHas('workspace_app_logs', [
            'workspace_id' => $this->workspace->id,
            'action'       => 'replaced',
            'app_id'       => $this->apps['sales']->id,
            'from_app_id'  => $this->apps['sales_lite']->id,
        ]);

        // The answer carries the new sidebar: Sales in Sales Lite's place, with its Full-only item.
        $this->assertSame('Sales', $response->json('data.menu.apps.0.label'));
        $this->assertSame(['Invoices', 'Sales Orders'], $this->labels($response->json('data.menu.apps.0.children')));
    }

    public function test_turning_on_an_app_adds_its_family(): void
    {
        $this->onPlan('premium');

        $this->activate('crm')->assertOk()->assertJsonPath('message', 'CRM is on.');

        $this->assertTrue($this->row('crm')->isActive());
        $this->assertContains('CRM', $this->labels($this->navigation()['menu']['apps']));
    }

    public function test_turning_sales_off_brings_sales_lite_back(): void
    {
        $this->onPlan('standard');
        $this->activate('sales')->assertOk();

        $this->deactivate('sales')->assertOk()->assertJsonPath('message', 'Sales is off. Sales Lite is back.');

        $this->assertTrue($this->row('sales')->isActive());
        $this->assertSame('sales_lite', $this->row('sales')->app->app_key);
        $this->assertDatabaseHas('workspace_app_logs', [
            'action'      => 'fallback',
            'app_id'      => $this->apps['sales_lite']->id,
            'from_app_id' => $this->apps['sales']->id,
        ]);
    }

    public function test_downgrading_moves_apps_down_and_keeps_their_records(): void
    {
        $this->onPlan('premium');
        $this->activate('sales')->assertOk();
        $this->activate('crm')->assertOk();

        $this->onPlan('starter');

        $this->assertSame('sales_lite', $this->row('sales')->app->app_key);
        $this->assertFalse($this->row('crm')->isActive());
        $this->assertNotNull($this->row('crm')->deactivated_at);

        $nav = $this->navigation();
        $this->assertNotContains('CRM', $this->labels($nav['menu']['apps']));
        $this->assertSame('inactive', $nav['apps']['crm']['status']);
    }

    /* ------------------------------------------- turning apps off and on */

    public function test_turning_a_lite_app_off_keeps_its_row_and_turning_it_on_finds_it(): void
    {
        $this->deactivate('purchases_lite')->assertOk()->assertJsonPath('message', 'Purchases Lite is off. Its records are kept.');

        $this->assertSame('inactive', $this->row('purchases')->status);
        $this->assertSame('available', $this->state('purchases_lite')['state']);

        $this->activate('purchases_lite')->assertOk();

        $this->assertSame(1, $this->workspace->workspaceApps()->where('family_key', 'purchases')->count());
        $this->assertTrue($this->row('purchases')->isActive());
        $this->assertNull($this->row('purchases')->deactivated_at);
    }

    public function test_an_app_waits_for_what_it_needs(): void
    {
        $this->onPlan('standard');
        $this->deactivate('purchases_lite')->assertOk();

        $inventory = $this->state('inventory');
        $this->assertSame('needs_apps', $inventory['state']);
        $this->assertSame(['Purchases Lite'], $inventory['missing']);

        $this->activate('inventory')->assertUnprocessable()->assertJsonPath('errors.app.0', 'Turn on first: Purchases Lite.');
        $this->assertNull($this->row('inventory'));
    }

    public function test_what_another_app_needs_cannot_be_turned_off_but_can_fall_back(): void
    {
        $this->onPlan('standard');
        $this->activate('inventory')->assertOk();

        $this->deactivate('purchases_lite')->assertUnprocessable()->assertJsonPath('errors.app.0', 'Turn these off first: Inventory.');
        $this->assertTrue($this->row('purchases')->isActive());

        // Inventory needs Purchases at Lite, so Full may fall back to it.
        $this->activate('purchases')->assertOk();
        $this->deactivate('purchases')->assertOk();
        $this->assertSame('purchases_lite', $this->row('purchases')->app->app_key);
    }

    public function test_the_plan_decides_what_can_be_turned_on(): void
    {
        $this->activate('sales')->assertForbidden()->assertJsonPath('message', 'Your plan does not include this app.');

        $this->assertSame('sales_lite', $this->row('sales')->app->app_key);
    }

    public function test_an_app_that_is_coming_soon_cannot_be_turned_on(): void
    {
        $this->onPlan('standard');

        $this->activate('pos')->assertUnprocessable()->assertJsonPath('errors.app.0', 'Point of Sale is not available yet.');
    }

    public function test_lite_cannot_go_on_over_full_and_turning_on_twice_changes_nothing(): void
    {
        $this->onPlan('standard');
        $this->activate('sales')->assertOk();

        $this->activate('sales_lite')->assertUnprocessable()->assertJsonPath('errors.app.0', 'Sales is on, and already includes Sales Lite.');

        $logs = $this->workspace->appLogs()->count();
        $this->activate('sales')->assertOk()->assertJsonPath('message', 'Sales is on.');
        $this->assertSame($logs, $this->workspace->appLogs()->count());
    }

    public function test_turning_off_what_is_not_on_or_does_not_exist_is_refused(): void
    {
        $this->deactivate('crm')->assertUnprocessable()->assertJsonPath('errors.app.0', 'CRM is not turned on.');
        $this->activate('no-such-app')->assertNotFound()->assertJsonPath('message', 'App not found.');
    }

    public function test_each_change_is_logged_for_the_account(): void
    {
        $this->onPlan('standard');
        $this->activate('sales')->assertOk();
        $this->deactivate('sales')->assertOk();

        $this->assertSame(
            ['Upgraded Sales Lite to Sales', 'Moved Sales back to Sales Lite'],
            ActivityLog::where('user_id', $this->owner->id)->where('model_type', Workspace::class)->orderBy('id')->pluck('description')->all(),
        );
    }

    /* ------------------------------------------------- which business */

    public function test_the_workspace_header_picks_the_business(): void
    {
        $second = $this->owner->workspaces()->create(['name' => 'Second Business']);

        // Without the header, the oldest.
        $this->assertSame($this->workspace->id, $this->navigation()['workspace']['id']);

        $this->getJson('/api/v1/navigation', ['X-Workspace-Id' => (string) $second->id])
            ->assertOk()
            ->assertJsonPath('data.workspace.id', $second->id);
    }

    public function test_someone_elses_business_is_a_404(): void
    {
        $theirs = User::factory()->create()->workspaces()->create(['name' => 'Not Mine']);
        $header = ['X-Workspace-Id' => (string) $theirs->id];

        $this->getJson('/api/v1/navigation', $header)->assertNotFound();
        $this->getJson('/api/v1/apps-center', $header)->assertNotFound();
        $this->postJson('/api/v1/apps/sales_lite/deactivate', [], $header)->assertNotFound();

        $this->assertTrue($theirs->workspaceApps()->where('family_key', 'sales')->first()->isActive());
    }

    public function test_every_endpoint_needs_a_token(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/navigation')->assertUnauthorized();
        $this->getJson('/api/v1/apps-center')->assertUnauthorized();
        $this->postJson('/api/v1/apps/sales/activate')->assertUnauthorized();
        $this->postJson('/api/v1/apps/sales_lite/deactivate')->assertUnauthorized();
    }

    /* ------------------------------------------- the guard on app APIs */

    public function test_an_apps_api_needs_the_app_on_at_the_edition_it_asks_for(): void
    {
        $this->salesRoutes();

        $this->getJson('/api/v1/test-sales/invoices')->assertOk();
        $this->postJson('/api/v1/test-sales/invoices')->assertOk();
        $this->getJson('/api/v1/test-sales/orders')->assertForbidden();
        $this->getJson('/api/v1/test-crm/leads')->assertForbidden();

        $this->onPlan('standard');
        $this->activate('sales')->assertOk();

        $this->getJson('/api/v1/test-sales/orders')->assertOk();
    }

    public function test_a_turned_off_app_stays_readable_but_not_writable(): void
    {
        $this->salesRoutes();

        $this->deactivate('sales_lite')->assertOk();

        $this->getJson('/api/v1/test-sales/invoices')->assertOk();
        $this->postJson('/api/v1/test-sales/invoices')->assertForbidden();

        // Off altogether, every record of the family reads - Full-only ones
        // too, for a business that once had Full. Only a family still on at a
        // lower edition keeps the higher edition's routes closed.
        $this->getJson('/api/v1/test-sales/orders')->assertOk();
    }

    /* ----------------------------------------------- the cached sidebar */

    public function test_the_sidebar_is_rebuilt_when_the_catalog_changes(): void
    {
        $this->assertSame(['Dashboard'], $this->labels($this->navigation()['menu']['core']));

        MenuItem::create(['section' => 'core', 'label' => 'Contacts', 'route' => '/contacts', 'sort_order' => 5]);

        $this->assertSame(['Dashboard', 'Contacts'], $this->labels($this->navigation()['menu']['core']));
    }

    public function test_the_sidebar_is_rebuilt_when_the_business_is_renamed(): void
    {
        $this->assertSame($this->workspace->name, $this->navigation()['workspace']['name']);

        $this->putJson("/api/v1/workspaces/{$this->workspace->id}", ['name' => 'Renamed Business'])->assertOk();

        $this->assertSame('Renamed Business', $this->navigation()['workspace']['name']);

        // The version bump is bookkeeping: the log names only what the user changed.
        $this->assertSame(['name'], ActivityLog::where('user_id', $this->owner->id)->where('action', 'updated')->latest('id')->first()->properties['fields']);
    }
}
