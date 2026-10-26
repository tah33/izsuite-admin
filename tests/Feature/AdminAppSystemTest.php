<?php

namespace Tests\Feature;

use App\Models\Admin\Role;
use App\Models\Billing\Package;
use App\Models\Frontend\AppCategory;
use App\Models\Frontend\AppDependency;
use App\Models\Frontend\Application;
use App\Models\Frontend\MenuItem;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The admin side of the app system: the catalog's rules as the admin panel
 * enforces them, and every screen rendering.
 */
class AdminAppSystemTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private AppCategory $category;

    /** @var array<string, Package> by key */
    private array $packages;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->admin    = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]);
        $this->category = AppCategory::create(['name' => 'Sales & Commerce', 'sort_order' => 1]);
        $this->packages = Package::all()->keyBy('key')->all();
    }

    private function app(string $key, string $family, string $edition = 'full', string $lowest = 'standard'): Application
    {
        $app = Application::create([
            'app_key' => $key, 'name' => ucfirst(str_replace(['_', '-'], ' ', $key)), 'family_key' => $family,
            'edition' => $edition, 'category_id' => $this->category->id, 'status' => 'available', 'is_active' => true,
        ]);

        $app->packages()->attach(collect($this->packages)->filter(fn ($p) => $p->rank >= $this->packages[$lowest]->rank)->pluck('id'));

        return $app;
    }

    /** @return array<string, list<string>> app key => plan keys */
    private function access(): array
    {
        return DB::table('package_apps')
            ->join('apps', 'apps.id', '=', 'package_apps.app_id')
            ->join('packages', 'packages.id', '=', 'package_apps.package_id')
            ->orderBy('packages.rank')
            ->get(['apps.app_key', 'packages.key'])
            ->groupBy('app_key')
            ->map(fn ($rows) => $rows->pluck('key')->all())
            ->all();
    }

    public function test_every_app_system_screen_renders(): void
    {
        $sales = $this->app('sales', 'sales');
        MenuItem::create(['section' => 'core', 'label' => 'Dashboard', 'route' => '/dashboard']);
        $item = MenuItem::create(['section' => 'apps', 'family_key' => 'sales', 'min_edition' => 'lite', 'label' => 'Invoices', 'route' => '/sales/invoices']);
        User::factory()->create()->workspaces()->create(['name' => 'Acme']);

        $this->actingAs($this->admin);

        foreach ([
            route('admin.apps.index'),
            route('admin.apps.create'),
            route('admin.apps.edit', $sales->id),
            route('admin.app-categories.index'),
            route('admin.packages.index'),
            route('admin.packages.edit', $this->packages['standard']->id),
            route('admin.package-access.index'),
            route('admin.menu-items.index'),
            route('admin.menu-items.create', ['section' => 'apps', 'family' => 'sales']),
            route('admin.menu-items.edit', $item->id),
            route('admin.workspaces.index'),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_package_access_keeps_an_app_in_every_plan_above_its_lowest(): void
    {
        $crm = $this->app('crm', 'crm', 'full', 'premium');
        $this->actingAs($this->admin);

        // Only Standard ticked: Premium follows.
        $this->putJson(route('admin.package-access.update'), ['access' => [$crm->id => [$this->packages['standard']->id]]])
            ->assertOk();

        $this->assertSame(['crm' => ['standard', 'premium']], $this->access());
    }

    public function test_package_access_refuses_a_plan_without_what_its_apps_need(): void
    {
        $purchases = $this->app('purchases', 'purchases', 'full', 'premium');
        $inventory = $this->app('inventory', 'inventory', 'full', 'premium');
        AppDependency::create(['app_id' => $inventory->id, 'requires_family' => 'purchases', 'min_edition' => 'lite']);
        $this->actingAs($this->admin);

        $this->putJson(route('admin.package-access.update'), ['access' => [
            $inventory->id => [$this->packages['standard']->id],
            $purchases->id => [$this->packages['premium']->id],
        ]])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Inventory is in Standard, but needs Purchases, which is only in Premium.');

        // Nothing changed.
        $this->assertSame(['purchases' => ['premium'], 'inventory' => ['premium']], $this->access());
    }

    public function test_a_dependency_cannot_be_on_its_own_family_loop_or_sit_above_the_app(): void
    {
        $purchases = $this->app('purchases', 'purchases', 'full', 'standard');
        $inventory = $this->app('inventory', 'inventory', 'full', 'standard');
        $this->app('crm', 'crm', 'full', 'premium');
        $this->actingAs($this->admin);

        $add = fn (Application $app, string $family) => $this->postJson(
            route('admin.apps.dependencies.store', $app->id),
            ['requires_family' => $family, 'min_edition' => 'lite'],
        );

        $add($inventory, 'inventory')->assertStatus(422)->assertJsonPath('message', 'An app cannot require its own family.');
        $add($inventory, 'purchases')->assertOk();
        $add($purchases, 'inventory')->assertStatus(422)->assertJsonPath('message', 'Inventory already needs Purchases, directly or through other apps, so this would go round in a circle.');
        $add($inventory, 'crm')->assertStatus(422)->assertJsonPath('message', 'Inventory is in Standard, but Crm is not in that plan. Change Package Access first.');

        $this->assertSame(['purchases'], $inventory->dependencies()->pluck('requires_family')->all());
    }

    public function test_a_menu_items_route_starts_with_its_family(): void
    {
        $this->app('sales', 'sales');
        $this->actingAs($this->admin);

        $this->postJson(route('admin.menu-items.store'), [
            'section' => 'apps', 'family_key' => 'sales', 'label' => 'Invoices', 'route' => '/invoices', 'sort_order' => 1, 'is_active' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('route');

        $this->postJson(route('admin.menu-items.store'), [
            'section' => 'apps', 'family_key' => 'sales', 'label' => 'Invoices', 'route' => '/sales/invoices', 'sort_order' => 1, 'is_active' => 1,
        ])->assertOk();

        // A Core item has no family or edition, whatever is sent.
        $this->postJson(route('admin.menu-items.store'), [
            'section' => 'core', 'family_key' => 'sales', 'min_edition' => 'full', 'label' => 'Contacts', 'route' => '/contacts', 'sort_order' => 2, 'is_active' => 1,
        ])->assertOk();

        $this->assertDatabaseHas('menu_items', ['label' => 'Contacts', 'section' => 'core', 'family_key' => null, 'min_edition' => null]);
    }

    public function test_a_category_or_app_in_use_cannot_be_deleted(): void
    {
        $lite = $this->app('sales_lite', 'sales', 'lite', 'starter');
        User::factory()->create()->workspaces()->create(['name' => 'Acme']);   // turns Sales Lite on
        $this->actingAs($this->admin);

        $this->deleteJson(route('admin.app-categories.destroy', $this->category->id))->assertStatus(422);
        $this->deleteJson(route('admin.apps.destroy', $lite->id))->assertStatus(422);

        $this->assertDatabaseHas('apps', ['id' => $lite->id]);
        $this->assertDatabaseHas('app_categories', ['id' => $this->category->id]);
    }

    public function test_moving_a_business_down_a_plan_brings_its_apps_within_it(): void
    {
        $this->app('sales_lite', 'sales', 'lite', 'starter');
        $sales     = $this->app('sales', 'sales', 'full', 'standard');
        $workspace = User::factory()->create()->workspaces()->create(['name' => 'Acme']);
        $workspace->package()->associate($this->packages['standard'])->save();
        $workspace->workspaceApps()->where('family_key', 'sales')->update(['app_id' => $sales->id]);
        $this->actingAs($this->admin);

        $this->patchJson(route('admin.workspaces.package', $workspace->id), ['package_id' => $this->packages['starter']->id])
            ->assertOk()
            ->assertJsonPath('message', '"Acme" is now on Billing Starter.');

        $workspace->refresh();
        $this->assertSame($this->packages['starter']->id, $workspace->package_id);
        $this->assertSame('sales_lite', $workspace->workspaceApps()->with('app')->first()->app->app_key);
    }

    public function test_an_app_can_be_switched_off_from_its_form(): void
    {
        $sales = $this->app('sales', 'sales');
        $this->actingAs($this->admin);

        // An unticked box sends nothing; it must still switch the app off.
        $this->putJson(route('admin.apps.update', $sales->id), [
            'name' => 'Sales', 'category_id' => $this->category->id, 'status' => 'available', 'sort_order' => 5,
            // Fixed once made: these are ignored.
            'app_key' => 'renamed', 'family_key' => 'other', 'edition' => 'lite',
        ])->assertOk();

        $sales->refresh();
        $this->assertFalse($sales->is_active);
        $this->assertSame(['sales', 'sales', 'full'], [$sales->app_key, $sales->family_key, $sales->edition->value]);
    }
}
