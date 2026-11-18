<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\AccountTransaction;
use App\Models\User\PaymentAccount;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 16 Phase D1: customer advances recorded from a customer or a sales
 * order, or kept from a payment over its invoice once confirmed; advances
 * and credit notes' remaining credit applied to the customer's invoices and
 * taken off again, with their history; and what each blocks while applied.
 * The base invoice is USD 335.50. Wednesday 28 October 2026.
 */
class CustomerAdvancesTest extends TestCase
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

    private function advance(array $fields = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/sales/advances', [
            'contact_id'         => $this->customer->id,
            'payment_date'       => '2026-10-28',
            'payment_account_id' => $this->cash->id,
            'currency'           => 'USD',
            'amount'             => '100.00',
            'method'             => 'cash',
            ...$fields,
        ]);
    }

    public function test_an_advance_is_applied_to_an_invoice_and_taken_off_again(): void
    {
        // Sales Lite takes no advances.
        $this->advance()->assertForbidden();

        $this->full();
        $invoice = $this->issuedInvoice();

        $this->assertContains('apply_credit', $this->getJson("/api/v1/sales/invoices/{$invoice}")->json('data.actions'));

        $advance = $this->advance()->assertCreated()
            ->assertJsonPath('message', 'Advance ADV-00001 recorded.')
            ->assertJsonPath('data.balance', '100.00')
            ->assertJsonPath('data.actions', ['apply', 'reverse'])
            ->json('data.id');

        // Money in, in the account's history - but no sale.
        $this->assertSame(['type' => 'customer_advance', 'direction' => 'in', 'amount' => '100.00'], AccountTransaction::sole()->only(['type', 'direction', 'amount']));

        $this->assertSame([['advance', 'ADV-00001', '100.00']], array_map(fn (array $row) => [$row['kind'], $row['number'], $row['available']], $this->getJson("/api/v1/sales/invoices/{$invoice}/credit")->json('data')));

        $this->postJson("/api/v1/sales/invoices/{$invoice}/allocations", ['advance_id' => $advance, 'amount' => '150.00'])->assertUnprocessable()->assertJsonValidationErrors('amount');

        $allocation = $this->postJson("/api/v1/sales/invoices/{$invoice}/allocations", ['advance_id' => $advance, 'amount' => '60.00'])
            ->assertCreated()
            ->assertJsonPath('message', "Applied advance ADV-00001 to invoice INV-00001.")
            ->json('data.id');

        $shown = $this->getJson("/api/v1/sales/invoices/{$invoice}")->json('data');
        $this->assertSame(['275.50', '60.00', 'partially_paid'], [$shown['balance_due'], $shown['allocated_amount'], $shown['payment_status']]);
        $this->assertNotContains('void', $shown['actions']);
        $this->assertSame([['active', 'advance', 'ADV-00001', '60.00']], array_map(fn (array $row) => [$row['status'], $row['source']['kind'], $row['source']['number'], $row['amount']], $shown['allocations']));

        $this->getJson("/api/v1/sales/advances/{$advance}")->assertJsonPath('data.balance', '40.00')->assertJsonPath('data.actions', ['apply']);

        // Applied, it is not reversed.
        $this->postJson("/api/v1/sales/advances/{$advance}/reverse")->assertStatus(409);

        $this->postJson("/api/v1/sales/allocations/{$allocation}/remove", ['reason' => 'Wrong invoice'])->assertOk();
        $this->postJson("/api/v1/sales/allocations/{$allocation}/remove")->assertStatus(409);

        $shown = $this->getJson("/api/v1/sales/invoices/{$invoice}")->json('data');
        $this->assertSame(['335.50', '0.00', 'unpaid'], [$shown['balance_due'], $shown['allocated_amount'], $shown['payment_status']]);
        $this->assertSame([['removed', 'Wrong invoice']], array_map(fn (array $row) => [$row['status'], $row['removal_reason']], $shown['allocations']));
        $this->getJson("/api/v1/sales/advances/{$advance}")->assertJsonPath('data.balance', '100.00')->assertJsonCount(1, 'data.allocations');

        $this->postJson("/api/v1/sales/advances/{$advance}/reverse", ['reason' => 'Paid back in cash'])->assertOk()->assertJsonPath('data.status', 'reversed');
        $this->assertSame('reversed', AccountTransaction::sole()->status);
        $this->assertSame([], $this->getJson("/api/v1/sales/invoices/{$invoice}/credit")->json('data'));
        $this->assertSame(1, $this->getJson('/api/v1/sales/advances?status=reversed')->json('pagination.total'));
    }

    public function test_an_order_takes_an_advance_in_its_currency(): void
    {
        $this->full();
        $order = $this->draft('orders');

        $this->assertContains('record_advance', $this->getJson("/api/v1/sales/orders/{$order}")->json('data.actions'));

        $this->advance(['contact_id' => null, 'currency' => null, 'sales_order_id' => $order, 'amount' => '50'])->assertCreated()
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.document.id', $order);

        $this->assertSame([['ADV-00001', '50.00']], array_map(fn (array $row) => [$row['number'], $row['balance']], $this->getJson("/api/v1/sales/orders/{$order}")->json('data.advances')));

        // Switched off in Settings > Sales: none taken, and the order offers none.
        $this->putJson('/api/v1/business-settings/sales', [
            'payment_terms' => 30, 'quotation_validity' => 30, 'proforma_validity' => 30, 'line_discounts' => true, 'document_discounts' => true, 'customer_advances' => false,
        ])->assertOk()->assertJsonPath('data.customer_advances', false);

        $this->assertNotContains('record_advance', $this->getJson("/api/v1/sales/orders/{$order}")->json('data.actions'));
        $this->advance()->assertStatus(409);
    }

    public function test_a_payment_over_the_balance_is_kept_as_an_advance_once_confirmed(): void
    {
        $this->full();
        $invoice = $this->issuedInvoice();

        $this->assertTrue($this->getJson("/api/v1/sales/invoices/{$invoice}")->json('data.keeps_overpayments'));

        $this->pay($invoice, $this->cash, '400.00')->assertUnprocessable()
            ->assertJsonPath('errors.amount.0', 'That comes to more than the USD 335.50 still due. Tick "Keep the extra as an advance" to keep the rest for the customer.');

        $this->pay($invoice, $this->cash, '400.00', ['keep_as_advance' => true])->assertCreated()
            ->assertJsonPath('message', 'Payment recorded. Receipt RCT-00001. USD 64.50 kept as advance ADV-00001.')
            ->assertJsonPath('data.amount', '335.50');

        $shown = $this->getJson("/api/v1/sales/invoices/{$invoice}")->json('data');
        $this->assertSame(['0.00', 'paid'], [$shown['balance_due'], $shown['payment_status']]);
        $this->assertSame([['ADV-00001', '64.50']], array_map(fn (array $row) => [$row['number'], $row['amount']], $shown['advances']));

        // All 400.00 reached the account: the payment and the advance.
        $this->assertSame(['335.50', '64.50'], AccountTransaction::orderBy('id')->pluck('amount')->all());

        // With advances off, an overpayment is refused as before.
        $this->putJson('/api/v1/business-settings/sales', [
            'payment_terms' => 30, 'quotation_validity' => 30, 'proforma_validity' => 30, 'line_discounts' => true, 'document_discounts' => true, 'customer_advances' => false,
        ])->assertOk();

        $second = $this->issuedInvoice();
        $this->pay($second, $this->cash, '400.00', ['keep_as_advance' => true])->assertUnprocessable()
            ->assertJsonPath('errors.amount.0', 'That comes to more than the USD 335.50 still due.');
    }

    public function test_the_statement_and_outstanding_figures_count_advances_and_credit_applied(): void
    {
        $this->full();
        $invoice = $this->issuedInvoice();
        $advance = $this->advance()->assertCreated()->json('data.id');
        $this->postJson("/api/v1/sales/invoices/{$invoice}/allocations", ['advance_id' => $advance, 'amount' => '60.00'])->assertCreated();

        // The advance lowers what the customer owes when it comes; applying it moves nothing.
        $rows = $this->getJson("/api/v1/reports/contact-statement?contact_id={$this->customer->id}")->assertOk()->json('data.rows');
        $this->assertSame(
            [['Opening Balance', null, '0.00'], ['Invoice', 'INV-00001', '335.50'], ['Advance', 'ADV-00001', '235.50']],
            array_map(fn (array $row) => [$row['type'], $row['number'], $row['balance']], $rows),
        );
        $this->assertSame('275.50', $rows[1]['outstanding']);

        $this->getJson('/api/v1/reports/outstanding-invoices')
            ->assertJsonPath('data.rows.0.outstanding', '275.50')
            ->assertJsonPath('data.rows.0.credit_notes', '60.00');
    }

    public function test_a_credit_notes_remaining_credit_is_applied_to_another_invoice(): void
    {
        $this->full();
        $first = $this->issuedInvoice();
        $this->pay($first, $this->cash, '335.50')->assertCreated();

        $chair = $this->lineOf($first, 0);
        $note  = $this->postJson("/api/v1/sales/invoices/{$first}/credit-notes", [
            'document_date' => '2026-10-28',
            'reason'        => 'One chair returned',
            'lines'         => [['source_line_id' => $chair, 'quantity' => '1', 'unit_price' => '150']],
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/sales/credit-notes/{$note}/issue")->assertOk();

        // Its own invoice was paid: the credit waits on the note.
        $this->getJson("/api/v1/sales/credit-notes/{$note}")->assertJsonPath('data.balance_due', '155.25');
        $this->assertContains('apply_credit', $this->getJson("/api/v1/sales/credit-notes/{$note}")->json('data.actions'));

        $second = $this->issuedInvoice();

        $this->assertSame([$second], array_column($this->getJson("/api/v1/sales/credit-notes/{$note}/invoices")->json('data'), 'id'));
        $this->assertSame([['credit_note', $note, '155.25']], array_map(fn (array $row) => [$row['kind'], $row['id'], $row['available']], $this->getJson("/api/v1/sales/invoices/{$second}/credit")->json('data')));

        // The paid invoice takes no credit.
        $this->postJson("/api/v1/sales/invoices/{$first}/allocations", ['credit_note_id' => $note, 'amount' => '10'])->assertStatus(409);

        $allocation = $this->postJson("/api/v1/sales/invoices/{$second}/allocations", ['credit_note_id' => $note, 'amount' => '155.25'])->assertCreated()->json('data.id');

        $this->getJson("/api/v1/sales/invoices/{$second}")->assertJsonPath('data.balance_due', '180.25');

        $shown = $this->getJson("/api/v1/sales/credit-notes/{$note}")->json('data');
        $this->assertSame(['0.00', '155.25', 'fully_applied'], [$shown['balance_due'], $shown['allocated_amount'], $shown['payment_status']]);
        $this->assertSame([], array_values(array_intersect(['refund', 'void', 'apply_credit'], $shown['actions'])));

        // While applied, neither the note nor the invoice is voided.
        $this->postJson("/api/v1/sales/credit-notes/{$note}/void", ['reason' => 'Mistake'])->assertStatus(409);
        $this->postJson("/api/v1/sales/invoices/{$second}/void", ['reason' => 'Mistake'])->assertStatus(409);

        $this->postJson("/api/v1/sales/allocations/{$allocation}/remove", ['reason' => 'Customer asked for a refund'])->assertOk();
        $this->getJson("/api/v1/sales/credit-notes/{$note}")->assertJsonPath('data.balance_due', '155.25')->assertJsonPath('data.payment_status', 'available');
        $this->getJson("/api/v1/sales/invoices/{$second}")->assertJsonPath('data.balance_due', '335.50');
    }
}
