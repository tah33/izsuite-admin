<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\PriceListRule;
use App\Models\User\SalesDocument;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 16 Phase H2b: the Discount Report, Price List Usage and Estimated Sales
 * Margin, on the full Sales app. Wednesday 28 October 2026. The chair costs
 * 100.00; the Trade list prices it at 120.00, and anything at 10% off from 5.
 *
 *   INV-00001  the base invoice, sold by the owner: 2 chairs at 150.00 less
 *              10% (270.00) and delivery (25.00) - 30.00 off its lines
 *   INV-00002  2 chairs at Trade's 120.00 (240.00), less 5% on the whole
 *              (12.00): 228.00
 *   INV-00003  5 chairs at Trade's 135.00 (675.00) and a desk lamp at 40.00,
 *              which has no purchase price; CN-00001 takes a chair back
 */
class SalesAnalysisReportsTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private int $trade;

    private int $first;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);

        $this->offerSalesLite();
        $full = Application::create(['app_key' => 'sales', 'name' => 'Sales', 'family_key' => 'sales', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 10, 'is_active' => true, 'status' => 'available']);
        $full->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->setUpSales();
        $this->postJson('/api/v1/apps/sales/activate')->assertOk();
        $this->chair->forceFill(['purchase_price' => '100.00'])->save();
    }

    /** The story's list, three invoices and credit note. */
    private function sell(): void
    {
        $this->trade = $this->postJson('/api/v1/sales/price-lists', [
            'name' => 'Trade', 'currency' => 'USD', 'is_active' => true, 'all_customers' => true, 'all_locations' => true, 'priority' => 5,
            'rules' => [
                ['target' => 'item', 'item_id' => $this->chair->id, 'min_quantity' => '1', 'method' => 'fixed', 'value' => '120'],
                ['target' => 'all', 'min_quantity' => '5', 'method' => 'discount', 'value' => '10'],
            ],
        ])->assertCreated()->json('data.id');
        [$fixed, $bulk] = PriceListRule::query()->where('price_list_id', $this->trade)->orderBy('position')->pluck('id')->all();

        $this->first = $this->issuedInvoice(['salesperson_id' => $this->user->id]);
        $this->issuedInvoice(['discount_type' => 'percent', 'discount_value' => '5', 'lines' => [$this->chairs('2', '120.00', $fixed)]]);

        $lamp  = $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Desk Lamp', 'unit' => 'Piece', 'sale_price' => '40.00']);
        $third = $this->issuedInvoice(['lines' => [$this->chairs('5', '135.00', $bulk), ['item_id' => $lamp->id, 'name' => 'Desk Lamp', 'quantity' => '1', 'unit' => 'Piece', 'unit_price' => '40.00']]]);

        $note = $this->postJson("/api/v1/sales/invoices/{$third}/credit-notes", [
            'document_date' => '2026-10-28', 'reason' => 'One came back',
            'lines'         => [['source_line_id' => $this->lineOf($third, 0), 'quantity' => '1', 'unit_price' => '135.00']],
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/sales/credit-notes/{$note}/issue")->assertOk();
    }

    /** Chairs priced by one of Trade's rules. */
    private function chairs(string $quantity, string $price, int $rule): array
    {
        return [
            'item_id' => $this->chair->id, 'name' => 'Office Chair', 'quantity' => $quantity, 'unit' => 'Piece', 'unit_price' => $price, 'tax_rate_id' => $this->vat->id,
            'price_list_id' => $this->trade, 'price_list_rule_id' => $rule,
        ];
    }

    /** @return list<list<mixed>> the rows of $report, each as the values of $keys */
    private function rows(string $report, array $keys): array
    {
        return array_map(
            fn (array $row) => array_values(array_intersect_key(array_replace(array_flip($keys), $row), array_flip($keys))),
            $this->getJson("/api/v1/reports/{$report}")->assertOk()->json('data.rows'),
        );
    }

    public function test_the_three_come_with_the_full_app_each_for_a_role_that_may_see_it(): void
    {
        $keys = fn () => array_column($this->getJson('/api/v1/reports')->assertOk()->json('data'), 'key');
        $all  = $keys();

        $this->assertSame(['discounts', 'price-list-usage', 'sales-margin'], array_slice($all, array_search('sales-by-source', $all, true) + 1, 3));

        $entries = array_column($this->getJson('/api/v1/reports')->json('data'), null, 'key');
        $this->assertSame([['period', 'location', 'contact', 'currency'], ['period', 'location', 'category']], [$entries['discounts']['filters'], $entries['sales-margin']['filters']]);

        // Seeing sales is enough for the Discount Report; the margin needs costs, and price list usage the price lists.
        $member = User::factory()->create();
        $role   = $this->business->roles()->create(['name' => 'Analyst', 'permissions' => ['reports.view', 'sales.invoices.view', 'sales.values.view']]);
        $this->business->members()->create(['user_id' => $member->id, 'status' => 'active', 'role_id' => $role->id]);
        Sanctum::actingAs($member);

        $this->assertContains('discounts', $keys());
        $this->assertSame([], array_intersect(['price-list-usage', 'sales-margin'], $keys()));
        $this->getJson('/api/v1/reports/sales-margin')->assertNotFound();

        $role->forceFill(['permissions' => [...$role->permissions, 'items.costs', 'sales.price_lists.view']])->save();
        $this->assertSame(['discounts', 'price-list-usage', 'sales-margin'], array_values(array_intersect($keys(), ['discounts', 'price-list-usage', 'sales-margin'])));
    }

    public function test_the_discount_report_lists_each_invoice_that_gave_one(): void
    {
        $this->sell();
        SalesDocument::query()->whereKey($this->first)->update(['discount_reason' => 'Matching a competitor']);

        $keys = ['number', 'salesperson', 'before', 'line_discounts', 'invoice_discount', 'discount', 'percent', 'reason'];

        // INV-00003 gave none: its lower prices are the list's.
        $this->assertSame([
            ['INV-00001', $this->user->name, '325.00', '30.00', '0.00', '30.00', '9.2', 'Matching a competitor'],
            ['INV-00002', null, '240.00', '0.00', '12.00', '12.00', '5.0', null],
        ], $this->rows('discounts', $keys));

        $this->getJson('/api/v1/reports/discounts')->assertOk()
            ->assertJsonPath('data.title', 'Discount Report')
            ->assertJsonPath('data.rows.0.customer', 'Uddin Traders')
            ->assertJsonPath('data.rows.0.link', "/sales/invoices/{$this->first}")
            ->assertJsonPath('data.totals', ['document_date' => 'Total', 'before' => '565.00', 'line_discounts' => '30.00', 'invoice_discount' => '12.00', 'discount' => '42.00', 'percent' => '7.4']);
    }

    public function test_price_list_usage_counts_each_rules_lines_and_a_saved_list_keeps_them(): void
    {
        $this->sell();

        $keys = ['list', 'rule', 'lines', 'invoices', 'quantity', 'net_sales'];

        $this->assertSame([
            ['Trade', 'Office Chair: USD 120.00', 1, 1, '2.000', '228.00'],
            ['Trade', 'All items, from 5: 10% off its price', 1, 1, '5.000', '675.00'],
        ], $this->rows('price-list-usage', $keys));
        $this->getJson('/api/v1/reports/price-list-usage')->assertJsonPath('data.totals', ['list' => 'Total', 'lines' => 2, 'invoices' => 2, 'net_sales' => '903.00'])
            ->assertJsonPath('data.rows.0.link', "/sales/price-lists/{$this->trade}");

        // Saved again without its bulk rule: the rule it keeps is the same one, so its line stays with it.
        $rules = $this->getJson("/api/v1/sales/price-lists/{$this->trade}")->assertOk()->json('data.rules');
        $this->putJson("/api/v1/sales/price-lists/{$this->trade}", [
            'name' => 'Trade', 'currency' => 'USD', 'is_active' => true, 'all_customers' => true, 'all_locations' => true, 'priority' => 5,
            'rules' => [[...array_diff_key($rules[0], ['names' => true]), 'value' => '125']],
        ])->assertOk();

        $this->assertSame([$rules[0]['id']], PriceListRule::query()->where('price_list_id', $this->trade)->pluck('id')->all());
        $this->assertSame([
            ['Trade', 'Office Chair: USD 125.00', 1, 1, '2.000', '228.00'],
            ['Trade', 'A rule since removed', 1, 1, '5.000', '675.00'],
        ], $this->rows('price-list-usage', $keys));
    }

    public function test_the_margin_is_an_estimate_from_each_items_cost_now(): void
    {
        $this->sell();

        // The chair: 8 sold less one back, 1,038.00 before tax, costing 8 x 100.00. The lamp has no cost; delivery is no item.
        $this->assertSame([
            ['Office Chair', '8.000', '1038.00', '800.00', '238.00', '22.9'],
            ['Desk Lamp', '1.000', '40.00', null, null, null],
        ], $this->rows('sales-margin', ['item', 'quantity', 'net_sales', 'cost', 'margin', 'percent']));

        $margin = $this->getJson('/api/v1/reports/sales-margin')->assertOk()
            ->assertJsonPath('data.totals', ['item' => 'Total of items with a cost', 'net_sales' => '1038.00', 'cost' => '800.00', 'margin' => '238.00', 'percent' => '22.9'])
            ->json('data');
        $this->assertStringContainsString('not a Profit and Loss Statement', $margin['notes'][0]);
        $this->assertSame('Items without a purchase price show no cost or margin, and the totals leave them out.', $margin['notes'][1]);

        // One category's: the chair alone, and the totals are all of it.
        $furniture = $this->business->itemCategories()->create(['name' => 'Furniture'])->id;
        $this->chair->forceFill(['category_id' => $furniture])->save();

        $this->getJson("/api/v1/reports/sales-margin?category_id={$furniture}")->assertOk()
            ->assertJsonPath('data.period', '01 Oct 2026 - 31 Oct 2026 · Furniture items')
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.totals.item', 'Total');
    }

    public function test_a_percentage_prints_with_its_sign(): void
    {
        $this->sell();
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200)]);

        $this->get('/api/v1/reports/sales-margin/pdf', ['Accept' => 'application/json'])->assertOk();

        Http::assertSent(fn ($request) => str_contains((string) collect($request->data())->firstWhere('filename', 'index.html')['contents'], '22.9%'));
    }
}
