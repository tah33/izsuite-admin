<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\Contact;
use App\Models\User\Item;
use App\Models\User\PaymentAccount;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Support\Permissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 18 Phase G1: POS's pages of records (section 18.3) - Orders, Returns
 * and Refunds, and Cash Movements - at Front Counter (R1), the owner's, and
 * Back Counter (R2), a clerk's, both in Dhaka taking Cash into the Shop Till.
 * Each the latest first, by register, session and cashier.
 */
class PosRecordPagesTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private User $clerk;

    private Workspace $business;

    private Contact $walkIn;

    private Item $mug;

    private PaymentAccount $safe;

    private int $cash;

    private array $registers = [];

    private array $sessions = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);

        $this->offerSalesLite();
        Application::create(['app_key' => 'pos', 'name' => 'Point of Sale', 'family_key' => 'pos', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 30, 'is_active' => true, 'status' => 'available'])
            ->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-11-20 10:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->business->forceFill(['base_currency' => 'USD', 'tax_mode' => 'later'])->save();
        $dhaka = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka', 'code' => 'DHK']);
        $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Shop Till', 'currency' => 'USD']);

        $this->postJson('/api/v1/apps/pos/activate')->assertOk();
        $this->cash = (int) $this->getJson('/api/v1/pos/registers/options')->assertOk()->json('data.payment_methods.0.id');
        $this->safe = $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Safe', 'currency' => 'USD']);

        $this->walkIn = $this->business->contacts()->create(['contact_type' => 'individual', 'role' => 'customer', 'name' => 'Walk-in Customer']);
        $this->business->posSetting()->firstOrFail()->update(['walk_in_contact_id' => $this->walkIn->id]);
        $this->mug = $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Mug', 'unit' => 'Piece', 'sale_price' => '15.00']);

        $this->clerk = $this->member(['pos.selling.sell', 'pos.returns.process', 'pos.cash.move']);
        foreach ([['Front Counter', 'R1', $this->user], ['Back Counter', 'R2', $this->clerk]] as [$name, $code, $cashier]) {
            $this->registers[$code] = $this->postJson('/api/v1/pos/registers', [
                'name' => $name, 'code' => $code, 'location_id' => $dhaka->id, 'currency' => 'USD', 'payment_method_ids' => [$this->cash], 'cashier_ids' => [$cashier->id],
            ])->assertCreated()->json('data.id');
        }

        // The owner sells two mugs at R1; the clerk one at R2, then takes it back in cash and drops cash in the safe.
        $this->sessions['R1'] = $this->postJson('/api/v1/pos/sessions', ['pos_register_id' => $this->registers['R1'], 'opening_float' => '50'])->assertCreated()->json('data.id');
        $this->sell('2', '30.00');
        Sanctum::actingAs($this->clerk);
        $this->sessions['R2'] = $this->postJson('/api/v1/pos/sessions', ['pos_register_id' => $this->registers['R2'], 'opening_float' => '50'])->assertCreated()->json('data.id');
        $lines = $this->sell('1', '15.00');
        Sanctum::actingAs($this->user);
        $this->postJson('/api/v1/pos/cash-movements', ['type' => 'safe_drop', 'account_id' => $this->safe->id, 'amount' => '20', 'reason' => 'Too much cash'])->assertCreated();
        Sanctum::actingAs($this->clerk);
        $this->postJson('/api/v1/pos/returns', ['order_id' => 2, 'lines' => [['line_id' => $lines, 'quantity' => '1', 'condition' => 'damaged']], 'reason' => 'Chipped', 'refund' => 'exchange'])->assertCreated();
        $this->postJson('/api/v1/pos/cash-movements', ['type' => 'safe_drop', 'account_id' => $this->safe->id, 'amount' => '10', 'reason' => 'Lunch'])->assertCreated();
        Sanctum::actingAs($this->user);
    }

    /** A sale of $quantity mugs for $total, in cash, in the acting user's session: its line's id. */
    private function sell(string $quantity, string $total): int
    {
        $this->postJson('/api/v1/pos/orders', [
            'contact_id' => $this->walkIn->id, 'lines' => [['item_id' => $this->mug->id, 'quantity' => $quantity]], 'total' => $total, 'payments' => [['method_id' => $this->cash, 'amount' => $total]],
        ])->assertCreated();

        return (int) \App\Models\User\SalesDocument::latest('id')->firstOrFail()->lines->first()->id;
    }

    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => Permissions::expand($permissions)]);
        $this->business->members()->create(['user_id' => $user->id, 'name' => $user->name, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    public function test_the_orders_page_lists_the_sales_the_latest_first(): void
    {
        $rows = $this->getJson('/api/v1/pos/orders')->assertOk()->assertJsonPath('pagination.total', 2)->json('data');
        $this->assertSame(['R2-00001', 'R1-00001'], array_column($rows, 'number'));
        $this->assertSame([
            'register' => 'Back Counter (R2)', 'session' => ['id' => $this->sessions['R2'], 'number' => 'S-00002'], 'cashier' => $this->clerk->name, 'customer' => 'Walk-in Customer',
            'total' => '15.00', 'currency' => 'USD', 'paid_by' => ['Cash'], 'returned' => '15.00', 'invoice' => ['id' => 2, 'number' => 'R2-00001'],
        ], array_diff_key($rows[0], array_flip(['id', 'number', 'completed_at'])));
        $this->assertSame('0.00', $rows[1]['returned']);

        // By register, session, cashier, or a receipt number searched for.
        $this->assertSame(['R1-00001'], array_column($this->getJson("/api/v1/pos/orders?register_id={$this->registers['R1']}")->json('data'), 'number'));
        $this->assertSame(['R2-00001'], array_column($this->getJson("/api/v1/pos/orders?session_id={$this->sessions['R2']}")->json('data'), 'number'));
        $this->assertSame(['R2-00001'], array_column($this->getJson("/api/v1/pos/orders?cashier_id={$this->clerk->id}")->json('data'), 'number'));
        $this->assertSame(['R1-00001'], array_column($this->getJson('/api/v1/pos/orders?search=R1-')->json('data'), 'number'));
    }

    public function test_the_returns_and_cash_movements_pages_list_theirs(): void
    {
        $returns = $this->getJson('/api/v1/pos/returns')->assertOk()->json('data');
        $this->assertSame([[
            'number' => 'RT-00001', 'sale' => 'R2-00001', 'register' => 'Back Counter (R2)', 'cashier' => $this->clerk->name, 'total' => '15.00',
            'refund' => 'exchange', 'refunded' => '0.00', 'pending' => '0.00', 'status' => 'completed', 'exchange' => null,
        ]], array_map(fn (array $row) => array_intersect_key($row, array_flip(['number', 'sale', 'register', 'cashier', 'total', 'refund', 'refunded', 'pending', 'status', 'exchange'])), $returns));
        $this->assertSame([], $this->getJson('/api/v1/pos/returns?refund=cash')->json('data'));
        $this->assertCount(1, $this->getJson('/api/v1/pos/returns?search=R2-00001')->json('data'));
        $this->assertSame([], $this->getJson('/api/v1/pos/returns?status=pending')->json('data'));

        $movements = $this->getJson('/api/v1/pos/cash-movements')->assertOk()->json('data');
        $this->assertSame([['CM-00002', 'Back Counter (R2)', 'S-00002', '10.00'], ['CM-00001', 'Front Counter (R1)', 'S-00001', '20.00']],
            array_map(fn (array $row) => [$row['number'], $row['register'], $row['session']['number'], $row['amount']], $movements));
        $this->assertSame(['CM-00002'], array_column($this->getJson("/api/v1/pos/cash-movements?cashier_id={$this->clerk->id}")->json('data'), 'number'));
        $this->assertSame([], $this->getJson('/api/v1/pos/cash-movements?type=cash_in')->json('data'));

        // The Sessions page's choices name the registers' cashiers, to filter by.
        $names = [$this->clerk->name, $this->user->name];
        sort($names);
        $this->assertSame($names, array_column($this->getJson('/api/v1/pos/sessions/options')->assertOk()->json('data.cashiers'), 'name'));
    }

    public function test_each_page_is_for_whoever_may_view_it(): void
    {
        Sanctum::actingAs($this->member(['pos.selling.sell']));
        $this->getJson('/api/v1/pos/orders')->assertForbidden();
        $this->getJson('/api/v1/pos/returns')->assertForbidden();
        $this->getJson('/api/v1/pos/cash-movements')->assertForbidden();

        Sanctum::actingAs($this->member(['pos.orders.view', 'pos.returns.view', 'pos.cash.view']));
        $this->getJson('/api/v1/pos/orders')->assertOk();
        $this->getJson('/api/v1/pos/returns')->assertOk();
        $this->getJson('/api/v1/pos/cash-movements')->assertOk();
    }
}
