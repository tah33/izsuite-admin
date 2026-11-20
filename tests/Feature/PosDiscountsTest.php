<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\Contact;
use App\Models\User\Item;
use App\Models\User\Location;
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
 * Doc 18 Phase C3: discounts, price overrides and cash rounding (sections
 * 18.12 to 18.14 and 18.32) - at Front Counter (R1), taking Cash and Card,
 * with Settings → Point of Sale's discount limit at 10% and cash rounding to
 * 0.05. Black Tea sells at 3.50, the Mug at 8.00 and Gum at 0.99.
 * Thursday 19 November 2026.
 */
class PosDiscountsTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const SELL = '/api/v1/pos/sell';

    private const ORDERS = '/api/v1/pos/orders';

    private User $user;

    private Workspace $business;

    private Location $dhaka;

    private Contact $walkIn;

    private Item $tea;

    private Item $mug;

    private Item $gum;

    private int $cash;

    private int $card;

    private array $register;

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
        $till        = $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Shop Till', 'currency' => 'USD']);
        $bank        = $this->business->paymentAccounts()->create(['type' => 'bank', 'name' => 'City Bank', 'currency' => 'USD']);

        $this->postJson('/api/v1/apps/pos/activate')->assertOk();
        $this->cash = (int) $this->getJson('/api/v1/pos/registers/options')->assertOk()->json('data.payment_methods.0.id');
        $this->card = (int) $this->postJson('/api/v1/business-settings/pos/payment-methods', ['name' => 'Card', 'type' => 'card', 'payment_account_id' => $bank->id])->assertCreated()->json('data.id');
        $this->putJson('/api/v1/business-settings/pos/selling', ['discount_limit' => '10', 'cash_rounding' => '0.05'])->assertOk();

        $this->walkIn = $this->business->contacts()->create(['contact_type' => 'individual', 'role' => 'customer', 'name' => 'Walk-in Customer']);
        $this->tea    = $this->item('Black Tea', '3.50');
        $this->mug    = $this->item('Mug', '8.00');
        $this->gum    = $this->item('Gum', '0.99');

        $this->register = $this->addRegister('Front Counter', 'R1', [$this->user->id]);
        $this->openSession($this->register);
    }

    private function item(string $name, string $price): Item
    {
        return $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => $name, 'unit' => 'Piece', 'sale_price' => $price]);
    }

    private function addRegister(string $name, string $code, array $cashierIds): array
    {
        return $this->postJson('/api/v1/pos/registers', [
            'name' => $name, 'code' => $code, 'location_id' => $this->dhaka->id, 'currency' => 'USD', 'payment_method_ids' => [$this->cash, $this->card], 'cashier_ids' => $cashierIds,
        ])->assertCreated()->json('data');
    }

    private function openSession(array $register): void
    {
        $this->postJson('/api/v1/pos/sessions', ['pos_register_id' => $register['id'], 'opening_float' => '50'])->assertCreated();
    }

    /** $lines, each [item, quantity, fields], as the cart sends them. */
    private function lines(array $lines): array
    {
        return array_map(fn (array $line) => ['item_id' => $line[0]->id, 'quantity' => $line[1], ...($line[2] ?? [])], $lines);
    }

    private function quote(array $lines, array $fields = []): TestResponse
    {
        return $this->getJson(self::SELL.'/quote?'.http_build_query(['lines' => $this->lines($lines), ...$fields]));
    }

    private function sell(array $lines, string $total, array $payments, array $fields = []): TestResponse
    {
        return $this->postJson(self::ORDERS, ['contact_id' => $this->walkIn->id, 'lines' => $this->lines($lines), 'total' => $total, 'payments' => $payments, ...$fields]);
    }

    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => Permissions::expand($permissions)]);
        $this->business->members()->create(['user_id' => $user->id, 'name' => $user->name, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    public function test_line_and_order_discounts_are_taken_before_tax_and_kept_on_the_invoice(): void
    {
        // Two teas at 10% off: 7.00 less 0.70. A mug: 8.00. Then 1.00 off the order: 13.30.
        $cart = [[$this->tea, '2', ['discount_type' => 'percent', 'discount_value' => '10']], [$this->mug, '1']];
        $quote = $this->quote($cart, ['discount_type' => 'amount', 'discount_value' => '1'])->assertOk()->json('data');

        $this->assertSame([['0.70', '6.30'], ['0.00', '8.00']], array_map(fn (array $line) => [$line['discount'], $line['amount']], $quote['lines']));
        $this->assertSame(['14.30', '1.00', '13.30', false, '10.00'], [$quote['subtotal'], $quote['discount_total'], $quote['grand_total'], $quote['beyond_limit'], $quote['limit']]);

        $this->sell($cart, '13.30', [['method_id' => $this->cash, 'amount' => '13.30']], ['discount_type' => 'amount', 'discount_value' => '1'])->assertCreated();

        $invoice = SalesDocument::with('lines')->sole();
        $this->assertSame(['amount', '1.0000', '1.00', '13.30', null], [$invoice->discount_type, (string) $invoice->discount_value, (string) $invoice->discount_total, (string) $invoice->grand_total, $invoice->discount_reason]);
        $this->assertSame(['percent', '10.0000', '0.70', '6.30'], [$invoice->lines[0]->discount_type, (string) $invoice->lines[0]->discount_value, (string) $invoice->lines[0]->discount_amount, (string) $invoice->lines[0]->amount]);

        // Nothing comes off a percentage beyond 100.
        $this->quote([[$this->tea, '1', ['discount_type' => 'percent', 'discount_value' => '120']]])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.discount_value' => 'A percentage takes up to 100 off.']);
    }

    public function test_a_discount_beyond_the_limit_needs_discount_beyond_the_limit_and_a_reason(): void
    {
        // 20% off the tea is beyond the 10% limit, as 2.00 off a 14.30 order would not be.
        $cart = [[$this->tea, '2', ['discount_type' => 'percent', 'discount_value' => '20']], [$this->mug, '1']];
        $this->assertSame([true, '13.60'], [$this->quote($cart)->json('data.beyond_limit'), $this->quote($cart)->json('data.grand_total')]);

        // A cashier who gives discounts, within the limit only.
        $cashier = $this->member(['pos.selling.sell', 'pos.selling.discount']);
        $back    = $this->addRegister('Back Counter', 'R2', [$cashier->id]);
        Sanctum::actingAs($cashier);
        $this->openSession($back);
        $this->sell($cart, '13.60', [['method_id' => $this->card, 'amount' => '13.60']], ['discount_reason' => 'Loyal customer'])->assertUnprocessable()
            ->assertJsonValidationErrors(['discount_reason' => 'This goes beyond the 10% discount limit: someone with Discount Beyond the Limit gives it - or approves it at the register.']);
        $this->sell([[$this->tea, '2', ['discount_type' => 'percent', 'discount_value' => '10']]], '6.30', [['method_id' => $this->card, 'amount' => '6.30']])->assertCreated();

        // One who may sell but not give discounts gives none.
        Sanctum::actingAs($this->user);
        $seller = $this->member(['pos.selling.sell']);
        $side   = $this->addRegister('Side Counter', 'R3', [$seller->id]);
        Sanctum::actingAs($seller);
        $this->openSession($side);
        $this->quote([[$this->tea, '1', ['discount_type' => 'amount', 'discount_value' => '0.10']]])->assertUnprocessable()->assertJsonValidationErrors(['lines.0.discount_value' => 'You may not give discounts.']);
        $this->quote([[$this->tea, '1']], ['discount_type' => 'percent', 'discount_value' => '5'])->assertUnprocessable()->assertJsonValidationErrors(['discount_value' => 'You may not give discounts.']);
        $this->assertEqualsCanonicalizing(['contacts.view', 'pos.sessions.view', 'pos.sessions.open', 'pos.selling.open', 'pos.selling.discount', 'pos.selling.beyond'], Permissions::expand(['pos.selling.beyond']));

        // The owner may go beyond it - saying why: who allowed it, and why, are kept.
        Sanctum::actingAs($this->user);
        $this->sell($cart, '13.60', [['method_id' => $this->card, 'amount' => '13.60']])->assertUnprocessable()->assertJsonValidationErrors(['discount_reason' => 'Say why the discount goes beyond the 10% limit.']);
        $this->sell($cart, '13.60', [['method_id' => $this->card, 'amount' => '13.60']], ['discount_reason' => 'Loyal customer'])->assertCreated();

        $order = PosOrder::where('number', 'R1-00001')->sole();
        $this->assertSame(['Loyal customer', $this->user->id, 'Loyal customer'], [$order->discount_reason, $order->discount_approved_by, $order->invoice->discount_reason]);
        $this->assertSame(1, ActivityLog::where('description', 'like', 'Sold R1-00001 % - its discount beyond the limit because Loyal customer')->count());

        // Without a limit, nothing is beyond it.
        $this->putJson('/api/v1/business-settings/pos/selling', ['discount_limit' => null, 'cash_rounding' => '0.05'])->assertOk();
        $this->assertFalse($this->quote($cart)->json('data.beyond_limit'));
    }

    public function test_a_price_typed_over_needs_override_prices_and_its_reason(): void
    {
        // Who may sell and give discounts, but not override prices, changes none.
        $seller = $this->member(['pos.selling.sell', 'pos.selling.discount']);
        $back   = $this->addRegister('Back Counter', 'R2', [$seller->id]);
        Sanctum::actingAs($seller);
        $this->openSession($back);
        $this->quote([[$this->tea, '1', ['price' => '3.20']]])->assertUnprocessable()->assertJsonValidationErrors(['lines.0.price' => 'You may not change prices.']);

        Sanctum::actingAs($this->user);
        $quote = $this->quote([[$this->tea, '2', ['price' => '3.20']]])->assertOk()->json('data');
        $this->assertSame(['3.20', '3.50', 'override', '6.40', false], [$quote['lines'][0]['price'], $quote['lines'][0]['standard'], $quote['lines'][0]['price_source'], $quote['grand_total'], $quote['beyond_limit']]);

        // Completed, it says why: the price it replaced and the reason are kept.
        $this->sell([[$this->tea, '2', ['price' => '3.20']]], '6.40', [['method_id' => $this->card, 'amount' => '6.40']])->assertUnprocessable()
            ->assertJsonValidationErrors(['lines.0.override_reason' => 'Say why Black Tea\'s price is changed.']);
        $this->sell([[$this->tea, '2', ['price' => '3.20', 'override_reason' => 'Dented tin']]], '6.40', [['method_id' => $this->card, 'amount' => '6.40']])->assertCreated();

        $line = PosOrder::sole()->lines()->sole();
        $this->assertSame(['override', '3.50', 'Dented tin', '3.20'], [$line->price_source, (string) $line->standard_price, $line->override_reason, (string) $line->invoiceLine->unit_price]);

        // More than 10% under the price it replaces: beyond the limit.
        $this->assertTrue($this->quote([[$this->tea, '1', ['price' => '3.00']]])->json('data.beyond_limit'));
    }

    public function test_a_cash_sale_rounds_to_the_step_apart_from_its_discounts(): void
    {
        // A tea and a gum, 4.49: by card, as it is; in cash, 4.50 - a Cash rounding line of 0.01, untaxed.
        $cart = [[$this->tea, '1'], [$this->gum, '1']];
        $this->sell($cart, '4.49', [['method_id' => $this->cash, 'amount' => '4.49']])->assertUnprocessable()->assertJsonValidationErrors(['payments' => 'USD 0.01 is still to pay.']);
        $this->sell($cart, '4.49', [['method_id' => $this->cash, 'amount' => '4.50', 'tendered' => '5.00']])->assertCreated()->assertJsonPath('data.total', '4.50')->assertJsonPath('data.change', '0.50');
        $this->sell($cart, '4.49', [['method_id' => $this->card, 'amount' => '4.49']])->assertCreated()->assertJsonPath('data.total', '4.49');

        $cash = SalesDocument::where('number', 'R1-00001')->with('lines')->sole();
        $this->assertSame(['4.50', 'Cash rounding', '0.01', null], [(string) $cash->grand_total, $cash->lines->last()->name, (string) $cash->lines->last()->amount, $cash->lines->last()->item_id]);
        $this->assertSame(['0.01', 2], [(string) PosOrder::where('number', 'R1-00001')->sole()->cash_rounding, PosOrder::where('number', 'R1-00001')->sole()->lines()->count()]);
        $this->assertSame(2, SalesDocument::where('number', 'R1-00002')->sole()->lines()->count());

        // Down to the step too, and after a discount that takes no share of the rounding: 4.49 less 10% is 4.04, then 4.05.
        $this->sell([[$this->tea, '1'], [$this->gum, '0.98']], '4.47', [['method_id' => $this->cash, 'amount' => '4.45']])->assertCreated()->assertJsonPath('data.total', '4.45');
        $this->sell($cart, '4.04', [['method_id' => $this->cash, 'amount' => '4.05']], ['discount_type' => 'percent', 'discount_value' => '10'])->assertCreated()->assertJsonPath('data.total', '4.05');
        $this->assertSame(['0.45', '4.05'], [(string) SalesDocument::where('number', 'R1-00004')->sole()->discount_total, (string) SalesDocument::where('number', 'R1-00004')->sole()->grand_total]);

        // The screen says the step; the cash expected counts what the drawer took.
        $this->assertSame('0.05', $this->getJson(self::SELL)->json('data.cash_rounding'));
    }

    public function test_settings_keep_the_discount_limit_and_the_rounding_step(): void
    {
        $this->putJson('/api/v1/business-settings/pos/selling', ['discount_limit' => '12.5', 'cash_rounding' => '0.10'])->assertOk()
            ->assertJsonPath('message', 'Selling settings saved.')
            ->assertJsonPath('data.discount_limit', '12.50')
            ->assertJsonPath('data.cash_rounding', '0.10');

        $this->putJson('/api/v1/business-settings/pos/selling', ['discount_limit' => '120', 'cash_rounding' => '0'])->assertUnprocessable()
            ->assertJsonValidationErrors(['discount_limit' => 'A limit is a percentage, up to 100.', 'cash_rounding' => 'Round to a step above 0 - or leave it empty for none.']);

        $this->assertTrue(ActivityLog::where('description', 'Updated the POS selling settings')->exists());
    }
}
