<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\Contact;
use App\Models\User\PosCashMovement;
use App\Models\User\PosOrder;
use App\Models\User\PosReturn;
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
 * Doc 18 Phase I2: a manager's approval at the cashier's register (section
 * 18.31). The owner, with PIN 2580, approves for a cashier who may sell and
 * give discounts, move cash and refund - but not override prices, go beyond
 * the 10% discount limit, move more than 50.00 without approval, or refund
 * by another method.
 */
class PosApprovalsTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $owner;

    private User $cashier;

    private Workspace $business;

    private Contact $walkIn;

    private int $cash;

    private int $card;

    private int $tea;

    private int $safe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);

        $this->offerSalesLite();
        Application::create(['app_key' => 'pos', 'name' => 'Point of Sale', 'family_key' => 'pos', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 30, 'is_active' => true, 'status' => 'available'])
            ->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-11-20 04:00:00', 'UTC'));
        $this->owner    = $this->signIn();
        $this->business = $this->businessOf($this->owner);
        $this->business->forceFill(['base_currency' => 'USD', 'tax_mode' => 'later'])->save();
        $dhaka = $this->business->locations()->create(['user_id' => $this->owner->id, 'name' => 'Dhaka', 'code' => 'DHK']);
        $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Shop Till', 'currency' => 'USD']);
        $bank       = $this->business->paymentAccounts()->create(['type' => 'bank', 'name' => 'City Bank', 'currency' => 'USD']);
        $this->safe = $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Safe', 'currency' => 'USD'])->id;

        $this->postJson('/api/v1/apps/pos/activate')->assertOk();
        $this->cash   = (int) $this->getJson('/api/v1/pos/registers/options')->assertOk()->json('data.payment_methods.0.id');
        $this->card   = (int) $this->postJson('/api/v1/business-settings/pos/payment-methods', ['name' => 'Card', 'type' => 'card', 'payment_account_id' => $bank->id])->assertCreated()->json('data.id');
        $this->walkIn = $this->business->contacts()->create(['contact_type' => 'individual', 'role' => 'customer', 'name' => 'Walk-in Customer']);
        $this->business->posSetting()->firstOrFail()->update(['walk_in_contact_id' => $this->walkIn->id, 'discount_limit' => '10', 'cash_movement_limit' => '50']);
        $this->tea = $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Black Tea', 'unit' => 'Piece', 'sale_price' => '10.00'])->id;
        $this->putJson('/api/v1/pos/pin', ['pin' => '2580', 'pin_confirmation' => '2580', 'password' => 'password'])->assertOk();

        $this->cashier = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Cashiers', 'permissions' => Permissions::expand(['pos.selling.sell', 'pos.selling.discount', 'pos.cash.move', 'pos.returns.refund'])]);
        $this->business->members()->create(['user_id' => $this->cashier->id, 'name' => $this->cashier->name, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        $register = $this->postJson('/api/v1/pos/registers', [
            'name' => 'Front Counter', 'code' => 'R1', 'location_id' => $dhaka->id, 'currency' => 'USD', 'payment_method_ids' => [$this->cash, $this->card], 'cashier_ids' => [$this->cashier->id],
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($this->cashier);
        $this->postJson('/api/v1/pos/sessions', ['pos_register_id' => $register, 'opening_float' => '100'])->assertCreated();
    }

    private function approve(string $action, string $pin = '2580', ?int $approver = null): TestResponse
    {
        return $this->postJson('/api/v1/pos/approvals', ['action' => $action, 'approver_id' => $approver ?? $this->owner->id, 'pin' => $pin]);
    }

    private function sell(array $line, string $total, array $with = []): TestResponse
    {
        return $this->postJson('/api/v1/pos/orders', [
            'contact_id' => $this->walkIn->id, 'lines' => [['item_id' => $this->tea, 'quantity' => '1', ...$line]], 'total' => $total, 'payments' => [['method_id' => $this->cash, 'amount' => $total]], ...$with,
        ]);
    }

    public function test_a_manager_approves_a_price_typed_over_with_their_pin(): void
    {
        $this->getJson('/api/v1/pos/approvers?action=override')->assertOk()->assertJsonPath('data', [['id' => $this->owner->id, 'name' => $this->owner->name]]);

        $this->sell(['price' => '9.50', 'override_reason' => 'Dented tin'], '9.50')->assertUnprocessable()->assertJsonValidationErrors(['lines.0.price' => 'You may not change prices.']);
        $this->approve('override', '0000')->assertUnprocessable()->assertJsonValidationErrors(['pin' => 'That PIN is not right.']);
        $this->approve('override', '2580', $this->cashier->id)->assertUnprocessable()->assertJsonValidationErrors(['approver_id']);

        $token = $this->approve('override')->assertCreated()->assertJsonPath('message', "Approved by {$this->owner->name}.")->json('data.token');
        $this->getJson('/api/v1/pos/sell/quote?'.http_build_query(['lines' => [['item_id' => $this->tea, 'quantity' => '1', 'price' => '9.50']], 'approvals' => ['override' => $token]]))->assertOk();
        $this->sell(['price' => '9.50', 'override_reason' => 'Dented tin'], '9.50', ['approvals' => ['override' => $token]])->assertCreated();

        $this->assertSame($this->owner->id, PosOrder::sole()->lines()->sole()->override_approved_by);

        // Its token approves overrides alone, at this register, for ten minutes.
        $this->sell(['discount_type' => 'percent', 'discount_value' => '20'], '8.00', ['discount_reason' => 'Regular', 'approvals' => ['beyond' => $token]])->assertUnprocessable()->assertJsonValidationErrors(['discount_reason']);
        $this->travel(11)->minutes();
        $this->sell(['price' => '9.50', 'override_reason' => 'Dented tin'], '9.50', ['approvals' => ['override' => $token]])->assertUnprocessable()->assertJsonValidationErrors(['lines.0.price']);
    }

    public function test_a_manager_approves_a_discount_beyond_the_limit_and_is_named_for_it(): void
    {
        $this->sell(['discount_type' => 'percent', 'discount_value' => '20'], '8.00', ['discount_reason' => 'Regular'])->assertUnprocessable()
            ->assertJsonValidationErrors(['discount_reason' => 'This goes beyond the 10% discount limit: someone with Discount Beyond the Limit gives it - or approves it at the register.']);

        $token = $this->approve('beyond')->assertCreated()->json('data.token');
        $this->sell(['discount_type' => 'percent', 'discount_value' => '20'], '8.00', ['discount_reason' => 'Regular', 'approvals' => ['beyond' => $token]])->assertCreated();

        $this->assertSame([$this->owner->id, 'Regular'], [PosOrder::sole()->discount_approved_by, PosOrder::sole()->discount_reason]);
    }

    public function test_a_manager_approves_cash_above_the_limit_and_a_refund_by_another_method(): void
    {
        $move = fn (array $with = []) => $this->postJson('/api/v1/pos/cash-movements', ['type' => 'safe_drop', 'account_id' => $this->safe, 'amount' => '80', 'reason' => 'Too much cash', ...$with]);

        $move()->assertCreated()->assertJsonPath('data.status', PosCashMovement::PENDING);
        $move(['approvals' => ['cash' => $this->approve('cash')->json('data.token')]])->assertCreated()->assertJsonPath('data.status', PosCashMovement::COMPLETED);
        $this->assertSame($this->owner->id, PosCashMovement::where('status', 'completed')->sole()->decided_by);

        $this->sell([], '10.00')->assertCreated();
        $line   = SalesDocument::where('number', 'R1-00001')->firstOrFail()->lines->first()->id;
        $refund = fn (array $with = []) => $this->postJson('/api/v1/pos/returns', [
            'order_id' => 1, 'lines' => [['line_id' => $line, 'quantity' => '1', 'condition' => 'resellable']], 'reason' => 'Wrong flavour', 'refund' => 'method', 'method_id' => $this->card, 'refund_reason' => 'No cash left', ...$with,
        ]);

        $refund()->assertForbidden();
        $refund(['approvals' => ['refund' => $this->approve('refund')->json('data.token')]])->assertCreated();
        $this->assertSame($this->owner->id, PosReturn::sole()->refund_approved_by);
    }
}
