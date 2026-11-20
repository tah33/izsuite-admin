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
use App\Models\User\PosReturn;
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
 * Doc 18 Phase E1: returns and their refunds (sections 18.23 and 18.24) - at
 * Front Counter (R1) in Dhaka, taking Cash into the Shop Till and Card into
 * City Bank. A return starts from the sale's receipt; underneath it is a
 * credit note of the sale's invoice, at its prices, and its refund - in cash
 * from the till, by the sale's own method, or left as a named customer's
 * credit.
 *
 * And (Phase E2) the refund rules: another of the register's methods, with
 * its permission and why; a method that waits, its refund pending until
 * confirmed or declined; and the refund receipt.
 *
 * And (Phase E3) exchanges: a return whose credit pays the sale of the
 * replacement, for its own customer, once - the difference paid by the sale's
 * payments, or paid back in cash.
 *
 * Two teas at 3.50 and a mug at 8.00: 15.00. Thursday 19 November 2026.
 */
class PosReturnsTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const RETURNS = '/api/v1/pos/returns';

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
        $this->card = (int) $this->postJson('/api/v1/business-settings/pos/payment-methods', ['name' => 'Card', 'type' => 'card', 'payment_account_id' => $this->bank->id, 'needs_reference' => true])->assertCreated()->json('data.id');

        $this->walkIn = $this->business->contacts()->create(['contact_type' => 'individual', 'role' => 'customer', 'name' => 'Walk-in Customer']);
        $this->business->posSetting()->firstOrFail()->update(['walk_in_contact_id' => $this->walkIn->id]);

        $this->tea = $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Black Tea', 'unit' => 'Piece', 'sale_price' => '3.50']);
        $this->mug = $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Mug', 'unit' => 'Piece', 'sale_price' => '8.00']);

        $register      = $this->postJson(self::REGISTERS, [
            'name' => 'Front Counter', 'code' => 'R1', 'location_id' => $this->dhaka->id, 'currency' => 'USD', 'payment_method_ids' => [$this->cash, $this->card], 'cashier_ids' => [$this->user->id],
        ])->assertCreated()->json('data');
        $this->session = $this->postJson('/api/v1/pos/sessions', ['pos_register_id' => $register['id'], 'opening_float' => '50'])->assertCreated()->json('data.id');
    }

    /** Two teas and a mug, 15.00, to $contact - the walk-in customer unless named - paid by $payments, cash unless given: its receipt's lines by item name. */
    private function sell(?Contact $contact = null, ?array $payments = null): array
    {
        $this->postJson('/api/v1/pos/orders', [
            'contact_id' => ($contact ?? $this->walkIn)->id,
            'lines'      => [['item_id' => $this->tea->id, 'quantity' => '2'], ['item_id' => $this->mug->id, 'quantity' => '1']],
            'total'      => '15.00',
            'payments'   => $payments ?? [['method_id' => $this->cash, 'amount' => '15.00']],
        ])->assertCreated();

        return SalesDocument::latest('id')->firstOrFail()->lines->pluck('id', 'name')->all();
    }

    private function takeBack(int $orderId, array $lines, string $refund, string $reason = 'Wrong flavour'): TestResponse
    {
        return $this->postJson(self::RETURNS, ['order_id' => $orderId, 'lines' => $lines, 'reason' => $reason, 'refund' => $refund]);
    }

    private function balanceOf(PaymentAccount $account): string
    {
        $moved = fn (string $direction) => (float) AccountTransaction::where('payment_account_id', $account->id)->where('status', 'completed')->where('direction', $direction)->sum('amount');

        return number_format($moved(AccountTransaction::IN) - $moved(AccountTransaction::OUT), 2, '.', '');
    }

    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => Permissions::expand($permissions)]);
        $this->business->members()->create(['user_id' => $user->id, 'name' => $user->name, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    public function test_a_return_from_the_receipt_is_a_credit_note_paid_back_in_cash(): void
    {
        $lines = $this->sell();

        $sale = $this->getJson(self::RETURNS.'/lookup?receipt=R1-00001')->assertOk()
            ->assertJsonPath('data.number', 'R1-00001')
            ->assertJsonPath('data.customer', 'Walk-in Customer')
            ->assertJsonPath('data.walk_in', true)
            ->assertJsonPath('data.paid_by', ['Cash'])
            ->assertJsonPath('data.lines.0', ['line_id' => $lines['Black Tea'], 'name' => 'Black Tea', 'unit' => 'Piece', 'sold' => '2.000', 'returnable' => '2.000', 'unit_price' => '3.50'])
            ->json('data');

        $this->getJson(self::RETURNS."/quote?order_id={$sale['id']}&lines[0][line_id]={$lines['Black Tea']}&lines[0][quantity]=1")->assertOk()->assertJsonPath('data', ['total' => '3.50', 'tax_total' => '0.00']);

        $this->takeBack($sale['id'], [['line_id' => $lines['Black Tea'], 'quantity' => '1', 'condition' => 'resellable']], 'cash')->assertCreated()
            ->assertJsonPath('message', 'Return RT-00001 recorded.')
            ->assertJsonPath('data', ['id' => PosReturn::sole()->id, 'number' => 'RT-00001', 'total' => '3.50', 'refunded' => '3.50', 'refunded_by' => 'Cash', 'pending' => '0.00', 'credit_note' => 'CN-00001', 'exchange' => null]);

        // Underneath: an issued credit note of the sale's invoice, and its refund out of the till.
        $note = SalesDocument::where('number', 'CN-00001')->sole();
        $this->assertSame(['credit_note', 'issued', '3.50', 'Wrong flavour'], [$note->type->value, $note->status->value, (string) $note->grand_total, $note->reason]);
        $this->assertSame(['refund', '3.50', $this->till->id], [SalesPayment::where('kind', 'refund')->sole()->kind, (string) SalesPayment::where('kind', 'refund')->sole()->amount, SalesPayment::where('kind', 'refund')->sole()->payment_account_id]);
        $this->assertSame('11.50', $this->balanceOf($this->till));
        $this->assertSame(['resellable', '1.000'], [PosReturn::sole()->lines->first()->condition, (string) PosReturn::sole()->lines->first()->quantity]);

        // The session counts the cash paid back.
        $totals = $this->getJson("/api/v1/pos/sessions/{$this->session}")->assertOk()->json('data.totals');
        $this->assertSame(['3.50', '61.50'], [$totals['cash_refunds'], $totals['expected_cash']]);
        $this->assertSame(1, ActivityLog::where('description', 'Took back RT-00001 from R1-00001 at the register "Front Counter" (R1), for USD 3.50: Cash 3.50 - Wrong flavour')->count());

        // What is left to return; no more than that.
        $this->getJson(self::RETURNS.'/lookup?receipt=R1-00001')->assertOk()->assertJsonPath('data.lines.0.returnable', '1.000');
        $this->takeBack($sale['id'], [['line_id' => $lines['Black Tea'], 'quantity' => '2', 'condition' => 'damaged']], 'cash')->assertUnprocessable()
            ->assertJsonPath('errors', ['lines.0.quantity' => ['Only USD 3.50 of this line is left to credit.']]);
        $this->getJson(self::RETURNS.'/lookup?receipt=R9-99999')->assertNotFound()->assertJsonPath('message', 'There is no sale with the receipt number R9-99999.');
    }

    public function test_a_card_sale_is_paid_back_by_card_and_a_named_customers_can_stay_credit(): void
    {
        $card = $this->sell(null, [['method_id' => $this->card, 'amount' => '15.00', 'reference' => 'AUTH-1']]);
        $this->takeBack(1, [['line_id' => $card['Mug'], 'quantity' => '1', 'condition' => 'damaged']], 'original')->assertCreated()
            ->assertJsonPath('data.refunded', '8.00')->assertJsonPath('data.refunded_by', 'Card');
        $this->assertSame('7.00', $this->balanceOf($this->bank));

        // The walk-in customer's credit cannot be kept; a named customer's can.
        $this->takeBack(1, [['line_id' => $card['Black Tea'], 'quantity' => '1', 'condition' => 'resellable']], 'credit')->assertUnprocessable()->assertJsonValidationErrors('refund');

        $rahman = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Rahman Traders']);
        $theirs = $this->sell($rahman);
        $this->takeBack(2, [['line_id' => $theirs['Mug'], 'quantity' => '1', 'condition' => 'resellable']], 'credit')->assertCreated()
            ->assertJsonPath('data.refunded', '0.00')->assertJsonPath('data.refunded_by', null);
        $this->assertSame('8.00', (string) SalesDocument::where('number', 'CN-00002')->sole()->balance_due);
        $this->assertSame(1, SalesPayment::where('kind', 'refund')->count());
    }

    public function test_another_method_needs_its_permission_and_why_and_one_that_waits_pends(): void
    {
        $this->putJson("/api/v1/business-settings/pos/payment-methods/{$this->card}", ['name' => 'Card', 'type' => 'card', 'payment_account_id' => $this->bank->id, 'needs_reference' => true, 'confirms_later' => true])->assertOk();
        $lines = $this->sell();
        $tea   = fn (string $condition = 'resellable') => [['line_id' => $lines['Black Tea'], 'quantity' => '1', 'condition' => $condition]];

        $this->postJson(self::RETURNS, ['order_id' => 1, 'lines' => $tea(), 'reason' => 'Wrong flavour', 'refund' => 'method', 'method_id' => $this->card])
            ->assertUnprocessable()->assertJsonPath('errors.refund_reason.0', 'Say why it is paid back this way.');
        $this->postJson(self::RETURNS, ['order_id' => 1, 'lines' => $tea(), 'reason' => 'Wrong flavour', 'refund' => 'method', 'method_id' => $this->card, 'refund_reason' => 'No cash left'])
            ->assertCreated()->assertJsonPath('data.refunded', '0.00')->assertJsonPath('data.pending', '3.50')->assertJsonPath('data.refunded_by', 'Card');

        // Pending: nothing out of the bank yet; confirmed, the refund it becomes.
        $this->assertSame([0, PosReturn::PENDING], [SalesPayment::where('kind', 'refund')->count(), PosReturn::sole()->refund_status]);
        $pending = $this->getJson("/api/v1/pos/sessions/{$this->session}/pending-refunds")->assertOk()->json('data');
        $this->assertSame([['RT-00001', 'R1-00001', 'Card', '3.50', 'pending']], array_map(fn (array $row) => [$row['number'], $row['order'], $row['method'], $row['amount'], $row['status']], $pending));
        $this->postJson("/api/v1/pos/returns/{$pending[0]['id']}/refund/confirm")->assertOk()->assertJsonPath('message', 'The Card refund for RT-00001 is confirmed.');
        $this->assertSame(['-3.50', '3.50'], [$this->balanceOf($this->bank), (string) PosReturn::sole()->refunded]);
        $this->postJson("/api/v1/pos/returns/{$pending[0]['id']}/refund/confirm")->assertConflict()->assertJsonPath('message', "RT-00001's refund is not waiting to be confirmed.");
        $this->assertSame(1, ActivityLog::where('description', 'Confirmed the Card refund of USD 3.50 for RT-00001')->count());

        // Declined: the credit stays on its credit note.
        $second = $this->postJson(self::RETURNS, ['order_id' => 1, 'lines' => $tea('damaged'), 'reason' => 'Torn bag', 'refund' => 'method', 'method_id' => $this->card, 'refund_reason' => 'No cash left'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/pos/returns/{$second}/refund/decline", ['reason' => 'The terminal refused it'])->assertOk()
            ->assertJsonPath('message', 'The Card refund for RT-00002 is declined: its credit stays on the credit note.');
        $this->assertSame([PosReturn::DECLINED, '3.50'], [PosReturn::findOrFail($second)->refund_status, (string) SalesDocument::where('number', 'CN-00002')->sole()->balance_due]);

        // Another method is for whoever may use alternative refund methods.
        $clerk = $this->member(['pos.returns.refund']);
        $back  = $this->postJson(self::REGISTERS, [
            'name' => 'Back Counter', 'code' => 'R2', 'location_id' => $this->dhaka->id, 'currency' => 'USD', 'payment_method_ids' => [$this->cash], 'cashier_ids' => [$clerk->id],
        ])->assertCreated()->json('data');
        Sanctum::actingAs($clerk);
        $this->postJson('/api/v1/pos/sessions', ['pos_register_id' => $back['id'], 'opening_float' => '20'])->assertCreated();
        $this->postJson(self::RETURNS, ['order_id' => 1, 'lines' => [['line_id' => $lines['Mug'], 'quantity' => '1', 'condition' => 'resellable']], 'reason' => 'Gift', 'refund' => 'method', 'method_id' => $this->cash, 'refund_reason' => 'Asked'])
            ->assertForbidden()->assertJsonPath('message', 'Paying back by another method needs Use Alternative Refund Methods - or a manager\'s approval at the register.');
    }

    public function test_a_refund_receipt_names_the_return_and_its_sale(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])]);
        $lines = $this->sell();
        $id    = $this->takeBack(1, [['line_id' => $lines['Black Tea'], 'quantity' => '1', 'condition' => 'resellable']], 'cash')->assertCreated()->json('data.id');

        $this->get(self::RETURNS."/{$id}/receipt", ['Accept' => 'application/json'])->assertOk()->assertHeader('Content-Disposition', 'inline; filename=Refund-Receipt-RT-00001.pdf');

        /** @var Request $request */
        [$request] = Http::recorded()->last();
        $html = (string) collect($request->data())->firstWhere('filename', 'index.html')['contents'];
        foreach (['Refund Receipt', 'RT-00001', 'R1-00001', 'Black Tea', '1 Piece × $3.50', 'Cash', '$3.50'] as $shown) {
            $this->assertStringContainsString($shown, $html);
        }
        $this->assertStringNotContainsString('Kept as credit', $html);
        $this->getJson(self::RETURNS.'/99999/receipt')->assertNotFound()->assertJsonPath('message', 'There is no such return.');
    }

    public function test_returns_and_paying_them_back_need_their_permissions(): void
    {
        $rahman = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Rahman Traders']);
        $lines  = $this->sell($rahman);

        Sanctum::actingAs($this->member(['pos.selling.sell']));
        $this->getJson(self::RETURNS.'/lookup?receipt=R1-00001')->assertForbidden();

        // Who may process returns, but not refunds, at their own register: credit, yes; cash, no.
        $clerk = $this->member(['pos.returns.process']);
        Sanctum::actingAs($this->user);
        $back = $this->postJson(self::REGISTERS, [
            'name' => 'Back Counter', 'code' => 'R2', 'location_id' => $this->dhaka->id, 'currency' => 'USD', 'payment_method_ids' => [$this->cash], 'cashier_ids' => [$clerk->id],
        ])->assertCreated()->json('data');
        Sanctum::actingAs($clerk);
        $this->postJson('/api/v1/pos/sessions', ['pos_register_id' => $back['id'], 'opening_float' => '20'])->assertCreated();

        $this->takeBack(1, [['line_id' => $lines['Mug'], 'quantity' => '1', 'condition' => 'resellable']], 'cash')->assertForbidden()
            ->assertJsonPath('message', 'Paying back a return needs Process Refunds: leave it as the customer\'s credit instead.');
        $this->takeBack(1, [['line_id' => $lines['Mug'], 'quantity' => '1', 'condition' => 'resellable']], 'credit')->assertCreated();
        $this->postJson(self::RETURNS, ['order_id' => 1, 'lines' => [['line_id' => $lines['Black Tea'], 'quantity' => '1', 'condition' => 'broken']], 'refund' => 'credit'])
            ->assertUnprocessable()->assertJsonValidationErrors(['lines.0.condition', 'reason']);
    }

    public function test_an_exchange_keeps_its_credit_for_the_replacement_and_pays_back_the_difference(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])]);
        $lines = $this->sell();

        // The mug comes back as an exchange: nothing paid out, its credit kept on the credit note - the walk-in customer's too.
        $return = $this->takeBack(1, [['line_id' => $lines['Mug'], 'quantity' => '1', 'condition' => 'damaged']], 'exchange', 'Chipped')->assertCreated()
            ->assertJsonPath('data.refunded', '0.00')
            ->assertJsonPath('data.exchange', ['credit' => '8.00', 'customer' => ['id' => $this->walkIn->id, 'name' => 'Walk-in Customer']])
            ->json('data.id');
        $this->assertSame([0, '8.00'], [SalesPayment::where('kind', 'refund')->count(), (string) SalesDocument::where('number', 'CN-00001')->sole()->balance_due]);
        $this->assertSame(1, ActivityLog::where('description', 'Took back RT-00001 from R1-00001 at the register "Front Counter" (R1), for USD 8.00: nothing paid back, USD 8.00 kept for the exchange - Chipped')->count());

        // A tea in its place: the credit pays it, and the 4.50 it leaves is paid back from the till.
        $tea  = fn () => $this->postJson('/api/v1/pos/orders', ['contact_id' => $this->walkIn->id, 'lines' => [['item_id' => $this->tea->id, 'quantity' => '1']], 'total' => '3.50', 'payments' => [], 'exchange_return_id' => $return]);
        $sale = $tea()->assertCreated()->assertJsonPath('data.number', 'R1-00002')
            ->assertJsonPath('data.exchange', ['number' => 'RT-00001', 'amount' => '3.50', 'paid_back' => '4.50'])->json('data.id');

        $invoice = SalesDocument::where('number', 'R1-00002')->sole();
        $note    = SalesDocument::where('number', 'CN-00001')->sole();
        $refund  = SalesPayment::where('kind', 'refund')->sole();
        $this->assertSame(['paid', '0.00', '3.50'], [$invoice->payment_status, (string) $invoice->balance_due, (string) $invoice->allocated_amount]);
        $this->assertSame(['4.50', $this->till->id, $note->id, '0.00'], [(string) $refund->amount, $refund->payment_account_id, $refund->sales_document_id, (string) $note->balance_due]);
        $this->assertSame([$sale, $return, '3.50', '4.50'], [PosReturn::sole()->exchange_order_id, PosOrder::findOrFail($sale)->exchange_return_id, (string) PosOrder::findOrFail($sale)->exchange_credit, (string) PosOrder::findOrFail($sale)->exchange_refunded]);
        $this->assertSame('10.50', $this->balanceOf($this->till));
        $this->assertSame(1, ActivityLog::where('description', 'Sold R1-00002 at the register "Front Counter" (R1) to Walk-in Customer, for USD 3.50: Exchange RT-00001 3.50, Cash 4.50 paid back')->count());

        // The session counts the cash paid back.
        $totals = $this->getJson("/api/v1/pos/sessions/{$this->session}")->assertOk()->json('data.totals');
        $this->assertSame(['15.00', '4.50', '60.50'], [$totals['cash_sales'], $totals['cash_refunds'], $totals['expected_cash']]);

        // Its receipt says so; the return's, what it kept for the exchange.
        $html = fn () => (string) collect(Http::recorded()->last()[0]->data())->firstWhere('filename', 'index.html')['contents'];
        $this->get("/api/v1/pos/orders/{$sale}/receipt", ['Accept' => 'application/json'])->assertOk();
        foreach (['Exchange · RT-00001', '$3.50', 'Paid back', '$4.50'] as $shown) {
            $this->assertStringContainsString($shown, $html());
        }
        $this->get(self::RETURNS."/{$return}/receipt", ['Accept' => 'application/json'])->assertOk();
        $this->assertStringContainsString('Kept for the exchange', $html());

        // Once only.
        $tea()->assertConflict()->assertJsonPath('message', "RT-00001's exchange was sold already.");
    }

    public function test_a_dearer_replacement_pays_the_rest_and_an_exchange_is_its_own_customers(): void
    {
        $rahman = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Rahman Traders']);
        $lines  = $this->sell($rahman);
        $tea    = [['line_id' => $lines['Black Tea'], 'quantity' => '1', 'condition' => 'resellable']];
        $return = $this->takeBack(1, $tea, 'exchange')->assertCreated()->json('data.id');
        $kept   = $this->takeBack(1, $tea, 'credit')->assertCreated()->json('data.id');
        $mug    = fn (array $with) => $this->postJson('/api/v1/pos/orders', [
            'contact_id' => $rahman->id, 'lines' => [['item_id' => $this->mug->id, 'quantity' => '1']], 'total' => '8.00', 'exchange_return_id' => $return, ...$with,
        ]);

        // For its own customer, made as an exchange - and never with the customer's credit besides.
        $mug(['contact_id' => $this->walkIn->id, 'payments' => [['method_id' => $this->cash, 'amount' => '4.50']]])->assertUnprocessable()
            ->assertJsonPath('errors.contact_id.0', "RT-00001's exchange is for its own customer: choose them again.");
        $mug(['exchange_return_id' => $kept, 'payments' => [['method_id' => $this->cash, 'amount' => '4.50']]])->assertUnprocessable()
            ->assertJsonPath('errors.exchange_return_id.0', 'RT-00002 was not made as an exchange.');
        $mug(['credit' => '1.00', 'payments' => [['method_id' => $this->cash, 'amount' => '3.50']]])->assertUnprocessable()
            ->assertJsonPath('errors.credit.0', "An exchange's sale is paid by its return's credit first: take the rest by the register's methods.");

        // The credit pays 3.50 of the mug; the card, the rest - no more.
        $mug(['payments' => [['method_id' => $this->card, 'amount' => '8.00', 'reference' => 'AUTH-2']]])->assertUnprocessable()
            ->assertJsonPath('errors.payments.0', 'The payments come to more than the total of USD 4.50: only cash gives change.');
        $mug(['payments' => [['method_id' => $this->card, 'amount' => '4.50', 'reference' => 'AUTH-2']]])->assertCreated()
            ->assertJsonPath('data.exchange', ['number' => 'RT-00001', 'amount' => '3.50', 'paid_back' => '0.00'])
            ->assertJsonPath('data.payments', [['method' => 'Card', 'amount' => '4.50', 'pending' => false]]);

        $this->assertSame(['paid', '0.00'], [SalesDocument::where('number', 'R1-00002')->sole()->payment_status, (string) SalesDocument::where('number', 'R1-00002')->sole()->balance_due]);
        $this->assertSame(['0.00', '3.50'], [(string) SalesDocument::where('number', 'CN-00001')->sole()->balance_due, (string) SalesDocument::where('number', 'CN-00002')->sole()->balance_due]);
        $this->assertSame([0, '4.50'], [SalesPayment::where('kind', 'refund')->count(), $this->balanceOf($this->bank)]);
    }

    public function test_paying_an_exchange_back_needs_process_refunds(): void
    {
        $lines = $this->sell();
        $clerk = $this->member(['pos.returns.process', 'pos.selling.sell']);
        $back  = $this->postJson(self::REGISTERS, [
            'name' => 'Back Counter', 'code' => 'R2', 'location_id' => $this->dhaka->id, 'currency' => 'USD', 'payment_method_ids' => [$this->cash], 'cashier_ids' => [$clerk->id],
        ])->assertCreated()->json('data');
        Sanctum::actingAs($clerk);
        $this->postJson('/api/v1/pos/sessions', ['pos_register_id' => $back['id'], 'opening_float' => '20'])->assertCreated();

        // Made as an exchange, nothing is paid back: whoever may process returns makes it.
        $return = $this->takeBack(1, [['line_id' => $lines['Mug'], 'quantity' => '1', 'condition' => 'resellable']], 'exchange')->assertCreated()->json('data.id');
        $sale   = fn (string $quantity, string $total, array $payments) => $this->postJson('/api/v1/pos/orders', [
            'contact_id' => $this->walkIn->id, 'lines' => [['item_id' => $this->tea->id, 'quantity' => $quantity]], 'total' => $total, 'payments' => $payments, 'exchange_return_id' => $return,
        ]);

        // A cheaper replacement pays the difference back: that needs Process Refunds. A dearer one does not.
        $sale('1', '3.50', [])->assertForbidden()->assertJsonPath('message', "The replacement costs less than RT-00001's credit: paying the difference back needs Process Refunds.");
        $sale('3', '10.50', [['method_id' => $this->cash, 'amount' => '2.50']])->assertCreated()->assertJsonPath('data.exchange', ['number' => 'RT-00001', 'amount' => '8.00', 'paid_back' => '0.00']);
        $this->assertSame(0, SalesPayment::where('kind', 'refund')->count());
    }
}
