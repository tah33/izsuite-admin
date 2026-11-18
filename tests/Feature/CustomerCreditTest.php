<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\PaymentAccount;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 16 Phase G1: a customer's credit control - their limit, whether they
 * buy on credit, what going over does and a note - and their exposure: what
 * their invoices owe, what counted orders have not invoiced, less the credit
 * they hold. Issuing an invoice, or confirming a counted order, that adds to
 * it and takes it over warns, or blocks unless someone allowed gives a
 * reason. The base invoice is USD 335.50, due 27 November. Wednesday 28 October 2026.
 */
class CustomerCreditTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private PaymentAccount $cash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);

        $this->offerSalesLite();
        $full = Application::create(['app_key' => 'sales', 'name' => 'Sales', 'family_key' => 'sales', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 10, 'is_active' => true, 'status' => 'available']);
        $full->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->setUpSales();
        $this->cash = $this->account();
    }

    private function full(): void
    {
        $this->postJson('/api/v1/apps/sales/activate')->assertOk();
    }

    private function credit(array $fields = []): TestResponse
    {
        return $this->putJson("/api/v1/sales/customers/{$this->customer->id}/credit", [
            'credit_limit'   => '400.00',
            'credit_sales'   => true,
            'credit_action'  => 'warn',
            'credit_warning' => null,
            ...$fields,
        ]);
    }

    private function advance(string $amount): void
    {
        $this->postJson('/api/v1/sales/advances', [
            'contact_id' => $this->customer->id, 'payment_date' => '2026-10-28', 'payment_account_id' => $this->cash->id,
            'currency'   => 'USD', 'amount' => $amount, 'method' => 'cash',
        ])->assertCreated();
    }

    private function issue(int $id, array $fields = []): TestResponse
    {
        return $this->postJson("/api/v1/sales/invoices/{$id}/issue", $fields);
    }

    private function figures(): array
    {
        return $this->getJson("/api/v1/sales/customers/{$this->customer->id}/credit")->assertOk()->json('data');
    }

    public function test_a_customer_credit_is_set_and_shows_what_they_owe(): void
    {
        // The full Sales app's.
        $this->getJson("/api/v1/sales/customers/{$this->customer->id}/credit")->assertForbidden();

        $this->full();

        $this->assertSame([
            'currency' => 'USD', 'credit_limit' => null, 'credit_sales' => true, 'credit_action' => 'warn', 'credit_warning' => null, 'counts_orders' => false,
            'outstanding' => '0.00', 'overdue' => '0.00', 'orders' => '0.00', 'unapplied' => '0.00', 'exposure' => '0.00', 'available' => null, 'over' => false,
        ], $this->figures());

        $this->issuedInvoice();
        $this->advance('100.00');

        $this->credit(['credit_limit' => '300', 'credit_action' => 'block', 'credit_warning' => ' Ask for payment first '])
            ->assertOk()
            ->assertJsonPath('message', 'Credit control saved.')
            ->assertJsonPath('data.credit_limit', '300.00')
            ->assertJsonPath('data.credit_warning', 'Ask for payment first')
            ->assertJsonPath('data.outstanding', '335.50')
            ->assertJsonPath('data.unapplied', '100.00')
            ->assertJsonPath('data.exposure', '235.50')
            ->assertJsonPath('data.available', '64.50')
            ->assertJsonPath('data.over', false);

        $this->assertDatabaseHas('activity_logs', ['description' => 'Changed the credit control of Uddin Traders: limit USD 300.00, buys on credit, going over blocks, note "Ask for payment first"']);

        // Past its due date, the invoice is overdue.
        $this->travelTo(Carbon::parse('2026-12-01 10:00:00', 'UTC'));
        $this->assertSame(['335.50', '335.50'], [$this->figures()['outstanding'], $this->figures()['overdue']]);

        $this->credit(['credit_limit' => '-5'])->assertJsonValidationErrors(['credit_limit' => 'A credit limit cannot be below zero.']);
        $this->credit(['credit_action' => 'stop'])->assertJsonValidationErrors('credit_action');
        $this->putJson('/api/v1/sales/customers/999999/credit', ['credit_sales' => true, 'credit_action' => 'warn'])->assertNotFound();
    }

    public function test_going_over_warns_or_blocks_and_an_override_needs_a_reason(): void
    {
        $this->full();
        $this->credit(['credit_limit' => '400']);

        $this->issue($this->draft('invoices'))->assertOk()->assertJsonPath('message', 'Invoice INV-00001 issued.');

        // Warn: through, saying so.
        $this->issue($this->draft('invoices'))->assertOk()
            ->assertJsonPath('message', 'Invoice INV-00002 issued. Uddin Traders has a credit limit of USD 400.00, and this takes their balance to USD 671.00.');

        // Block: refused, unless someone who may gives a reason.
        $this->credit(['credit_limit' => '400', 'credit_action' => 'block']);
        $third = $this->draft('invoices');

        $this->issue($third)->assertUnprocessable()->assertJsonValidationErrors([
            'credit_reason' => 'Uddin Traders has a credit limit of USD 400.00, and this would take their balance to USD 1,006.50. To go on, give a reason.',
        ]);
        $this->getJson("/api/v1/sales/invoices/{$third}")->assertJsonPath('data.status', 'draft');

        $this->issue($third, ['credit_reason' => ' Paid by cheque tomorrow '])->assertOk()
            ->assertJsonPath('message', 'Invoice INV-00003 issued. Uddin Traders has a credit limit of USD 400.00, and this takes their balance to USD 1,006.50.')
            ->assertJsonPath('data.credit_reason', 'Paid by cheque tomorrow');
        $this->assertDatabaseHas('activity_logs', ['description' => "Issued invoice INV-00003 over its customer's credit limit, because: Paid by cheque tomorrow"]);

        // Someone who may not override is told who can - a reason changes nothing.
        $fourth = $this->draft('invoices');
        $member = User::factory()->create();
        $role   = $this->business->roles()->create(['name' => 'Counter', 'permissions' => [
            'contacts.view', 'items.view', 'sales.invoices.view', 'sales.invoices.create', 'sales.invoices.edit', 'sales.invoices.issue', 'sales.values.view',
        ]]);
        $this->business->members()->create(['user_id' => $member->id, 'status' => 'active', 'role_id' => $role->id]);
        Sanctum::actingAs($member);

        $this->issue($fourth, ['credit_reason' => 'Trust me'])->assertUnprocessable()->assertJsonValidationErrors([
            'credit_reason' => 'Uddin Traders has a credit limit of USD 400.00, and this would take their balance to USD 1,342.00. Someone who may override credit limits can go on, with a reason.',
        ]);

        // Nor may they set the limit.
        $this->credit(['credit_limit' => '5000'])->assertForbidden();
    }

    public function test_what_adds_nothing_goes_through_and_counted_orders_are_checked(): void
    {
        $this->full();

        // Paying ahead covers a customer who may not buy on credit.
        $this->credit(['credit_limit' => null, 'credit_sales' => false, 'credit_action' => 'block']);
        $this->advance('400.00');

        $this->issue($this->draft('invoices'))->assertOk()->assertJsonPath('message', 'Invoice INV-00001 issued.');
        $this->issue($this->draft('invoices'))->assertUnprocessable()->assertJsonValidationErrors([
            'credit_reason' => 'Uddin Traders buys only with payment ahead, and this would leave USD 271.00 on credit. To go on, give a reason.',
        ]);

        // Orders count once Settings > Sales says so: confirming one adds it, invoicing it then adds nothing.
        $this->credit(['credit_limit' => '400', 'credit_sales' => true, 'credit_action' => 'block']);
        $this->putJson('/api/v1/business-settings/sales', [
            'payment_terms' => 0, 'quotation_validity' => 30, 'proforma_validity' => 30, 'line_discounts' => true, 'document_discounts' => true, 'credit_counts_orders' => true,
        ])->assertOk()->assertJsonPath('data.credit_counts_orders', true);

        $order = $this->draft('orders');
        $this->postJson("/api/v1/sales/orders/{$order}/confirm")->assertOk()->assertJsonPath('message', 'Sales order SO-00001 confirmed.');
        $this->assertSame(['335.50', '335.50', '400.00', '271.00'], array_values(array_intersect_key($this->figures(), array_flip(['outstanding', 'orders', 'unapplied', 'exposure']))));

        $this->postJson('/api/v1/sales/orders/'.$this->draft('orders').'/confirm')->assertUnprocessable()->assertJsonValidationErrors([
            'credit_reason' => 'Uddin Traders has a credit limit of USD 400.00, and this would take their balance to USD 606.50. To go on, give a reason.',
        ]);

        // Its invoice moves what is owed from the order to the invoice: nothing added, so nothing checked.
        $lines   = array_column($this->getJson("/api/v1/sales/orders/{$order}")->json('data.lines'), 'id');
        $invoice = $this->postJson("/api/v1/sales/orders/{$order}/invoice", ['lines' => [['line_id' => $lines[0], 'quantity' => '2'], ['line_id' => $lines[1], 'quantity' => '1']]])
            ->assertCreated()->json('data.id');

        $this->issue($invoice)->assertOk()->assertJsonPath('message', 'Invoice INV-00002 issued.');
        $this->assertSame(['671.00', '0.00', '400.00', '271.00'], array_values(array_intersect_key($this->figures(), array_flip(['outstanding', 'orders', 'unapplied', 'exposure']))));
    }
}
