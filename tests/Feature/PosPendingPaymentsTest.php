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
use App\Models\User\PaymentAccount;
use App\Models\User\PosOrder;
use App\Models\User\PosOrderPayment;
use App\Models\User\SalesDocument;
use App\Models\User\SalesPayment;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Support\Permissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 18 Phase D2: pending payments (section 18.16) - at Front Counter (R1)
 * in Dhaka, taking Cash into the Shop Till and Card into City Bank, a card
 * payment waiting to be confirmed. Pending, it is the order's but no
 * customer payment: nothing in the bank, the invoice owing it, the session
 * counting it apart. Confirmed - once, by whoever may - it becomes one;
 * declined, the invoice still owes it.
 *
 * Two teas at 3.50 and a mug at 8.00: 15.00. Thursday 19 November 2026.
 */
class PosPendingPaymentsTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const ORDERS = '/api/v1/pos/orders';

    private const REGISTERS = '/api/v1/pos/registers';

    private User $user;

    private Workspace $business;

    private Location $dhaka;

    private Contact $walkIn;

    private Item $tea;

    private Item $mug;

    private PaymentAccount $till;

    private PaymentAccount $bank;

    private int $cash;

    private int $card;

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
        $this->till  = $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Shop Till', 'currency' => 'USD']);
        $this->bank  = $this->business->paymentAccounts()->create(['type' => 'bank', 'name' => 'City Bank', 'currency' => 'USD']);

        $this->postJson('/api/v1/apps/pos/activate')->assertOk();
        $this->cash = (int) $this->getJson(self::REGISTERS.'/options')->assertOk()->json('data.payment_methods.0.id');
        $this->card = (int) $this->postJson('/api/v1/business-settings/pos/payment-methods', [
            'name' => 'Card', 'type' => 'card', 'payment_account_id' => $this->bank->id, 'needs_reference' => true, 'confirms_later' => true,
        ])->assertCreated()->assertJsonPath('data.confirms_later', true)->json('data.id');

        $this->walkIn = $this->business->contacts()->create(['contact_type' => 'individual', 'role' => 'customer', 'name' => 'Walk-in Customer']);
        $this->business->posSetting()->firstOrFail()->update(['walk_in_contact_id' => $this->walkIn->id]);

        $this->tea = $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Black Tea', 'unit' => 'Piece', 'sale_price' => '3.50']);
        $this->mug = $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Mug', 'unit' => 'Piece', 'sale_price' => '8.00']);

        $register = $this->postJson(self::REGISTERS, [
            'name' => 'Front Counter', 'code' => 'R1', 'location_id' => $this->dhaka->id, 'currency' => 'USD', 'payment_method_ids' => [$this->cash, $this->card], 'cashier_ids' => [$this->user->id],
        ])->assertCreated()->json('data');
        $this->session = $this->postJson('/api/v1/pos/sessions', ['pos_register_id' => $register['id'], 'opening_float' => '50'])->assertCreated()->json('data.id');
    }

    /** Two teas and a mug, 15.00, paid by $payments. */
    private function sell(array $payments): TestResponse
    {
        return $this->postJson(self::ORDERS, [
            'contact_id' => $this->walkIn->id,
            'lines'      => [['item_id' => $this->tea->id, 'quantity' => '2'], ['item_id' => $this->mug->id, 'quantity' => '1']],
            'total'      => '15.00',
            'payments'   => $payments,
        ]);
    }

    private function balanceOf(PaymentAccount $account): string
    {
        return number_format((float) AccountTransaction::where('payment_account_id', $account->id)->where('status', 'completed')->sum('amount'), 2, '.', '');
    }

    private function totals(): array
    {
        return $this->getJson("/api/v1/pos/sessions/{$this->session}")->assertOk()->json('data.totals');
    }

    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => Permissions::expand($permissions)]);
        $this->business->members()->create(['user_id' => $user->id, 'name' => $user->name, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    public function test_a_card_payment_waits_until_confirmed_and_then_pays_the_invoice(): void
    {
        $this->sell([['method_id' => $this->card, 'amount' => '10.00', 'reference' => 'AUTH-1'], ['method_id' => $this->cash, 'amount' => '5.00']])->assertCreated()
            ->assertJsonPath('data.payments', [['method' => 'Card', 'amount' => '10.00', 'pending' => true], ['method' => 'Cash', 'amount' => '5.00', 'pending' => false]]);

        // Pending: the order's, but no customer payment - the bank holds nothing, the invoice owes it, the session counts it apart.
        $invoice = SalesDocument::where('number', 'R1-00001')->sole();
        $this->assertSame(['5.00', '10.00'], [(string) $invoice->amount_paid, (string) $invoice->balance_due]);
        $this->assertSame(1, SalesPayment::count());
        $this->assertSame(['5.00', '0.00'], [$this->balanceOf($this->till), $this->balanceOf($this->bank)]);
        $this->assertSame([[['method' => 'Cash', 'amount' => '5.00']], '10.00', '5.00'], [$this->totals()['payments'], $this->totals()['pending'], $this->totals()['cash_sales']]);
        $this->getJson('/api/v1/pos/sell')->assertOk()->assertJsonPath('data.pending_count', 1);
        $this->assertSame(1, ActivityLog::where('description', 'Sold R1-00001 at the register "Front Counter" (R1) to Walk-in Customer, for USD 15.00: Card 10.00 (pending), Cash 5.00')->count());

        // Its receipt says so.
        Http::preventStrayRequests();
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])]);
        $this->get(self::ORDERS.'/'.PosOrder::where('sales_document_id', $invoice->id)->value('id').'/receipt', ['Accept' => 'application/json'])->assertOk();
        /** @var Request $request */
        [$request] = Http::recorded()->last();
        $this->assertStringContainsString('Card · AUTH-1 (pending)', (string) collect($request->data())->firstWhere('filename', 'index.html')['contents']);

        $pending = $this->getJson("/api/v1/pos/sessions/{$this->session}/pending-payments")->assertOk()->json('data');
        $this->assertSame([['R1-00001', 'Card', '10.00', 'USD', 'AUTH-1', 'pending']], array_map(fn (array $row) => [$row['order'], $row['method'], $row['amount'], $row['currency'], $row['reference'], $row['status']], $pending));

        // Confirmed: the customer payment it becomes, into the bank, paying the invoice - once.
        $this->postJson("/api/v1/pos/order-payments/{$pending[0]['id']}/confirm")->assertOk()
            ->assertJsonPath('message', 'The Card payment for R1-00001 is confirmed.')
            ->assertJsonPath('data.status', 'completed');

        $received = SalesPayment::where('payment_account_id', $this->bank->id)->sole();
        $this->assertSame(['RCT-00002', 'card', 'AUTH-1', '10.00', '2026-11-19', '0.00'], [$received->number, $received->method, $received->reference, (string) $received->amount, $received->payment_date, (string) $received->balance_after]);
        $this->assertSame(['paid', '0.00'], [$invoice->fresh()->payment_status, (string) $invoice->fresh()->balance_due]);
        $this->assertSame('10.00', $this->balanceOf($this->bank));
        $this->assertSame([[['method' => 'Card', 'amount' => '10.00'], ['method' => 'Cash', 'amount' => '5.00']], '0.00'], [$this->totals()['payments'], $this->totals()['pending']]);
        $this->assertSame(1, ActivityLog::where('description', 'Confirmed the Card payment of USD 10.00 for R1-00001')->count());

        $this->postJson("/api/v1/pos/order-payments/{$pending[0]['id']}/confirm")->assertConflict()->assertJsonPath('message', 'This Card payment was confirmed already.');
        $this->assertSame([], $this->getJson("/api/v1/pos/sessions/{$this->session}/pending-payments")->assertOk()->json('data'));
    }

    public function test_a_declined_payment_leaves_the_invoice_owing_it(): void
    {
        $this->sell([['method_id' => $this->card, 'amount' => '15.00', 'reference' => 'AUTH-2']])->assertCreated();
        $id = PosOrderPayment::sole()->id;

        $this->postJson("/api/v1/pos/order-payments/{$id}/decline", [])->assertUnprocessable()->assertJsonPath('errors.reason.0', 'Say why it is declined.');
        $this->postJson("/api/v1/pos/order-payments/{$id}/decline", ['reason' => 'The bank refused it'])->assertOk()
            ->assertJsonPath('message', 'The Card payment for R1-00001 is declined: its invoice still owes it.')
            ->assertJsonPath('data.status', 'declined');

        $invoice = SalesDocument::where('number', 'R1-00001')->sole();
        $this->assertSame(['unpaid', '15.00'], [$invoice->payment_status, (string) $invoice->balance_due]);
        $this->assertSame([0, '0.00'], [SalesPayment::count(), $this->balanceOf($this->bank)]);
        $this->assertSame([[], '0.00'], [$this->totals()['payments'], $this->totals()['pending']]);
        $this->assertSame(1, ActivityLog::where('description', 'Declined the Card payment of USD 15.00 for R1-00001: The bank refused it')->count());
        $this->postJson("/api/v1/pos/order-payments/{$id}/confirm")->assertConflict()->assertJsonPath('message', 'This Card payment was declined.');
    }

    public function test_confirming_needs_its_permission_and_cash_never_waits(): void
    {
        $this->sell([['method_id' => $this->card, 'amount' => '15.00', 'reference' => 'AUTH-3']])->assertCreated();
        $id = PosOrderPayment::sole()->id;

        Sanctum::actingAs($this->member(['pos.selling.sell']));
        $this->getJson("/api/v1/pos/sessions/{$this->session}/pending-payments")->assertForbidden();
        $this->postJson("/api/v1/pos/order-payments/{$id}/confirm")->assertForbidden();

        Sanctum::actingAs($this->member(['pos.payments.confirm']));
        $this->postJson("/api/v1/pos/order-payments/{$id}/confirm")->assertOk();
        $this->postJson('/api/v1/pos/order-payments/99999/confirm')->assertNotFound()->assertJsonPath('message', 'There is no such payment.');

        Sanctum::actingAs($this->user);
        $this->putJson("/api/v1/business-settings/pos/payment-methods/{$this->cash}", ['name' => 'Cash', 'type' => 'cash', 'payment_account_id' => $this->till->id, 'gives_change' => true, 'confirms_later' => true])
            ->assertUnprocessable()->assertJsonPath('errors.confirms_later.0', 'Cash is in the drawer at once: it does not wait to be confirmed.');
    }
}
