<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\AccountTransaction;
use App\Models\User\Contact;
use App\Models\User\Item;
use App\Models\User\Location;
use App\Models\User\PosHeldOrder;
use App\Models\User\PosOrder;
use App\Models\User\SalesDocument;
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
 * Doc 18 Phase D1: holding and resuming orders (section 18.22) - at Front
 * Counter (R1) in Dhaka. A cart is held as it stands, priced then, and kept
 * - no sale, nothing in Sales, stock or the accounts - until it is resumed
 * at its register, priced again and saying what changed, or cancelled with
 * a reason, or it expires after Settings' hours.
 *
 * Black Tea sells at 3.50 and the Mug at 8.00. Thursday 19 November 2026.
 */
class PosHeldOrdersTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const HELD = '/api/v1/pos/held-orders';

    private const REGISTERS = '/api/v1/pos/registers';

    private User $user;

    private Workspace $business;

    private Location $dhaka;

    private Contact $walkIn;

    private Item $tea;

    private Item $mug;

    private int $cash;

    private array $register;

    private int $session;

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
        $this->dhaka = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka', 'code' => 'DHK']);
        $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Shop Till', 'currency' => 'USD']);

        $this->postJson('/api/v1/apps/pos/activate')->assertOk();
        $this->cash = (int) $this->getJson(self::REGISTERS.'/options')->assertOk()->json('data.payment_methods.0.id');

        $this->walkIn = $this->business->contacts()->create(['contact_type' => 'individual', 'role' => 'customer', 'name' => 'Walk-in Customer']);
        $this->business->posSetting()->firstOrFail()->update(['walk_in_contact_id' => $this->walkIn->id]);

        $this->tea = $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Black Tea', 'unit' => 'Piece', 'sale_price' => '3.50']);
        $this->mug = $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Mug', 'unit' => 'Piece', 'sale_price' => '8.00']);

        $this->register = $this->addRegister('Front Counter', 'R1', [$this->user->id]);
        $this->session  = $this->openSession($this->register);
    }

    private function addRegister(string $name, string $code, array $cashierIds): array
    {
        return $this->postJson(self::REGISTERS, [
            'name' => $name, 'code' => $code, 'location_id' => $this->dhaka->id, 'currency' => 'USD', 'payment_method_ids' => [$this->cash], 'cashier_ids' => $cashierIds,
        ])->assertCreated()->json('data');
    }

    private function openSession(array $register): int
    {
        return $this->postJson('/api/v1/pos/sessions', ['pos_register_id' => $register['id'], 'opening_float' => '50'])->assertCreated()->json('data.id');
    }

    /** Two teas, one with no sugar, and a mug - 15.00 - to $contact, the walk-in customer unless named, with $fields besides. */
    private function hold(?Contact $contact = null, array $fields = []): TestResponse
    {
        return $this->postJson(self::HELD, [
            'contact_id' => ($contact ?? $this->walkIn)->id,
            'lines'      => [['item_id' => $this->tea->id, 'quantity' => '2', 'note' => 'No sugar'], ['item_id' => $this->mug->id, 'quantity' => '1']],
            'note'       => 'Collect at 5',
            ...$fields,
        ]);
    }

    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => Permissions::expand($permissions)]);
        $this->business->members()->create(['user_id' => $user->id, 'name' => $user->name, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    public function test_a_cart_is_held_as_it_stands_and_resumed_at_its_register_priced_again(): void
    {
        $id = $this->hold()->assertCreated()
            ->assertJsonPath('message', 'Order H-00001 held.')
            ->assertJsonPath('data.number', 'H-00001')
            ->assertJsonPath('data.customer', 'Walk-in Customer')
            ->assertJsonPath('data.items', 'Black Tea, Mug')
            ->assertJsonPath('data.total', '15.00')
            ->assertJsonPath('data.cashier', $this->user->name)
            ->assertJsonPath('data.expires_at', null)
            ->json('data.id');

        // No sale: nothing in Sales, the orders or the accounts.
        $this->assertSame([0, 0, 0], [SalesDocument::count(), PosOrder::count(), AccountTransaction::count()]);
        $this->assertSame(['H-00001'], $this->getJson(self::HELD)->assertOk()->json('data.*.number'));
        $this->getJson('/api/v1/pos/sell')->assertOk()->assertJsonPath('data.held_count', 1);
        $this->assertSame(1, ActivityLog::where('description', 'Held the order H-00001 at the register "Front Counter" (R1) for Walk-in Customer: 2 items, USD 15.00')->count());

        // As resuming would load it: its lines, notes and customer - nothing changed yet.
        $this->getJson(self::HELD."/{$id}")->assertOk()
            ->assertJsonPath('data.customer', ['id' => $this->walkIn->id, 'name' => 'Walk-in Customer'])
            ->assertJsonPath('data.lines.0', ['item_id' => $this->tea->id, 'name' => 'Black Tea', 'unit' => 'Piece', 'quantity' => '2.000', 'note' => 'No sugar', 'price' => null, 'override_reason' => null, 'discount_type' => null, 'discount_value' => null])
            ->assertJsonPath('data.note', 'Collect at 5')
            ->assertJsonPath('data.changes', [])
            ->assertJsonPath('data.total', '15.00');

        // The mug's price changed since: said before it is resumed.
        $this->mug->update(['sale_price' => '9.00']);
        $this->getJson(self::HELD."/{$id}")->assertOk()
            ->assertJsonPath('data.changes', ['Mug: USD 8.00 when it was held, USD 9.00 now.'])
            ->assertJsonPath('data.held_total', '15.00')
            ->assertJsonPath('data.total', '16.00');

        $this->postJson(self::HELD."/{$id}/resume")->assertOk()
            ->assertJsonPath('message', 'Order H-00001 resumed.')
            ->assertJsonPath('data.total', '16.00');

        $this->assertSame(PosHeldOrder::RESUMED, PosHeldOrder::findOrFail($id)->status);
        $this->assertSame([], $this->getJson(self::HELD)->assertOk()->json('data'));
        $this->getJson('/api/v1/pos/sell')->assertOk()->assertJsonPath('data.held_count', 0);
        $this->postJson(self::HELD."/{$id}/resume")->assertConflict()->assertJsonPath('message', 'H-00001 was resumed already.');
        $this->assertSame(1, ActivityLog::where('description', 'Resumed the order H-00001 held at the register "Front Counter" (R1)')->count());
    }

    public function test_resuming_leaves_out_what_can_no_longer_be_sold_or_given(): void
    {
        $rahman = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Rahman Traders']);
        $id     = $this->postJson(self::HELD, [
            'contact_id'     => $rahman->id,
            'lines'          => [
                ['item_id' => $this->tea->id, 'quantity' => '2', 'price' => '3.00', 'override_reason' => 'Regular'],
                ['item_id' => $this->mug->id, 'quantity' => '1', 'discount_type' => 'percent', 'discount_value' => '10'],
            ],
            'discount_type'  => 'amount',
            'discount_value' => '1',
        ])->assertCreated()->assertJsonPath('data.total', '12.20')->json('data.id');

        // Handed over: the owner's session closed, a cashier who may hold and resume - but not give discounts or change prices - opens theirs.
        $this->postJson("/api/v1/pos/sessions/{$this->session}/close", ['counted_cash' => '50'])->assertOk();
        $cashier = $this->member(['pos.selling.hold']);
        $this->putJson(self::REGISTERS."/{$this->register['id']}", [
            'name' => 'Front Counter', 'code' => 'R1', 'location_id' => $this->dhaka->id, 'currency' => 'USD', 'payment_method_ids' => [$this->cash], 'cashier_ids' => [$cashier->id],
        ])->assertOk();
        $rahman->update(['is_active' => false]);
        $this->tea->update(['sale_price' => '3.20']);
        Sanctum::actingAs($cashier);
        $this->openSession($this->register);

        $this->getJson(self::HELD."/{$id}")->assertOk()
            ->assertJsonPath('data.customer', null)
            ->assertJsonPath('data.lines.0.price', null)
            ->assertJsonPath('data.lines.1.discount_type', null)
            ->assertJsonPath('data.discount', null)
            ->assertJsonPath('data.changes', [
                'Its customer can no longer be sold to: the sale is back to the walk-in customer.',
                'The price typed over Black Tea needs Override Prices: it is left out.',
                'Mug\'s discount needs Apply Discounts: it is left out.',
                'The sale\'s discount needs Apply Discounts: it is left out.',
                'Black Tea: USD 3.00 when it was held, USD 3.20 now.',
            ])
            ->assertJsonPath('data.total', '14.40');
    }

    public function test_an_item_no_longer_sold_is_left_out_and_nothing_left_is_not_resumed(): void
    {
        $id = $this->hold()->assertCreated()->json('data.id');

        $this->mug->update(['is_active' => false]);
        $this->getJson(self::HELD."/{$id}")->assertOk()
            ->assertJsonPath('data.changes', ['Mug is no longer sold: it is left out.'])
            ->assertJsonPath('data.lines.*.name', ['Black Tea'])
            ->assertJsonPath('data.total', '7.00');

        $this->tea->update(['is_active' => false]);
        $this->postJson(self::HELD."/{$id}/resume")->assertConflict()->assertJsonPath('message', "None of H-00001's items is sold any longer: there is nothing to resume.");
        $this->assertSame(PosHeldOrder::HELD, PosHeldOrder::findOrFail($id)->status);
    }

    public function test_cancelling_one_needs_cancel_held_orders_and_a_reason(): void
    {
        $id = $this->hold()->assertCreated()->json('data.id');

        Sanctum::actingAs($this->member(['pos.selling.hold']));
        $this->postJson(self::HELD."/{$id}/cancel", ['reason' => 'Customer left'])->assertForbidden();

        Sanctum::actingAs($this->user);
        $this->postJson(self::HELD."/{$id}/cancel", [])->assertUnprocessable()->assertJsonPath('errors.reason.0', 'Say why it is cancelled.');
        $this->postJson(self::HELD."/{$id}/cancel", ['reason' => 'Customer left'])->assertOk()->assertJsonPath('message', 'Order H-00001 cancelled.');

        $held = PosHeldOrder::findOrFail($id);
        $this->assertSame([PosHeldOrder::CANCELLED, 'Customer left', $this->user->id], [$held->status, $held->cancel_reason, $held->closed_by]);
        $this->assertSame(1, ActivityLog::where('description', 'Cancelled the order H-00001 held at the register "Front Counter" (R1): Customer left')->count());
        $this->postJson(self::HELD."/{$id}/resume")->assertConflict()->assertJsonPath('message', 'H-00001 was cancelled.');
    }

    public function test_a_held_order_expires_after_settings_hours(): void
    {
        $this->putJson('/api/v1/business-settings/pos/selling', ['hold_hours' => 0])->assertUnprocessable()->assertJsonValidationErrors('hold_hours');
        $this->putJson('/api/v1/business-settings/pos/selling', ['hold_hours' => 2])->assertOk()->assertJsonPath('data.hold_hours', 2);

        $id = $this->hold()->assertCreated()->assertJsonPath('data.expires_at', '2026-11-19T12:00:00+00:00')->json('data.id');

        // Past its time it waits no longer - before the scheduler comes round, too.
        $this->travel(3)->hours();
        $this->assertSame([], $this->getJson(self::HELD)->assertOk()->json('data'));
        $this->getJson(self::HELD."/{$id}")->assertConflict()->assertJsonPath('message', 'H-00001 expired: it can no longer be resumed.');

        // The scheduler: nobody signed in.
        $this->app['auth']->forgetGuards();
        $this->artisan('pos:expire-held-orders')->expectsOutput('Expired 1 held order.')->assertSuccessful();
        $this->assertSame(PosHeldOrder::EXPIRED, PosHeldOrder::findOrFail($id)->status);
        $this->assertSame(1, ActivityLog::where('description', 'The order H-00001 held at the register "Front Counter" (R1) expired')->whereNull('user_id')->count());
        $this->artisan('pos:expire-held-orders')->expectsOutput('Expired 0 held orders.');
    }

    public function test_holding_needs_its_permission_and_resuming_the_register_it_was_held_at(): void
    {
        Sanctum::actingAs($this->member(['pos.selling.sell']));
        $this->hold()->assertForbidden();

        // Held at Back Counter (R2): it is resumed there, not at Front Counter.
        $cashier = $this->member(['pos.selling.hold']);
        Sanctum::actingAs($this->user);
        $back = $this->addRegister('Back Counter', 'R2', [$cashier->id]);
        Sanctum::actingAs($cashier);
        $this->openSession($back);
        $theirs = $this->hold()->assertCreated()->json('data.id');

        Sanctum::actingAs($this->user);
        $this->assertSame([], $this->getJson(self::HELD)->assertOk()->json('data'));
        $this->getJson(self::HELD."/{$theirs}")->assertConflict()->assertJsonPath('message', 'H-00001 is held at the register "Back Counter": it is resumed there.');
        $this->getJson(self::HELD.'/99999')->assertNotFound()->assertJsonPath('message', 'There is no such held order.');

        // An item no longer sold is not held either.
        $this->mug->update(['is_active' => false]);
        $this->hold()->assertUnprocessable()->assertJsonPath('errors', ['lines.1.item_id' => ['Mug is no longer sold: take it out of the sale.']]);
    }
}
