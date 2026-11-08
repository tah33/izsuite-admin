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
use App\Services\User\Apps\SyncAppsWithPackage;
use App\Support\Permissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The Apps Center's pages (Doc 13 sections 5 to 10) - Doc 13 Phase A: each
 * edition names the plan it needs; each app has a page saying what it brings;
 * a role with Manage Apps turns apps on and off; turning one off says first
 * what goes, and can take the apps that need it along; the website reads
 * the same catalog.
 */
class AppsCenterPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    /** @var array<string, Application> by app_key */
    private array $apps = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $sales           = AppCategory::create(['name' => 'Sales & Commerce', 'sort_order' => 1]);
        $buying          = AppCategory::create(['name' => 'Purchasing & Inventory', 'sort_order' => 2]);

        // key, name, family, edition, category, lowest plan, status
        foreach ([
            ['sales_lite', 'Sales Lite', 'sales', 'lite', $sales, 'starter'],
            ['sales', 'Sales', 'sales', 'full', $sales, 'standard'],
            ['crm', 'CRM', 'crm', 'full', $sales, 'premium'],
            ['pos', 'Point of Sale', 'pos', 'full', $sales, 'standard', 'coming_soon'],
            ['purchases_lite', 'Purchases Lite', 'purchases', 'lite', $buying, 'starter'],
            ['inventory', 'Inventory', 'inventory', 'full', $buying, 'standard'],
        ] as $order => $row) {
            [$key, $name, $family, $edition, $category, $lowest] = $row;

            $this->apps[$key]                                    = Application::create([
                'app_key'    => $key, 'name' => $name, 'family_key' => $family, 'edition' => $edition, 'category_id' => $category->id,
                'sort_order' => $order, 'is_active' => true, 'status' => $row[6] ?? 'available', 'description' => "{$name} in short.",
            ]);

            $this->apps[$key]->packages()->attach(Package::where('rank', '>=', Package::where('key', $lowest)->value('rank'))->pluck('id'));
        }

        AppDependency::create(['app_id' => $this->apps['inventory']->id, 'requires_family' => 'purchases', 'min_edition' => 'lite']);

        MenuItem::create(['section' => 'apps', 'family_key' => 'sales', 'min_edition' => 'lite', 'label' => 'Invoices', 'route' => '/sales/invoices', 'sort_order' => 1]);
        MenuItem::create(['section' => 'apps', 'family_key' => 'sales', 'min_edition' => 'full', 'label' => 'Sales Orders', 'route' => '/sales/orders', 'sort_order' => 2]);
        MenuItem::create(['section' => 'apps', 'family_key' => 'purchases', 'min_edition' => 'lite', 'label' => 'Purchases', 'route' => '/purchases', 'sort_order' => 3]);
        MenuItem::create(['section' => 'apps', 'family_key' => 'inventory', 'min_edition' => 'lite', 'label' => 'Stock', 'route' => '/inventory/stock', 'sort_order' => 4]);

        $this->owner     = User::factory()->create();
        $this->workspace = $this->owner->workspaces()->create(['name' => 'Main Business']);
        app(SyncAppsWithPackage::class)->changePackage($this->workspace, Package::where('key', 'standard')->firstOrFail());

        Sanctum::actingAs($this->owner);
    }

    public function test_each_edition_names_the_plan_it_needs(): void
    {
        $editions = collect($this->getJson('/api/v1/apps-center')->assertOk()->json('data.categories'))
            ->flatMap(fn (array $category) => $category['families'])
            ->flatMap(fn (array $family) => $family['editions'])
            ->keyBy('app_key');

        $this->assertSame(['Billing Starter', 'Standard', 'Premium'], [$editions['sales_lite']['package'], $editions['sales']['package'], $editions['crm']['package']]);
    }

    public function test_an_app_has_a_page_of_its_own(): void
    {
        $this->apps['sales_lite']->update(['long_description' => 'Quote, invoice and get paid.', 'features' => ['Quotations', 'Invoices'], 'support_url' => 'https://help.example.test/sales']);

        $page = $this->getJson('/api/v1/apps-center/sales')->assertOk()->json('data');

        $this->assertSame(['Sales', 'Sales & Commerce', 'https://help.example.test/sales'], [$page['name'], $page['category'], $page['support_url']]);
        $this->assertSame(['Sales Lite', 'Sales'], array_column($page['editions'], 'name'));
        $this->assertSame(['Quote, invoice and get paid.', ['Quotations', 'Invoices']], [$page['editions'][0]['long_description'], $page['editions'][0]['features']]);
        $this->assertSame([['label' => 'Invoices', 'edition' => 'lite'], ['label' => 'Sales Orders', 'edition' => 'full']], $page['pages']);
        $this->assertContains(['name' => 'Sales Summary', 'shared' => false], $page['reports']);
        $this->assertContains(['name' => 'Tax Summary', 'shared' => true], $page['reports']);
        $this->assertContains('Invoices', $page['permissions']);
        $this->assertContains('Sales Values', $page['permissions']);

        $this->assertSame(['Inventory'], $this->getJson('/api/v1/apps-center/purchases')->json('data.needed_by'));
        $this->getJson('/api/v1/apps-center/nothing-here')->assertNotFound();
    }

    public function test_a_role_with_manage_apps_turns_apps_on_and_off(): void
    {
        $keeper = $this->workspace->roles()->create(['name' => 'App Keeper', 'permissions' => Permissions::expand(['settings.apps'])]);
        $viewer = $this->workspace->roles()->create(['name' => 'Viewer', 'permissions' => Permissions::expand(['dashboard.view'])]);
        $member = User::factory()->create();
        $other  = User::factory()->create();
        $this->workspace->members()->create(['user_id' => $member->id, 'role_id' => $keeper->id, 'name' => $member->name, 'email' => $member->email, 'status' => 'active']);
        $this->workspace->members()->create(['user_id' => $other->id, 'role_id' => $viewer->id, 'name' => $other->name, 'email' => $other->email, 'status' => 'active']);

        Sanctum::actingAs($member);
        $this->postJson('/api/v1/apps/sales/activate')->assertOk()->assertJsonPath('message', 'Sales replaced Sales Lite.');
        $this->postJson('/api/v1/apps/sales/deactivate')->assertOk();

        Sanctum::actingAs($other);
        $this->postJson('/api/v1/apps/sales/activate')->assertForbidden();
    }

    public function test_turning_an_app_off_says_what_goes_and_can_take_its_dependents_along(): void
    {
        $this->postJson('/api/v1/apps/inventory/activate')->assertOk();

        $impact = $this->getJson('/api/v1/apps/purchases_lite/deactivation')->assertOk()->json('data');
        $this->assertSame([null, ['Inventory'], ['Purchases']], [$impact['falls_back_to'], $impact['dependents'], $impact['pages']]);
        $this->assertContains('Purchase Summary', $impact['reports']);

        // Turned off, the pages it shows go - not those of an edition it is not.
        $this->assertSame(['Invoices'], $this->getJson('/api/v1/apps/sales_lite/deactivation')->json('data.pages'));

        // Back to Lite, only the full edition's pages go - and no report.
        $this->postJson('/api/v1/apps/sales/activate')->assertOk();
        $this->assertSame(['Sales Lite', ['Sales Orders'], []], array_values(array_intersect_key($this->getJson('/api/v1/apps/sales/deactivation')->json('data'), array_flip(['falls_back_to', 'pages', 'reports']))));

        $this->postJson('/api/v1/apps/purchases_lite/deactivate')->assertUnprocessable()->assertJsonPath('errors.app.0', 'Turn these off first: Inventory.');
        $this->postJson('/api/v1/apps/purchases_lite/deactivate', ['with_dependents' => true])
            ->assertOk()
            ->assertJsonPath('message', 'Purchases Lite is off. Its records are kept. Also off: Inventory.');

        $this->assertSame(['inactive', 'inactive'], $this->workspace->workspaceApps()->whereIn('family_key', ['purchases', 'inventory'])->orderBy('family_key')->pluck('status')->all());
        $this->assertSame(2, ActivityLog::where('description', 'like', 'Turned off %')->count());
    }

    public function test_the_website_reads_the_catalog(): void
    {
        $this->app['auth']->forgetGuards();

        $catalog = $this->getJson('/api/v1/catalog')->assertOk()->json('data');

        $sales   = collect($catalog['categories'])->firstWhere('name', 'Sales & Commerce');
        $this->assertSame(['Sales', 'CRM', 'Point of Sale'], array_column($sales['apps'], 'name'));
        $this->assertSame([['name' => 'Sales Lite', 'package' => 'Billing Starter'], ['name' => 'Sales', 'package' => 'Standard']], $sales['apps'][0]['editions']);
        $this->assertTrue(collect($sales['apps'])->firstWhere('name', 'Point of Sale')['coming_soon']);

        $starter = collect($catalog['packages'])->firstWhere('key', 'starter');
        $this->assertSame(['Sales Lite', 'Purchases Lite'], $starter['apps']);
        $this->assertSame(1, $starter['user_limit']);
    }
}
