<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\Contact;
use App\Models\User\Item;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Services\Reports\ReportCatalog;
use App\Support\Permissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 18 Phase H1: POS's sales reports (section 18.36) - at Front Counter
 * (R1) in Dhaka, its business's time six hours ahead of UTC. On 19 Nov at
 * 16:00 a mug at 15.00 for cash; on 20 Nov at 09:00 two mugs for cash and a
 * tea at 3.50 by card, and a mug of that sale brought back in cash.
 */
class PosSalesReportsTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const RANGE = ['range' => 'custom', 'date_from' => '2026-11-19', 'date_to' => '2026-11-20'];

    private User $user;

    private Workspace $business;

    private Contact $walkIn;

    private int $register;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);

        $this->offerSalesLite();
        Application::create(['app_key' => 'pos', 'name' => 'Point of Sale', 'family_key' => 'pos', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 30, 'is_active' => true, 'status' => 'available'])
            ->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-11-19 10:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->business->forceFill(['base_currency' => 'USD', 'tax_mode' => 'later'])->save();
        $this->business->businessSetting()->create([
            'user_id' => $this->user->id, 'business_name' => 'Main Business', 'language' => 'en', 'country' => 'BD', 'state' => 'Dhaka',
            'city'    => 'Dhaka', 'zip_code' => '1207', 'address_line_1' => 'Road 1', 'timezone' => 'Asia/Dhaka', 'stock_accounting_method' => 'fifo',
        ]);
        $dhaka = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka', 'code' => 'DHK']);
        $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Shop Till', 'currency' => 'USD']);
        $bank = $this->business->paymentAccounts()->create(['type' => 'bank', 'name' => 'City Bank', 'currency' => 'USD']);

        $this->postJson('/api/v1/apps/pos/activate')->assertOk();
        $cash = (int) $this->getJson('/api/v1/pos/registers/options')->assertOk()->json('data.payment_methods.0.id');
        $card = (int) $this->postJson('/api/v1/business-settings/pos/payment-methods', ['name' => 'Card', 'type' => 'card', 'payment_account_id' => $bank->id])->assertCreated()->json('data.id');

        $this->walkIn = $this->business->contacts()->create(['contact_type' => 'individual', 'role' => 'customer', 'name' => 'Walk-in Customer']);
        $this->business->posSetting()->firstOrFail()->update(['walk_in_contact_id' => $this->walkIn->id]);
        $mug = $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Mug', 'unit' => 'Piece', 'sale_price' => '15.00']);
        $tea = $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Black Tea', 'unit' => 'Piece', 'sale_price' => '3.50']);

        $this->register = $this->postJson('/api/v1/pos/registers', [
            'name' => 'Front Counter', 'code' => 'R1', 'location_id' => $dhaka->id, 'currency' => 'USD', 'payment_method_ids' => [$cash, $card], 'cashier_ids' => [$this->user->id],
        ])->assertCreated()->json('data.id');

        $first = $this->postJson('/api/v1/pos/sessions', ['pos_register_id' => $this->register, 'opening_float' => '50'])->assertCreated()->json('data.id');
        $this->sell($mug, '1', '15.00', $cash);
        $this->postJson("/api/v1/pos/sessions/{$first}/close", ['counted_cash' => '65'])->assertOk();

        $this->travelTo(Carbon::parse('2026-11-20 03:00:00', 'UTC'));
        $this->postJson('/api/v1/pos/sessions', ['pos_register_id' => $this->register, 'opening_float' => '50'])->assertCreated();
        $line = $this->sell($mug, '2', '30.00', $cash);
        $this->sell($tea, '1', '3.50', $card);
        $this->postJson('/api/v1/pos/returns', ['order_id' => 2, 'lines' => [['line_id' => $line, 'quantity' => '1', 'condition' => 'resellable']], 'reason' => 'Chipped', 'refund' => 'cash'])->assertCreated();
    }

    /** $quantity of $item for $total, by the method $method: its first line's id. */
    private function sell(Item $item, string $quantity, string $total, int $method): int
    {
        $this->postJson('/api/v1/pos/orders', [
            'contact_id' => $this->walkIn->id, 'lines' => [['item_id' => $item->id, 'quantity' => $quantity]], 'total' => $total, 'payments' => [['method_id' => $method, 'amount' => $total]],
        ])->assertCreated();

        return (int) \App\Models\User\SalesDocument::latest('id')->firstOrFail()->lines->first()->id;
    }

    private function report(string $key, array $query = []): TestResponse
    {
        return $this->getJson("/api/v1/reports/{$key}?".http_build_query([...self::RANGE, ...$query]));
    }

    /** Each row's $keys, in their order. */
    private function rows(TestResponse $response, array $keys): array
    {
        return array_map(fn (array $row) => array_combine($keys, array_map(fn (string $key) => $row[$key] ?? null, $keys)), $response->assertOk()->json('data.rows'));
    }

    public function test_the_summary_counts_completed_sales_less_their_returns_day_by_day(): void
    {
        $summary = $this->report('pos-sales-summary')->assertOk();

        $this->assertSame(['group', 'orders', 'net_sales', 'total', 'average', 'returns', 'net'], array_column($summary->json('data.columns'), 'key'));
        $this->assertSame([
            ['orders' => 1, 'net_sales' => '15.00', 'total' => '15.00', 'average' => '15.00', 'returns' => '0.00', 'net' => '15.00'],
            ['orders' => 2, 'net_sales' => '33.50', 'total' => '33.50', 'average' => '16.75', 'returns' => '15.00', 'net' => '18.50'],
        ], $this->rows($summary, ['orders', 'net_sales', 'total', 'average', 'returns', 'net']));
        $this->assertSame(['orders' => 3, 'net_sales' => '48.50', 'total' => '48.50', 'returns' => '15.00', 'net' => '33.50', 'average' => '16.17'], array_intersect_key($summary->json('data.totals'), array_flip(['orders', 'net_sales', 'total', 'returns', 'net', 'average'])));
        $this->assertSame(['Sales Before Tax', 'Returns', 'Net Sales', 'Average Order'], array_column($summary->json('data.summary'), 'label'));

        // Sales by Day is the same, under its own name; by month it is one row.
        $this->assertSame($summary->json('data.rows'), $this->report('pos-sales-by-day')->json('data.rows'));
        $this->assertSame('Sales by Day', $this->report('pos-sales-by-day')->json('data.title'));
        $this->assertSame([['orders' => 3, 'net' => '33.50']], $this->rows($this->report('pos-sales-summary', ['group_by' => 'month']), ['orders', 'net']));
    }

    public function test_the_sales_by_reports_group_one_way(): void
    {
        // The business's own hours: 16:00 on the 19th, 09:00 on the 20th - in their order.
        $this->assertSame([
            ['group' => '09:00 - 09:59', 'orders' => 2, 'returns' => '15.00', 'net' => '18.50'],
            ['group' => '16:00 - 16:59', 'orders' => 1, 'returns' => '0.00', 'net' => '15.00'],
        ], $this->rows($this->report('pos-sales-by-hour'), ['group', 'orders', 'returns', 'net']));

        $this->assertSame([
            ['group' => 'Mug', 'quantity' => '3.000', 'net_sales' => '45.00', 'returned_quantity' => '1.000', 'returns' => '15.00', 'net' => '30.00'],
            ['group' => 'Black Tea', 'quantity' => '1.000', 'net_sales' => '3.50', 'returned_quantity' => '0.000', 'returns' => '0.00', 'net' => '3.50'],
        ], $this->rows($this->report('pos-sales-by-item'), ['group', 'quantity', 'net_sales', 'returned_quantity', 'returns', 'net']));

        $this->assertSame([['group' => 'Front Counter (R1)', 'orders' => 3, 'net' => '33.50']], $this->rows($this->report('pos-sales-by-register'), ['group', 'orders', 'net']));
        $this->assertSame([['group' => $this->user->name, 'orders' => 3, 'net' => '33.50']], $this->rows($this->report('pos-sales-by-cashier'), ['group', 'orders', 'net']));
        $this->assertSame([['group' => 'No salesperson', 'net' => '33.50']], $this->rows($this->report('pos-sales-by-salesperson'), ['group', 'net']));
        $this->assertSame([['group' => 'No category', 'net' => '33.50']], $this->rows($this->report('pos-sales-by-category'), ['group', 'net']));
    }

    public function test_payment_methods_and_the_average_order(): void
    {
        $this->assertSame([
            ['group' => 'Cash', 'payments' => 2, 'received' => '45.00', 'refunds' => 1, 'refunded' => '15.00', 'net' => '30.00'],
            ['group' => 'Card', 'payments' => 1, 'received' => '3.50', 'refunds' => 0, 'refunded' => '0.00', 'net' => '3.50'],
        ], $this->rows($this->report('pos-payment-methods'), ['group', 'payments', 'received', 'refunds', 'refunded', 'net']));

        $average = $this->report('pos-average-order')->assertOk();
        $this->assertSame([
            ['orders' => 1, 'total' => '15.00', 'average' => '15.00', 'items' => '1.000', 'per_order' => '1.00'],
            ['orders' => 2, 'total' => '33.50', 'average' => '16.75', 'items' => '3.000', 'per_order' => '1.50'],
        ], $this->rows($average, ['orders', 'total', 'average', 'items', 'per_order']));
        $this->assertEquals(['orders' => 3, 'total' => '48.50', 'average' => '16.17', 'items' => '4.000', 'per_order' => '1.33'], array_intersect_key($average->json('data.totals'), array_flip(['orders', 'total', 'average', 'items', 'per_order'])));
    }

    public function test_a_register_or_cashier_narrows_them(): void
    {
        $this->getJson('/api/v1/reports/pos/choices')->assertOk()
            ->assertJsonPath('data.registers', [['id' => $this->register, 'name' => 'Front Counter', 'code' => 'R1']])
            ->assertJsonPath('data.cashiers', [['id' => $this->user->id, 'name' => $this->user->name]]);

        $this->report('pos-sales-summary', ['register_id' => $this->register, 'cashier_id' => $this->user->id])->assertOk()
            ->assertJsonPath('data.totals.net', '33.50')
            ->assertJsonPath('data.period', "19 Nov 2026 - 20 Nov 2026 · Front Counter (R1) · {$this->user->name}");

        $this->report('pos-sales-summary', ['register_id' => 999])->assertUnprocessable()->assertJsonValidationErrors(['register_id' => 'Choose one of your registers.']);
        $this->report('pos-sales-summary', ['cashier_id' => 999])->assertUnprocessable()->assertJsonValidationErrors(['cashier_id' => 'Choose one of your cashiers.']);
    }

    public function test_they_need_view_pos_reports_and_the_orders(): void
    {
        $catalog = collect($this->getJson('/api/v1/reports')->assertOk()->json('data'))->where('category', 'Point of Sale');
        $this->assertSame([
            'POS Sales Summary', 'Sales by Location', 'Sales by Register', 'Sales by Cashier', 'Sales by Salesperson', 'Sales by Item', 'Sales by Category', 'Sales by Brand',
            'Sales by Hour', 'Sales by Day', 'Sales by Payment Method', 'Average Order Value',
        ], $catalog->pluck('name')->take(12)->values()->all());
        $this->assertContains('POS Sales Summary', array_column(ReportCatalog::namesFor('pos'), 'name'));
        $this->assertContains('pos.reports.view', Permissions::forTemplate('manager', ['pos']));

        $member = function (array $permissions): void {
            $user = User::factory()->create();
            $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => Permissions::expand($permissions)]);
            $this->business->members()->create(['user_id' => $user->id, 'name' => $user->name, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);
            Sanctum::actingAs($user);
        };

        // Reports' View and POS's orders, without View POS Reports: none of them.
        $member(['reports.view', 'pos.orders.view']);
        $this->assertSame([], collect($this->getJson('/api/v1/reports')->assertOk()->json('data'))->where('category', 'Point of Sale')->all());
        $this->report('pos-sales-summary')->assertNotFound();
        $this->getJson('/api/v1/reports/pos/choices')->assertForbidden();

        // View POS Reports alone brings Reports' View, but not the orders it counts.
        $member(['pos.reports.view']);
        $this->assertSame([], collect($this->getJson('/api/v1/reports')->assertOk()->json('data'))->where('category', 'Point of Sale')->all());
        $this->getJson('/api/v1/reports/pos/choices')->assertOk();

        $member(['pos.reports.view', 'pos.orders.view']);
        $this->report('pos-sales-summary')->assertOk()->assertJsonPath('data.totals.net', '33.50');
    }
}
