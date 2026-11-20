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
use App\Models\User\SalesDocument;
use App\Models\User\SalesPayment;
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
 * Doc 18 Phase C2: payment and the completed sale (sections 18.15 to 18.20)
 * - at Front Counter (R1) in Dhaka, taking Cash into the Shop Till and Card
 * into City Bank, its reference typed. Paid exactly - cash handed over with
 * its change - the sale is one invoice of the business, issued and paid,
 * numbered by the register's receipt run, made in Point of Sale; its
 * payments are customer payments into their accounts; and the session's
 * takings count it.
 *
 * Black Tea sells at 3.50 and the Mug at 8.00: two teas and a mug, 15.00.
 * Thursday 19 November 2026.
 */
class PosSalesTest extends TestCase
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
        $this->till  = $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Shop Till', 'currency' => 'USD']);
        $this->bank  = $this->business->paymentAccounts()->create(['type' => 'bank', 'name' => 'City Bank', 'currency' => 'USD']);

        $this->postJson('/api/v1/apps/pos/activate')->assertOk();
        $this->cash = (int) $this->getJson(self::REGISTERS.'/options')->assertOk()->json('data.payment_methods.0.id');
        $this->card = (int) $this->postJson('/api/v1/business-settings/pos/payment-methods', ['name' => 'Card', 'type' => 'card', 'payment_account_id' => $this->bank->id, 'needs_reference' => true])->assertCreated()->json('data.id');

        $this->walkIn = $this->business->contacts()->create(['contact_type' => 'individual', 'role' => 'customer', 'name' => 'Walk-in Customer']);
        $this->business->posSetting()->firstOrFail()->update(['walk_in_contact_id' => $this->walkIn->id]);

        $this->tea = $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Black Tea', 'unit' => 'Piece', 'sale_price' => '3.50']);
        $this->mug = $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Mug', 'unit' => 'Piece', 'sale_price' => '8.00']);

        $this->register = $this->addRegister('Front Counter', 'R1', [$this->user->id]);
        $this->session  = $this->openSession($this->register);
    }

    private function addRegister(string $name, string $code, array $cashierIds, array $fields = []): array
    {
        return $this->postJson(self::REGISTERS, [
            'name' => $name, 'code' => $code, 'location_id' => $this->dhaka->id, 'currency' => 'USD', 'payment_method_ids' => [$this->cash, $this->card], 'cashier_ids' => $cashierIds, ...$fields,
        ])->assertCreated()->json('data');
    }

    private function openSession(array $register): int
    {
        return $this->postJson('/api/v1/pos/sessions', ['pos_register_id' => $register['id'], 'opening_float' => '50'])->assertCreated()->json('data.id');
    }

    /** The sale - two teas and a mug, to the walk-in customer, for 15.00 - paid by $payments, with $fields besides. */
    private function sell(array $payments, array $fields = []): TestResponse
    {
        return $this->postJson(self::ORDERS, [
            'contact_id' => $this->walkIn->id,
            'lines'      => [['item_id' => $this->tea->id, 'quantity' => '2', 'note' => 'No sugar'], ['item_id' => $this->mug->id, 'quantity' => '1']],
            'note'       => 'Collect at 5',
            'total'      => '15.00',
            'payments'   => $payments,
            ...$fields,
        ]);
    }

    private function balanceOf(PaymentAccount $account): string
    {
        return number_format((float) AccountTransaction::where('payment_account_id', $account->id)->where('status', 'completed')->sum('amount'), 2, '.', '');
    }

    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => Permissions::expand($permissions)]);
        $this->business->members()->create(['user_id' => $user->id, 'name' => $user->name, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    public function test_a_cash_sale_is_one_invoice_paid_into_the_till_with_its_change(): void
    {
        $this->sell([['method_id' => $this->cash, 'amount' => '15.00', 'tendered' => '20.00']])->assertCreated()
            ->assertJsonPath('message', 'Sale R1-00001 completed.')
            ->assertJsonPath('data.number', 'R1-00001')
            ->assertJsonPath('data.total', '15.00')
            ->assertJsonPath('data.change', '5.00')
            ->assertJsonPath('data.payments', [['method' => 'Cash', 'amount' => '15.00', 'pending' => false]]);

        // One invoice, issued and paid, made in Point of Sale, at the register's location, dated and due today.
        $invoice = SalesDocument::where('number', 'R1-00001')->with(['lines', 'sourceApp'])->sole();
        $this->assertSame(
            ['invoice', 'issued', 'paid', '15.00', '15.00', '0.00', $this->walkIn->id, $this->dhaka->id, '2026-11-19', '2026-11-19', 'Collect at 5', 'pos', 'Walk-in Customer'],
            [$invoice->type->value, $invoice->status->value, $invoice->payment_status, (string) $invoice->grand_total, (string) $invoice->amount_paid, (string) $invoice->balance_due,
                $invoice->contact_id, $invoice->location_id, $invoice->document_date, $invoice->due_date, $invoice->notes, $invoice->sourceApp->family_key, $invoice->customer_snapshot['name']],
        );
        $this->assertSame(
            [['Black Tea', '2.000', '3.50', 'No sugar', '7.00'], ['Mug', '1.000', '8.00', null, '8.00']],
            $invoice->lines->map(fn ($line) => [$line->name, (string) $line->quantity, (string) $line->unit_price, $line->description, (string) $line->amount])->all(),
        );

        // Its payment: a customer receipt into the till, which now holds it.
        $payment = SalesPayment::where('sales_document_id', $invoice->id)->sole();
        $this->assertSame(['RCT-00001', 'cash', '15.00', $this->till->id, $this->dhaka->id, '2026-11-19'], [$payment->number, $payment->method, (string) $payment->amount, $payment->payment_account_id, $payment->location_id, $payment->payment_date]);
        $this->assertSame(['15.00', '0.00'], [$this->balanceOf($this->till), $this->balanceOf($this->bank)]);

        // The order: what was handed over and the change, and where each price came from.
        $order = PosOrder::with(['lines', 'payments'])->sole();
        $this->assertSame([PosOrder::COMPLETED, $this->session, $this->user->id, '20.00', '5.00'], [$order->status, $order->pos_session_id, $order->cashier_id, (string) $order->tendered, (string) $order->change_given]);
        $this->assertSame(['item', 'item'], $order->lines->pluck('price_source')->all());
        $this->assertSame([['Cash', 'cash', '15.00', '20.00', '5.00']], $order->payments->map(fn ($row) => [$row->method_name, $row->method_type, (string) $row->amount, (string) $row->tendered, (string) $row->change_given])->all());

        // The Sales app lists it as POS's; the session counts it, the cash in its drawer too.
        $this->assertTrue($this->getJson('/api/v1/sales/invoices')->assertOk()->json('data.0.pos'));
        $totals = $this->getJson("/api/v1/pos/sessions/{$this->session}")->assertOk()->json('data.totals');
        $this->assertSame([1, '15.00', '0.00', [['method' => 'Cash', 'amount' => '15.00']], '15.00', '65.00'], [$totals['sales_count'], $totals['sales_total'], $totals['tax_total'], $totals['payments'], $totals['cash_sales'], $totals['expected_cash']]);

        // Logged once, on its invoice.
        $this->assertSame(1, ActivityLog::where('description', 'Sold R1-00001 at the register "Front Counter" (R1) to Walk-in Customer, for USD 15.00: Cash 15.00')->count());
        $this->assertSame(0, ActivityLog::where('description', 'like', 'Recorded payment%')->orWhere('description', 'like', 'POST api/v1/pos/orders%')->count());

        // The next sale is the register's next receipt.
        $this->sell([['method_id' => $this->cash, 'amount' => '15.00']])->assertCreated()->assertJsonPath('data.number', 'R1-00002')->assertJsonPath('data.change', '0.00');
    }

    public function test_a_sale_is_paid_by_card_and_cash_together_its_taxes_counted(): void
    {
        $this->business->forceFill(['tax_mode' => 'enabled'])->save();
        $this->business->taxRates()->create(['name' => 'VAT', 'code' => 'VAT15', 'rate' => '15', 'treatment' => 'standard', 'for_sales' => true, 'effective_from' => '2026-01-01'])
            ->forceFill(['default_for_sales' => true])->save();

        // 15.00 and VAT 2.25: 10.00 by card, the rest in cash from a 10.00 note.
        $this->sell([
            ['method_id' => $this->card, 'amount' => '10.00', 'reference' => '4321'],
            ['method_id' => $this->cash, 'amount' => '7.25', 'tendered' => '10.00'],
        ], ['total' => '17.25'])->assertCreated()->assertJsonPath('data.change', '2.75')->assertJsonPath('data.payments.0', ['method' => 'Card', 'amount' => '10.00', 'pending' => false]);

        $invoice = SalesDocument::where('number', 'R1-00001')->sole();
        $this->assertSame(['2.25', '17.25', 'paid'], [(string) $invoice->tax_total, (string) $invoice->grand_total, $invoice->payment_status]);
        $this->assertSame(
            [['RCT-00001', 'card', '4321', '10.00', $this->bank->id], ['RCT-00002', 'cash', null, '7.25', $this->till->id]],
            SalesPayment::orderBy('id')->get()->map(fn ($row) => [$row->number, $row->method, $row->reference, (string) $row->amount, $row->payment_account_id])->all(),
        );

        $totals = $this->getJson("/api/v1/pos/sessions/{$this->session}")->json('data.totals');
        $this->assertSame(['17.25', '2.25', [['method' => 'Card', 'amount' => '10.00'], ['method' => 'Cash', 'amount' => '7.25']], '7.25', '57.25'], [$totals['sales_total'], $totals['tax_total'], $totals['payments'], $totals['cash_sales'], $totals['expected_cash']]);
    }

    public function test_a_registers_own_cash_account_takes_its_cash(): void
    {
        // The register's drawer is an account of its own: its cash method's money goes there, not into the method's.
        $drawer = $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Front Drawer', 'currency' => 'USD']);
        $this->putJson(self::REGISTERS."/{$this->register['id']}", [
            'name' => 'Front Counter', 'code' => 'R1', 'location_id' => $this->dhaka->id, 'currency' => 'USD', 'cash_account_id' => $drawer->id,
            'payment_method_ids' => [$this->cash, $this->card], 'cashier_ids' => [$this->user->id],
        ])->assertOk();

        $this->sell([['method_id' => $this->cash, 'amount' => '15.00']])->assertCreated();
        $this->assertSame(['15.00', '0.00'], [$this->balanceOf($drawer), $this->balanceOf($this->till)]);
    }

    public function test_the_payments_pay_exactly_the_total_by_the_registers_methods(): void
    {
        $this->sell([['method_id' => $this->cash, 'amount' => '10.00']])->assertUnprocessable()->assertJsonValidationErrors(['payments' => 'USD 5.00 is still to pay.']);
        $this->sell([['method_id' => $this->card, 'amount' => '20.00', 'reference' => '1']])->assertUnprocessable()
            ->assertJsonValidationErrors(['payments' => 'The payments come to more than the total of USD 15.00: only cash gives change.']);
        $this->sell([['method_id' => $this->card, 'amount' => '15.00', 'tendered' => '20.00', 'reference' => '1']])->assertUnprocessable()
            ->assertJsonValidationErrors(['payments.0.tendered' => 'Card gives no change: it takes the amount it pays.']);
        $this->sell([['method_id' => $this->cash, 'amount' => '15.00', 'tendered' => '10.00']])->assertUnprocessable()
            ->assertJsonValidationErrors(['payments.0.tendered' => 'What was handed over is less than the amount it pays.']);
        $this->sell([['method_id' => $this->card, 'amount' => '15.00']])->assertUnprocessable()->assertJsonValidationErrors(['payments.0.reference' => 'Type Card\'s reference.']);

        // A method the register does not take, or one switched off.
        $wallet = $this->postJson('/api/v1/business-settings/pos/payment-methods', ['name' => 'Wallet', 'type' => 'mobile_money', 'payment_account_id' => $this->bank->id])->assertCreated()->json('data.id');
        $this->sell([['method_id' => $wallet, 'amount' => '15.00']])->assertUnprocessable()->assertJsonValidationErrors(['payments.0.method_id' => 'Choose one of the payment methods this register takes.']);
        $this->putJson("/api/v1/business-settings/pos/payment-methods/{$this->card}", ['name' => 'Card', 'type' => 'card', 'payment_account_id' => $this->bank->id, 'needs_reference' => true, 'is_active' => false])->assertOk();
        $this->sell([['method_id' => $this->card, 'amount' => '15.00', 'reference' => '1']])->assertUnprocessable()->assertJsonValidationErrors('payments.0.method_id');

        // The total changed since the payment was taken: say what it is now.
        $this->tea->update(['sale_price' => '4.00']);
        $this->sell([['method_id' => $this->cash, 'amount' => '15.00']])->assertStatus(409)->assertJsonPath('message', 'The total is now USD 16.00: check the cart, and take the payment again.');

        // An item without a price is not sold; a sale is made to a customer.
        $this->mug->update(['sale_price' => null]);
        $this->sell([['method_id' => $this->cash, 'amount' => '8.00']], ['total' => '8.00'])->assertUnprocessable()->assertJsonValidationErrors(['lines.1.item_id' => 'Mug has no price: set one on the item, or take it out of the sale.']);
        $this->sell([['method_id' => $this->cash, 'amount' => '15.00']], ['contact_id' => null])->assertUnprocessable()
            ->assertJsonValidationErrors(['contact_id' => 'Choose the customer: a sale is made to one. Settings → Point of Sale can name a walk-in customer.']);

        // Refused, nothing was recorded.
        $this->assertSame([0, 0, 0], [SalesDocument::count(), SalesPayment::count(), PosOrder::count()]);
    }

    public function test_selling_needs_sell_items_in_an_open_session_of_ones_own(): void
    {
        // Sell Items brings Open POS - and what it brings.
        $this->assertEqualsCanonicalizing(['contacts.view', 'pos.sessions.view', 'pos.sessions.open', 'pos.selling.open', 'pos.selling.sell'], Permissions::expand(['pos.selling.sell']));

        $browser = $this->member(['pos.selling.open']);
        $seller  = $this->member(['pos.selling.sell']);
        $back    = $this->addRegister('Back Counter', 'R2', [$browser->id, $seller->id]);

        // Open POS alone does not sell.
        Sanctum::actingAs($browser);
        $this->sell([['method_id' => $this->cash, 'amount' => '15.00']])->assertForbidden();

        // A seller sells in their own session only - its register numbering its own receipts.
        Sanctum::actingAs($seller);
        $this->sell([['method_id' => $this->cash, 'amount' => '15.00']])->assertStatus(409)->assertJsonPath('message', 'Open your session first: you sell in a session of your own, at a register.');
        $theirs = $this->openSession($back);
        $this->sell([['method_id' => $this->cash, 'amount' => '15.00']])->assertCreated()->assertJsonPath('data.number', 'R2-00001');
        $this->assertSame([$theirs, $seller->id], [PosOrder::sole()->pos_session_id, PosOrder::sole()->cashier_id]);

        // Closed, the session sells no more - and its Z report keeps what it sold.
        $this->postJson("/api/v1/pos/sessions/{$theirs}/close", ['counted_cash' => '65'])->assertOk();
        $this->sell([['method_id' => $this->cash, 'amount' => '15.00']])->assertStatus(409);
        $this->assertSame([1, '15.00', '65.00'], [
            $this->getJson("/api/v1/pos/sessions/{$theirs}")->json('data.z_report.totals.sales_count'),
            $this->getJson("/api/v1/pos/sessions/{$theirs}")->json('data.z_report.totals.cash_sales'),
            $this->getJson("/api/v1/pos/sessions/{$theirs}")->json('data.z_report.counted_cash'),
        ]);
    }

    public function test_a_receipt_number_is_never_one_an_invoice_has_and_a_repeated_request_sells_once(): void
    {
        // An invoice of the business numbered like the register's next receipt: skipped.
        $this->business->salesDocuments()->create(['type' => 'invoice', 'number' => 'R1-00001', 'status' => 'issued', 'contact_id' => $this->walkIn->id, 'document_date' => '2026-11-18', 'currency' => 'USD']);

        $key = ['Idempotency-Key' => 'pos-sale-key-0001'];
        $this->withHeaders($key)->sell([['method_id' => $this->cash, 'amount' => '15.00']])->assertCreated()->assertJsonPath('data.number', 'R1-00002');
        $this->withHeaders($key)->sell([['method_id' => $this->cash, 'amount' => '15.00']])->assertCreated()->assertHeader('Idempotent-Replayed', 'true')->assertJsonPath('data.number', 'R1-00002');

        $this->assertSame([1, '15.00'], [PosOrder::count(), $this->balanceOf($this->till)]);
    }
}
