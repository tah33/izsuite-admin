<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\SalesDocument;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 16 Phase H2a: the full Sales app's "Sales by" reports - Sales Summary
 * grouped one way, under each one's own name - and its other groupings of
 * Sales Summary. Wednesday 28 October 2026, over two invoices:
 *
 *   INV-00001, made on Sales Lite   one chair, 150.00 + VAT 22.50
 *   INV-00002, on the full app      the base invoice, 295.00 + VAT 40.50:
 *                                   two chairs (270.00) and delivery (25.00),
 *                                   sold by the owner for Dhaka North
 *
 * The chair is in Furniture, by Ergo; delivery is no item.
 */
class SalesByReportsTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private int $furniture;

    private int $lite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $full = Application::create(['app_key' => 'sales', 'name' => 'Sales', 'family_key' => 'sales', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 10, 'is_active' => true, 'status' => 'available']);
        $full->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->setUpSales();
    }

    private function full(): void
    {
        $this->postJson('/api/v1/apps/sales/activate')->assertOk();
    }

    /** The story's two invoices: one chair on Sales Lite, then the full app turned on and the base invoice sold by the owner for Dhaka North. */
    private function sell(): void
    {
        $this->furniture = $this->business->itemCategories()->create(['name' => 'Furniture'])->id;
        $this->chair->forceFill(['category_id' => $this->furniture, 'brand_id' => $this->business->itemBrands()->create(['name' => 'Ergo'])->id])->save();

        $this->lite = $this->issuedInvoice(['lines' => [['item_id' => $this->chair->id, 'name' => 'Office Chair', 'quantity' => '1', 'unit' => 'Piece', 'unit_price' => '150.00', 'tax_rate_id' => $this->vat->id]]]);

        $this->full();
        $team = $this->postJson('/api/v1/business-settings/sales-teams', ['name' => 'Dhaka North', 'member_ids' => [$this->user->id]])->assertCreated()->json('data.id');

        $this->issuedInvoice(['salesperson_id' => $this->user->id, 'sales_team_id' => $team]);
    }

    /** @return list<array{0: string, 1: int, 2: string, 3: string}> each row's group, invoices, net sales and tax */
    private function rows(string $report): array
    {
        return array_map(
            fn (array $row) => [$row['group'], $row['invoices'], $row['net_sales'], $row['tax']],
            $this->getJson("/api/v1/reports/{$report}")->assertOk()->json('data.rows'),
        );
    }

    public function test_the_sales_by_reports_come_with_the_full_sales_app(): void
    {
        $this->assertNotContains('sales-by-customer', array_column($this->getJson('/api/v1/reports')->assertOk()->json('data'), 'key'));
        $this->getJson('/api/v1/reports/sales-by-salesperson')->assertNotFound();

        $this->full();

        $catalog = $this->getJson('/api/v1/reports')->assertOk()->json('data');
        $keys    = array_column($catalog, 'key');
        $entries = array_column($catalog, null, 'key');

        // After Sales Lite's own sales reports, in Doc 16's order.
        $this->assertSame(
            ['sales-by-customer', 'sales-by-item', 'sales-by-category', 'sales-by-brand', 'sales-by-location', 'sales-by-salesperson', 'sales-by-team', 'sales-by-source'],
            array_slice($keys, array_search('credit-notes', $keys, true) + 1, 8),
        );
        $this->assertSame(['Sales by Sales Team', 'Sales & Receivables', ['period', 'location', 'currency']], [$entries['sales-by-team']['name'], $entries['sales-by-team']['category'], $entries['sales-by-team']['filters']]);
        $this->assertSame(['period', 'currency'], $entries['sales-by-location']['filters']);
    }

    public function test_each_is_sales_summary_grouped_its_own_way_under_its_own_name(): void
    {
        $this->sell();

        // Its own grouping, whatever is asked.
        $this->getJson('/api/v1/reports/sales-by-salesperson?group_by=month')->assertOk()
            ->assertJsonPath('data.title', 'Sales by Salesperson')
            ->assertJsonPath('data.columns.0.label', 'Salesperson')
            ->assertJsonPath('data.totals.net_sales', '445.00');

        $this->assertSame([[$this->user->name, 1, '295.00', '40.50'], ['No salesperson', 1, '150.00', '22.50']], $this->rows('sales-by-salesperson?group_by=month'));
        $this->assertSame([['Dhaka North', 1, '295.00', '40.50'], ['No team', 1, '150.00', '22.50']], $this->rows('sales-by-team'));
        $this->assertSame([['Sales', 1, '295.00', '40.50'], ['Sales Lite', 1, '150.00', '22.50']], $this->rows('sales-by-source'));
        $this->assertSame([['Uddin Traders', 2, '445.00', '63.00']], $this->rows('sales-by-customer'));

        // By each line's item as it is now: delivery is no item, so in no category and of no brand.
        $this->assertSame([['Furniture', 2, '420.00', '63.00'], ['No category', 1, '25.00', '0.00']], $this->rows('sales-by-category'));
        $this->assertSame([['Ergo', 2, '420.00', '63.00'], ['No brand', 1, '25.00', '0.00']], $this->rows('sales-by-brand'));
        $this->assertSame([['Office Chair', 2, '420.00', '63.00'], ['Delivery', 1, '25.00', '0.00']], $this->rows('sales-by-item'));

        $this->getJson('/api/v1/reports/sales-by-location')->assertOk()->assertJsonPath('data.columns.0.label', 'Location')->assertJsonPath('data.totals.net_sales', '445.00');

        // A document from before sources were kept.
        SalesDocument::query()->whereKey($this->lite)->update(['source_app_id' => null]);
        $this->assertSame([['Sales', 1, '295.00', '40.50'], ['Not recorded', 1, '150.00', '22.50']], $this->rows('sales-by-source'));
    }

    public function test_sales_summary_takes_the_full_apps_groupings_only_with_it(): void
    {
        // Sales Lite has none of them - and another report's grouping is by month.
        $this->getJson('/api/v1/reports/sales-summary?group_by=salesperson')->assertUnprocessable()->assertJsonValidationErrors(['group_by' => 'Only the full Sales app groups sales that way.']);
        $this->getJson('/api/v1/reports/sales-summary?group_by=supplier')->assertOk()->assertJsonPath('data.columns.0.label', 'Month')->assertJsonPath('data.title', 'Sales Summary');

        $this->sell();

        // One item's or one category's sales are its lines alone: who sold them, their team, the app that made them.
        $chair = "item_id={$this->chair->id}";
        $this->assertSame([[$this->user->name, 1, '270.00', '40.50'], ['No salesperson', 1, '150.00', '22.50']], $this->rows("sales-summary?group_by=salesperson&{$chair}"));
        $this->assertSame([['Dhaka North', 1, '270.00', '40.50'], ['No team', 1, '150.00', '22.50']], $this->rows("sales-summary?group_by=team&category_id={$this->furniture}"));
        $this->assertSame([['Sales', 1, '270.00', '40.50'], ['Sales Lite', 1, '150.00', '22.50']], $this->rows("sales-summary?group_by=source&{$chair}"));
        $this->assertSame([['Ergo', 2, '420.00', '63.00'], ['No brand', 1, '25.00', '0.00']], $this->rows('sales-summary?group_by=brand'));
    }

    public function test_a_sales_by_report_downloads_under_its_own_name(): void
    {
        $this->sell();

        $csv = $this->get('/api/v1/reports/sales-by-team/export', ['Accept' => 'application/json'])
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=sales-by-team-2026-10-28.csv')
            ->streamedContent();

        $this->assertStringContainsString('"Sales Team",Invoices,Subtotal,Discounts,"Credit Notes","Net Sales",Tax,"Grand Total"', $csv);
        $this->assertStringContainsString('"Dhaka North",1,295.00,0.00,0.00,295.00,40.50,335.50', $csv);
        $this->assertDatabaseHas('activity_logs', ['action' => 'exported', 'description' => 'Exported the Sales by Sales Team report as CSV']);
    }
}
