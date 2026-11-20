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
use App\Support\Permissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 18 Phase G2: POS today (sections 18.3 and 18.35), on its Overview and
 * the Dashboard - at Front Counter (R1) in Dhaka, taking Cash into the Shop
 * Till and Card into City Bank: two mugs at 15.00 for cash, a tea at 3.50 by
 * card, a mug brought back in cash, and yesterday's sale left out.
 */
class PosOverviewTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private Contact $walkIn;

    private Item $mug;

    private Item $tea;

    private int $cash;

    private int $card;

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
        $dhaka = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka', 'code' => 'DHK']);
        $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Shop Till', 'currency' => 'USD']);
        $bank = $this->business->paymentAccounts()->create(['type' => 'bank', 'name' => 'City Bank', 'currency' => 'USD']);

        $this->postJson('/api/v1/apps/pos/activate')->assertOk();
        $this->cash = (int) $this->getJson('/api/v1/pos/registers/options')->assertOk()->json('data.payment_methods.0.id');
        $this->card = (int) $this->postJson('/api/v1/business-settings/pos/payment-methods', ['name' => 'Card', 'type' => 'card', 'payment_account_id' => $bank->id])->assertCreated()->json('data.id');

        $this->walkIn = $this->business->contacts()->create(['contact_type' => 'individual', 'role' => 'customer', 'name' => 'Walk-in Customer']);
        $this->business->posSetting()->firstOrFail()->update(['walk_in_contact_id' => $this->walkIn->id]);
        $this->mug = $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Mug', 'unit' => 'Piece', 'sale_price' => '15.00']);
        $this->tea = $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Black Tea', 'unit' => 'Piece', 'sale_price' => '3.50']);

        $this->register = $this->postJson('/api/v1/pos/registers', [
            'name' => 'Front Counter', 'code' => 'R1', 'location_id' => $dhaka->id, 'currency' => 'USD', 'payment_method_ids' => [$this->cash, $this->card], 'cashier_ids' => [$this->user->id],
        ])->assertCreated()->json('data.id');

        // Yesterday: a sale, and its session closed with the cash it should hold - neither is today's.
        $yesterday = $this->postJson('/api/v1/pos/sessions', ['pos_register_id' => $this->register, 'opening_float' => '50'])->assertCreated()->json('data.id');
        $this->sell($this->mug, '1', '15.00', $this->cash);
        $this->postJson("/api/v1/pos/sessions/{$yesterday}/close", ['counted_cash' => '65'])->assertOk();

        // Today.
        $this->travelTo(Carbon::parse('2026-11-20 10:00:00', 'UTC'));
        $today = $this->postJson('/api/v1/pos/sessions', ['pos_register_id' => $this->register, 'opening_float' => '50'])->assertCreated()->json('data.id');
        $lines = $this->sell($this->mug, '2', '30.00', $this->cash);
        $this->sell($this->tea, '1', '3.50', $this->card);
        $this->postJson('/api/v1/pos/returns', ['order_id' => 2, 'lines' => [['line_id' => $lines, 'quantity' => '1', 'condition' => 'resellable']], 'reason' => 'Chipped', 'refund' => 'cash'])->assertCreated();
        $this->assertNotNull($today);
    }

    /** $quantity of $item for $total, by the method $method: its first line's id. */
    private function sell(Item $item, string $quantity, string $total, int $method): int
    {
        $this->postJson('/api/v1/pos/orders', [
            'contact_id' => $this->walkIn->id, 'lines' => [['item_id' => $item->id, 'quantity' => $quantity]], 'total' => $total, 'payments' => [['method_id' => $method, 'amount' => $total]],
        ])->assertCreated();

        return (int) \App\Models\User\SalesDocument::latest('id')->firstOrFail()->lines->first()->id;
    }

    public function test_the_overview_shows_today_at_the_registers(): void
    {
        $today = $this->getJson('/api/v1/pos/overview')->assertOk()->json('data');

        $this->assertSame(['2026-11-20', 'USD'], [$today['date'], $today['currency']]);
        $this->assertSame(['count' => 2, 'total' => '33.50', 'average' => '16.75'], $today['sales']);
        $this->assertSame([['method' => 'Cash', 'amount' => '30.00'], ['method' => 'Card', 'amount' => '3.50']], $today['payments']);
        $this->assertSame(['count' => 1, 'credited' => '15.00', 'refunded' => '15.00'], $today['returns']);
        $this->assertSame([['name' => 'Mug', 'quantity' => '2.000', 'amount' => '30.00'], ['name' => 'Black Tea', 'quantity' => '1.000', 'amount' => '3.50']], $today['top_items']);
        $this->assertSame(['S-00002'], array_column($today['open_sessions'], 'number'));
        $this->assertSame([], $today['variances']);

        // A session closed off today is a variance.
        $session = $today['open_sessions'][0]['id'];
        $this->postJson("/api/v1/pos/sessions/{$session}/close", ['counted_cash' => '60', 'variance_reason' => 'A note short'])->assertOk();
        $closed = $this->getJson('/api/v1/pos/overview')->assertOk()->json('data');
        $this->assertSame([['session' => ['id' => $session, 'number' => 'S-00002'], 'register' => 'Front Counter (R1)', 'currency' => 'USD', 'variance' => '-5.00']], $closed['variances']);
        $this->assertSame([], $closed['open_sessions']);
    }

    public function test_the_dashboard_shows_it_for_whoever_may_view_pos_orders(): void
    {
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.pos.sales.total', '33.50')->assertJsonPath('data.pos.date', '2026-11-20');

        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Sellers', 'permissions' => Permissions::expand(['dashboard.view', 'pos.selling.sell'])]);
        $this->business->members()->create(['user_id' => $user->id, 'name' => $user->name, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.pos', null);
        $this->getJson('/api/v1/pos/overview')->assertForbidden();
    }
}
