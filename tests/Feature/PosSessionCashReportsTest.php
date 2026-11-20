<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
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
 * Doc 18 Phase H3: sessions and cash (section 18.36) - at Front Counter (R1)
 * with a variance limit of 2.00. Session S-00001: a 50.00 float, a mug at
 * 15.00 for cash, 20.00 dropped into the safe, counted 5.00 short and
 * approved by a supervisor. Session S-00002, still open: a 50.00 float and a
 * tea at 3.50 for cash.
 */
class PosSessionCashReportsTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const RANGE = ['range' => 'custom', 'date_from' => '2026-11-20', 'date_to' => '2026-11-20'];

    private User $user;

    private User $supervisor;

    private User $cashier;

    private Workspace $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);

        $this->offerSalesLite();
        Application::create(['app_key' => 'pos', 'name' => 'Point of Sale', 'family_key' => 'pos', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 30, 'is_active' => true, 'status' => 'available'])
            ->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-11-20 04:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->business->forceFill(['base_currency' => 'USD', 'tax_mode' => 'later'])->save();
        $dhaka = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka', 'code' => 'DHK']);
        $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Shop Till', 'currency' => 'USD']);
        $safe = $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Safe', 'currency' => 'USD']);

        $this->postJson('/api/v1/apps/pos/activate')->assertOk();
        $cash = (int) $this->getJson('/api/v1/pos/registers/options')->assertOk()->json('data.payment_methods.0.id');
        $this->putJson('/api/v1/business-settings/pos/cash-count', ['variance_limit' => '2', 'counts_denominations' => false])->assertOk();
        $walkIn = $this->business->contacts()->create(['contact_type' => 'individual', 'role' => 'customer', 'name' => 'Walk-in Customer']);
        $this->business->posSetting()->firstOrFail()->update(['walk_in_contact_id' => $walkIn->id]);
        $mug = $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Mug', 'unit' => 'Piece', 'sale_price' => '15.00']);
        $tea = $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Black Tea', 'unit' => 'Piece', 'sale_price' => '3.50']);

        // The cashier may sell and move cash, not approve their own variance.
        $this->cashier = $this->member(['pos.selling.sell', 'pos.cash.move']);
        Sanctum::actingAs($this->user);
        $register = $this->postJson('/api/v1/pos/registers', [
            'name' => 'Front Counter', 'code' => 'R1', 'location_id' => $dhaka->id, 'currency' => 'USD', 'payment_method_ids' => [$cash], 'cashier_ids' => [$this->cashier->id],
        ])->assertCreated()->json('data.id');
        $sell = fn (int $item, string $total) => $this->postJson('/api/v1/pos/orders', [
            'contact_id' => $walkIn->id, 'lines' => [['item_id' => $item, 'quantity' => '1']], 'total' => $total, 'payments' => [['method_id' => $cash, 'amount' => $total]],
        ])->assertCreated();

        Sanctum::actingAs($this->cashier);
        $first = $this->postJson('/api/v1/pos/sessions', ['pos_register_id' => $register, 'opening_float' => '50'])->assertCreated()->json('data.id');
        $sell($mug->id, '15.00');
        $this->postJson('/api/v1/pos/cash-movements', ['type' => 'safe_drop', 'account_id' => $safe->id, 'amount' => '20', 'reason' => 'Too much cash in the drawer'])->assertCreated();
        $this->postJson("/api/v1/pos/sessions/{$first}/close", ['counted_cash' => '40', 'variance_reason' => 'A note missing'])->assertOk()->assertJsonPath('data.status', 'closing');

        $this->supervisor = $this->member(['pos.sessions.approve']);
        $this->postJson("/api/v1/pos/sessions/{$first}/approve")->assertOk();

        $this->travelTo(Carbon::parse('2026-11-20 05:00:00', 'UTC'));
        Sanctum::actingAs($this->cashier);
        $this->postJson('/api/v1/pos/sessions', ['pos_register_id' => $register, 'opening_float' => '50'])->assertCreated();
        $sell($tea->id, '3.50');
        Sanctum::actingAs($this->user);
    }

    /** A member of the business with $permissions, signed in. */
    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => Permissions::expand($permissions)]);
        $this->business->members()->create(['user_id' => $user->id, 'name' => $user->name, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);
        Sanctum::actingAs($user);

        return $user;
    }

    private function report(string $key, array $query = []): TestResponse
    {
        return $this->getJson("/api/v1/reports/{$key}?".http_build_query([...self::RANGE, ...$query]))->assertOk();
    }

    /** Each row's $keys, in their order. */
    private function rows(TestResponse $response, array $keys): array
    {
        return array_map(fn (array $row) => array_combine($keys, array_map(fn (string $key) => $row[$key] ?? null, $keys)), $response->json('data.rows'));
    }

    public function test_the_session_summary(): void
    {
        $report = $this->report('pos-sessions');

        $this->assertSame([
            ['number' => 'S-00001', 'status' => 'Closed', 'currency' => 'USD', 'opening_float' => '50.00', 'sales' => 1, 'sales_total' => '15.00', 'expected' => '45.00', 'counted' => '40.00', 'variance' => '-5.00'],
            ['number' => 'S-00002', 'status' => 'Open', 'currency' => 'USD', 'opening_float' => '50.00', 'sales' => 1, 'sales_total' => '3.50', 'expected' => '53.50', 'counted' => null, 'variance' => null],
        ], $this->rows($report, ['number', 'status', 'currency', 'opening_float', 'sales', 'sales_total', 'expected', 'counted', 'variance']));
        $this->assertSame(['sales' => 2, 'sales_total' => '18.50', 'variance' => '-5.00'], array_intersect_key($report->json('data.totals'), array_flip(['sales', 'sales_total', 'variance'])));
    }

    public function test_cash_variances_and_movements(): void
    {
        $variances = $this->report('pos-cash-variances');
        $this->assertSame([[
            'number' => 'S-00001', 'expected' => '45.00', 'counted' => '40.00', 'variance' => '-5.00', 'kind' => 'Short', 'reason' => 'A note missing', 'approval' => "Approved by {$this->supervisor->name}",
        ]], $this->rows($variances, ['number', 'expected', 'counted', 'variance', 'kind', 'reason', 'approval']));
        $this->assertSame(['0.00', '-5.00', '-5.00'], array_column($variances->json('data.summary'), 'value'));

        $movements = $this->report('pos-cash-movements');
        $this->assertSame([[
            'number' => 'CM-00001', 'session' => 'S-00001', 'type' => 'Safe drop', 'in' => null, 'out' => '20.00', 'other' => 'Safe', 'by' => $this->cashier->name, 'status' => 'Completed',
        ]], $this->rows($movements, ['number', 'session', 'type', 'in', 'out', 'other', 'by', 'status']));
        $this->assertSame(['0.00', '20.00', '-20.00'], array_column($movements->json('data.summary'), 'value'));

        // Nothing the day after.
        $this->assertSame([], $this->report('pos-cash-variances', ['date_from' => '2026-11-21', 'date_to' => '2026-11-21'])->json('data.rows'));
    }

    public function test_each_needs_the_view_of_what_it_counts(): void
    {
        $keys = fn () => collect($this->getJson('/api/v1/reports')->assertOk()->json('data'))->where('category', 'Point of Sale')->pluck('key')->values()->all();

        $this->member(['pos.reports.view', 'pos.sessions.view']);
        $this->assertSame(['pos-sessions', 'pos-cash-variances'], $keys());

        $this->member(['pos.reports.view', 'pos.cash.view']);
        $this->assertSame(['pos-sessions', 'pos-cash-movements', 'pos-cash-variances'], $keys());
    }
}
