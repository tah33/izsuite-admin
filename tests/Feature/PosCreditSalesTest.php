<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\Contact;
use App\Models\User\CustomerAdvance;
use App\Models\User\Item;
use App\Models\User\Location;
use App\Models\User\PaymentAccount;
use App\Models\User\PosOrder;
use App\Models\User\SalesAllocation;
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
 * Doc 18 Phase D3a: credit sales (section 18.17) - at Front Counter (R1) in
 * Dhaka, taking Cash. With the full Sales app, a sale to Rahman Traders can
 * leave what its payments do not pay on their account: one invoice owing
 * the rest, due by their 30 days' terms - within their credit limit, over it
 * with a warning, or, as they block, with an override and why.
 *
 * And (Phase D3b) the customer's own credit - an advance - paying part of a
 * sale: applied to its invoice, no payment into an account.
 *
 * Two teas at 3.50 and a mug at 8.00: 15.00. Thursday 19 November 2026.
 */
class PosCreditSalesTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const ORDERS = '/api/v1/pos/orders';

    private User $user;

    private Workspace $business;

    private Location $dhaka;

    private Contact $walkIn;

    private Contact $rahman;

    private Item $tea;

    private Item $mug;

    private int $cash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);

        $this->offerSalesLite();
        $category = Application::sole()->category_id;
        Application::create(['app_key' => 'sales', 'name' => 'Sales', 'family_key' => 'sales', 'edition' => 'full', 'category_id' => $category, 'sort_order' => 10, 'is_active' => true, 'status' => 'available'])
            ->packages()->attach(Package::pluck('id'));
        Application::create(['app_key' => 'pos', 'name' => 'Point of Sale', 'family_key' => 'pos', 'edition' => 'full', 'category_id' => $category, 'sort_order' => 30, 'is_active' => true, 'status' => 'available'])
            ->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-11-19 10:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->business->forceFill(['base_currency' => 'USD', 'tax_mode' => 'later'])->save();
        $this->dhaka = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka', 'code' => 'DHK']);
        $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Shop Till', 'currency' => 'USD']);

        $this->postJson('/api/v1/apps/pos/activate')->assertOk();
        $this->cash = (int) $this->getJson('/api/v1/pos/registers/options')->assertOk()->json('data.payment_methods.0.id');

        $this->walkIn = $this->business->contacts()->create(['contact_type' => 'individual', 'role' => 'customer', 'name' => 'Walk-in Customer']);
        $this->rahman = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Rahman Traders', 'customer_payment_terms' => 30]);
        $this->rahman->forceFill(['credit_sales' => true, 'credit_limit' => '100.00', 'credit_action' => Contact::CREDIT_WARN])->save();
        $this->business->posSetting()->firstOrFail()->update(['walk_in_contact_id' => $this->walkIn->id]);

        $this->tea = $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Black Tea', 'unit' => 'Piece', 'sale_price' => '3.50']);
        $this->mug = $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Mug', 'unit' => 'Piece', 'sale_price' => '8.00']);

        $register = $this->postJson('/api/v1/pos/registers', [
            'name' => 'Front Counter', 'code' => 'R1', 'location_id' => $this->dhaka->id, 'currency' => 'USD', 'payment_method_ids' => [$this->cash], 'cashier_ids' => [$this->user->id],
        ])->assertCreated()->json('data');
        $this->postJson('/api/v1/pos/sessions', ['pos_register_id' => $register['id'], 'opening_float' => '50'])->assertCreated();
    }

    private function full(): void
    {
        $this->postJson('/api/v1/apps/sales/activate')->assertOk();
    }

    /** Two teas and a mug, 15.00, to $contact - Rahman Traders unless named - paid by $payments, with $fields besides. */
    private function sell(array $payments, array $fields = [], ?Contact $contact = null): TestResponse
    {
        return $this->postJson(self::ORDERS, [
            'contact_id' => ($contact ?? $this->rahman)->id,
            'lines'      => [['item_id' => $this->tea->id, 'quantity' => '2'], ['item_id' => $this->mug->id, 'quantity' => '1']],
            'total'      => '15.00',
            'payments'   => $payments,
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

    /** A member whose role holds $permissions, the cashier of Back Counter (R2), signed in with their session open there. */
    private function cashierAtBackCounter(array $permissions): User
    {
        $cashier = $this->member($permissions);
        $back    = $this->postJson('/api/v1/pos/registers', [
            'name' => 'Back Counter', 'code' => 'R2', 'location_id' => $this->dhaka->id, 'currency' => 'USD', 'payment_method_ids' => [$this->cash], 'cashier_ids' => [$cashier->id],
        ])->assertCreated()->json('data');

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/pos/sessions', ['pos_register_id' => $back['id'], 'opening_float' => '20'])->assertCreated();

        return $cashier;
    }

    public function test_a_sale_left_partly_on_account_is_one_invoice_owing_the_rest(): void
    {
        $this->full();
        $this->getJson('/api/v1/pos/sell')->assertOk()->assertJsonPath('data.credit_sales', true);

        $this->sell([['method_id' => $this->cash, 'amount' => '5.00']], ['on_account' => true])->assertCreated()
            ->assertJsonPath('data.on_account', '10.00')
            ->assertJsonPath('data.credit_warning', null);

        // One invoice: paid in part, the rest owing, due by Rahman Traders' 30 days.
        $invoice = SalesDocument::where('number', 'R1-00001')->sole();
        $this->assertSame(['partially_paid', '5.00', '10.00', '2026-12-19', 30], [$invoice->payment_status, (string) $invoice->amount_paid, (string) $invoice->balance_due, $invoice->due_date, (int) $invoice->payment_terms]);
        $this->assertSame('10.00', (string) PosOrder::sole()->on_account);
        $this->assertSame(1, ActivityLog::where('description', 'Sold R1-00001 at the register "Front Counter" (R1) to Rahman Traders, for USD 15.00: Cash 5.00, on account 10.00')->count());

        // Its receipt says what is on account, and when it is due.
        Http::preventStrayRequests();
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])]);
        $this->get(self::ORDERS.'/'.PosOrder::sole()->id.'/receipt', ['Accept' => 'application/json'])->assertOk();
        /** @var Request $request */
        [$request] = Http::recorded()->last();
        $this->assertStringContainsString('On account · due 19 Dec 2026', (string) collect($request->data())->firstWhere('filename', 'index.html')['contents']);

        // All of it on account, nothing paid.
        $this->sell([], ['on_account' => true])->assertCreated()->assertJsonPath('data.on_account', '15.00')->assertJsonPath('data.payments', []);
    }

    public function test_over_the_limit_it_warns_or_needs_an_override_with_a_reason(): void
    {
        $this->full();
        $this->rahman->forceFill(['credit_limit' => '20.00'])->save();

        $this->sell([], ['on_account' => true])->assertCreated()->assertJsonPath('data.credit_warning', null);
        $this->sell([], ['on_account' => true])->assertCreated()
            ->assertJsonPath('data.credit_warning', 'Rahman Traders has a credit limit of USD 20.00, and this takes their balance to USD 30.00.');

        // A customer who blocks: refused, unless someone who may override says why.
        $this->rahman->forceFill(['credit_action' => Contact::CREDIT_BLOCK])->save();
        $this->cashierAtBackCounter(['pos.selling.credit']);
        $this->sell([], ['on_account' => true])->assertUnprocessable()
            ->assertJsonPath('errors.credit_reason.0', 'Rahman Traders has a credit limit of USD 20.00, and this would take their balance to USD 45.00. Someone who may override credit limits can go on, with a reason.');

        Sanctum::actingAs($this->user);
        $this->sell([], ['on_account' => true])->assertUnprocessable()
            ->assertJsonPath('errors.credit_reason.0', 'Rahman Traders has a credit limit of USD 20.00, and this would take their balance to USD 45.00. To go on, give a reason.');
        $this->sell([], ['on_account' => true, 'credit_reason' => 'Pays every Friday'])->assertCreated()->assertJsonPath('data.credit_warning', null);

        $this->assertSame('Pays every Friday', PosOrder::latest('id')->first()->credit_reason);
        $this->assertSame(1, ActivityLog::where('description', "Sold R1-00003 over its customer's credit limit, because: Pays every Friday")->count());
    }

    public function test_the_customers_own_credit_pays_part_of_a_sale(): void
    {
        $this->full();
        $this->postJson('/api/v1/sales/advances', [
            'contact_id' => $this->rahman->id, 'payment_date' => '2026-11-19', 'payment_account_id' => PaymentAccount::where('name', 'Shop Till')->value('id'),
            'currency'   => 'USD', 'amount' => '6.00', 'method' => 'cash',
        ])->assertCreated();

        $this->getJson('/api/v1/pos/sell/credit?contact_id='.$this->rahman->id)->assertOk()->assertJsonPath('data', ['available' => '6.00']);
        $this->getJson('/api/v1/pos/sell/credit?contact_id='.$this->walkIn->id)->assertOk()->assertJsonPath('data.available', '0.00');

        $this->sell([['method_id' => $this->cash, 'amount' => '8.00']], ['credit' => '7.00'])->assertUnprocessable()->assertJsonPath('errors.credit.0', 'Rahman Traders holds USD 6.00 of credit.');
        $this->sell([['method_id' => $this->cash, 'amount' => '9.00']], ['credit' => '6.00'])->assertCreated()->assertJsonPath('data.credit', '6.00');

        // Applied to the invoice - no payment into an account - the advance used up.
        $invoice = SalesDocument::where('number', 'R1-00001')->sole();
        $this->assertSame(['paid', '0.00'], [$invoice->payment_status, (string) $invoice->balance_due]);
        $this->assertSame(['6.00'], SalesAllocation::where('invoice_id', $invoice->id)->get()->map(fn (SalesAllocation $row) => (string) $row->amount)->all());
        $this->assertSame(['0.00', 1], [(string) CustomerAdvance::sole()->balance, SalesPayment::where('sales_document_id', $invoice->id)->count()]);
        $this->assertSame('6.00', (string) PosOrder::sole()->credit_applied);
        $this->getJson('/api/v1/pos/sell/credit?contact_id='.$this->rahman->id)->assertJsonPath('data.available', '0.00');
        $this->assertSame(1, ActivityLog::where('description', 'Sold R1-00001 at the register "Front Counter" (R1) to Rahman Traders, for USD 15.00: Customer credit 6.00, Cash 9.00')->count());

        $this->sell([['method_id' => $this->cash, 'amount' => '14.00']], ['credit' => '1.00'], $this->walkIn)->assertUnprocessable()
            ->assertJsonPath('errors.credit.0', 'The walk-in customer holds no credit: choose the customer whose credit it is.');
    }

    public function test_a_credit_sale_needs_the_full_sales_app_its_permission_and_a_named_customer(): void
    {
        // Sales Lite: none is left on account.
        $this->getJson('/api/v1/pos/sell')->assertOk()->assertJsonPath('data.credit_sales', false);
        $this->sell([], ['on_account' => true])->assertConflict()
            ->assertJsonPath('message', 'A sale is left on account with the full Sales app: switch it on in the Apps Center, or take the whole payment.');

        $this->full();
        $this->sell([], ['on_account' => true], $this->walkIn)->assertUnprocessable()
            ->assertJsonPath('errors.contact_id.0', 'A sale is left on account for a named customer, not the walk-in one.');
        $this->sell([['method_id' => $this->cash, 'amount' => '5.00']])->assertUnprocessable()->assertJsonPath('errors.payments.0', 'USD 10.00 is still to pay.');

        // Paid in full, a sale needs no credit, whatever is asked.
        $this->sell([['method_id' => $this->cash, 'amount' => '15.00']], ['on_account' => true])->assertCreated()->assertJsonPath('data.on_account', '0.00');

        $this->cashierAtBackCounter(['pos.selling.sell']);
        $this->sell([], ['on_account' => true])->assertForbidden()->assertJsonPath('message', 'Leaving a sale on account needs Create Credit Sales.');
    }
}
