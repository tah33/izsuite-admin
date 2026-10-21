<?php

namespace Tests\Feature;

use App\Models\Frontend\AppCategory;
use App\Models\Frontend\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppsApiTest extends TestCase
{
    use RefreshDatabase;

    private function seedCategories(): void
    {
        AppCategory::create(['name' => 'Productivity', 'is_active' => true]);
        AppCategory::create(['name' => 'Accounting', 'is_active' => true]);
        AppCategory::create(['name' => 'Archived', 'is_active' => false]);
    }

    private function category(string $name): int
    {
        return AppCategory::firstOrCreate(['name' => $name], ['is_active' => true])->id;
    }

    private function seedApps(): void
    {
        Application::create([
            'name' => 'Notion', 'description' => 'Notes and docs', 'price' => 9.99,
            'category_id' => $this->category('Productivity'), 'status' => 'coming_soon', 'is_active' => true,
            'logo_url' => 'apps/notion.png',
        ]);
        Application::create([
            'name' => 'Xero', 'description' => 'Bookkeeping', 'price' => 0,
            'category_id' => $this->category('Accounting'), 'status' => 'available', 'is_active' => true,
        ]);
        Application::create([
            'name' => 'Dead App', 'description' => 'Retired', 'price' => 5,
            'category_id' => $this->category('Productivity'), 'status' => 'available', 'is_active' => false,
        ]);
    }

    /* ---------------------------------------------------------- categories */

    public function test_category_list_returns_active_rows_sorted_by_name(): void
    {
        $this->seedCategories();

        $response = $this->getJson('/api/v1/app-categories');

        $response->assertOk()
            ->assertJsonStructure([
                'data'       => [['id', 'name', 'is_active', 'created_at']],
                'pagination' => ['current_page', 'last_page', 'per_page', 'total', 'has_next_page'],
            ])
            ->assertJsonPath('pagination.total', 2);

        // Inactive category is excluded, and the order is alphabetical.
        $this->assertSame(['Accounting', 'Productivity'], array_column($response->json('data'), 'name'));
    }

    public function test_category_list_can_be_searched(): void
    {
        $this->seedCategories();

        $response = $this->getJson('/api/v1/app-categories?search=count');

        $response->assertOk()->assertJsonPath('pagination.total', 1);
        $this->assertSame(['Accounting'], array_column($response->json('data'), 'name'));
    }

    public function test_category_list_paginates(): void
    {
        $this->seedCategories();

        $this->getJson('/api/v1/app-categories?per_page=10&page=1')
            ->assertOk()
            ->assertJsonPath('pagination.per_page', 10)
            ->assertJsonPath('pagination.current_page', 1)
            ->assertJsonPath('pagination.has_next_page', false);
    }

    /* --------------------------------------------------------------- apps */

    public function test_app_list_returns_active_rows_sorted_by_name(): void
    {
        $this->seedApps();

        $response = $this->getJson('/api/v1/apps');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id', 'app_key', 'name', 'description', 'price', 'price_formatted',
                    'logo_url', 'icon', 'category_id', 'category', 'status', 'is_active',
                    'family_key', 'edition', 'edition_label', 'sort_order', 'created_at',
                ]],
                'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('pagination.total', 2);

        $this->assertSame(['Notion', 'Xero'], array_column($response->json('data'), 'name'));
    }

    public function test_app_logo_is_returned_as_an_absolute_url(): void
    {
        $this->seedApps();

        $notion = collect($this->getJson('/api/v1/apps')->json('data'))
            ->firstWhere('name', 'Notion');

        $this->assertStringStartsWith('http', $notion['logo_url']);
        $this->assertStringContainsString('apps/notion.png', $notion['logo_url']);

        // An app with no logo returns null rather than a broken URL.
        $xero = collect($this->getJson('/api/v1/apps')->json('data'))
            ->firstWhere('name', 'Xero');

        $this->assertNull($xero['logo_url']);
    }

    public function test_app_list_filters_by_category(): void
    {
        $this->seedApps();

        $response = $this->getJson('/api/v1/apps?category=Accounting');

        $response->assertOk()->assertJsonPath('pagination.total', 1);
        $this->assertSame(['Xero'], array_column($response->json('data'), 'name'));
    }

    public function test_app_list_filters_by_status(): void
    {
        $this->seedApps();

        $response = $this->getJson('/api/v1/apps?status=coming_soon');

        $response->assertOk()->assertJsonPath('pagination.total', 1);
        $this->assertSame(['Notion'], array_column($response->json('data'), 'name'));
    }

    public function test_app_search_spans_name_description_and_category(): void
    {
        $this->seedApps();

        $this->getJson('/api/v1/apps?search=Bookkeeping')
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.name', 'Xero');

        $this->getJson('/api/v1/apps?search=Productivity')
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.name', 'Notion');
    }

    public function test_unknown_filter_values_return_an_empty_list_not_an_error(): void
    {
        $this->seedApps();

        $this->getJson('/api/v1/apps?category=DoesNotExist')
            ->assertOk()
            ->assertJsonPath('pagination.total', 0)
            ->assertJsonPath('data', []);
    }

    /* ----------------------------------------------------------- editions */

    /** A family with both editions (Doc 01 section 9). */
    private function seedSalesFamily(): array
    {
        $make = fn (string $name, string $edition) => Application::create([
            'name' => $name, 'category_id' => $this->category('Sales & Commerce'),
            'status' => 'available', 'is_active' => true,
            'family_key' => 'sales', 'edition' => $edition, 'sort_order' => 10,
        ]);

        return [$make('Sales Lite', 'lite'), $make('Sales', 'full')];
    }

    public function test_family_key_and_app_key_default_from_the_name(): void
    {
        $standalone = Application::create(['name' => 'Task Manager']);
        $lite       = Application::create(['name' => 'Sales Lite', 'family_key' => 'sales', 'edition' => 'lite']);

        $this->assertSame('task-manager', $standalone->family_key);
        $this->assertSame('task-manager', $standalone->app_key);
        $this->assertSame('sales_lite', $lite->app_key);
    }

    public function test_a_lite_app_upgrades_to_the_active_full_edition_of_its_family(): void
    {
        [$lite, $full] = $this->seedSalesFamily();
        Application::create(['name' => 'CRM', 'family_key' => 'crm', 'edition' => 'full']);

        $this->assertSame(['sales'], $lite->upgrades()->pluck('app_key')->all());
        $this->assertTrue($full->upgrades()->isEmpty());
        $this->assertSame($lite->id, $full->liteEdition()->id);

        $full->update(['is_active' => false]);
        $this->assertTrue($lite->upgrades()->isEmpty());
    }

    public function test_app_list_exposes_edition_fields(): void
    {
        $this->seedSalesFamily();

        $apps = collect($this->getJson('/api/v1/apps')->json('data'));
        $full = $apps->firstWhere('edition', 'full');
        $lite = $apps->firstWhere('edition', 'lite');

        $this->assertSame('sales', $full['family_key']);
        $this->assertSame('sales', $full['app_key']);
        $this->assertSame('Full', $full['edition_label']);
        $this->assertSame('sales_lite', $lite['app_key']);
        $this->assertSame('Lite', $lite['edition_label']);
        $this->assertSame('Sales & Commerce', $lite['category']);
        $this->assertSame(10, $lite['sort_order']);
    }

    /* --------------------------------------------------------- validation */

    public function test_per_page_outside_the_allowed_list_is_rejected(): void
    {
        foreach (['/api/v1/apps?per_page=7', '/api/v1/app-categories?per_page=7'] as $url) {
            $this->getJson($url)
                ->assertStatus(422)
                ->assertJsonValidationErrors('per_page');
        }
    }

    public function test_endpoints_are_public(): void
    {
        $this->getJson('/api/v1/apps')->assertOk();
        $this->getJson('/api/v1/app-categories')->assertOk();
    }
}
