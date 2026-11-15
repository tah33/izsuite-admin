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
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 16 Phase H3c: the Returns Report, Credit Notes and Refunds, the Invoice
 * and Payment Report and Customer Credit Exposure, on the full Sales app.
 * Wednesday 28 October 2026; each invoice is the base one, 335.50, due in 30
 * days - all Uddin Traders', whose credit limit is 400.00:
 *
 *   INV-00001  100.00 paid, and 50.00 of a 100.00 advance applied
 *   INV-00002  paid in full; CN-00001 credits its delivery, 25.00, refunded
 *   INV-00003  CN-00002 credits its delivery, 25.00, kept on it
 *   INV-00004  of 1 September, unpaid, overdue
 *
 * And an order of 4 chairs, delivered, three returns of them: one received
 * (damaged), one submitted (opened, 2 chairs), one rejected (unopened).
 */
class ReturnsAndCreditReportsTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private PaymentAccount $cash;

    /** @var array<int, int> the invoices' ids, in the story's order */
    private array $invoices = [];

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

    /** A credit note of invoice $id crediting its delivery, issued - its id. */
    private function creditDelivery(int $id): int
    {
        $note = $this->postJson("/api/v1/sales/invoices/{$id}/credit-notes", [
            'document_date' => '2026-10-28', 'reason' => 'Collected it himself',
            'lines'         => [['source_line_id' => $this->lineOf($id, 1), 'quantity' => '1', 'unit_price' => '25.00']],
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/sales/credit-notes/{$note}/issue")->assertOk();

        return $note;
    }

    /** The story's invoices, advance, credit notes and refund. */
    private function invoiceAndSettle(): void
    {
        $this->full();

        $this->invoices[] = $first = $this->issuedInvoice();
        $this->pay($first, $this->cash, '100.00')->assertCreated();
        $advance = $this->postJson('/api/v1/sales/advances', [
            'contact_id' => $this->customer->id, 'payment_date' => '2026-10-28', 'payment_account_id' => $this->cash->id, 'currency' => 'USD', 'amount' => '100.00', 'method' => 'cash',
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/sales/invoices/{$first}/allocations", ['advance_id' => $advance, 'amount' => '50.00'])->assertCreated();

        $this->invoices[] = $second = $this->issuedInvoice();
        $this->pay($second, $this->cash, '335.50')->assertCreated();
        $note = $this->creditDelivery($second);
        $this->postJson("/api/v1/sales/credit-notes/{$note}/refunds", ['payment_date' => '2026-10-28', 'payment_account_id' => $this->cash->id, 'amount' => '25.00'])->assertCreated();

        $this->invoices[] = $third = $this->issuedInvoice();
        $this->creditDelivery($third);

        $this->invoices[] = $this->issuedInvoice(['document_date' => '2026-09-01']);
    }

    /** An order of 4 chairs, delivered, and three returns of them. */
    private function returnChairs(): void
    {
        $this->full();

        $order = $this->draft('orders', ['lines' => [['item_id' => $this->chair->id, 'name' => 'Office Chair', 'quantity' => '4', 'unit' => 'Piece', 'unit_price' => '150.00', 'tax_rate_id' => $this->vat->id]]]);
        $this->postJson("/api/v1/sales/orders/{$order}/confirm")->assertOk();
        $chairs   = $this->getJson("/api/v1/sales/orders/{$order}")->json('data.lines.0.id');
        $delivery = $this->postJson("/api/v1/sales/orders/{$order}/deliveries", ['lines' => [['line_id' => $chairs, 'quantity' => '4']]])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/sales/deliveries/{$delivery}/deliver")->assertOk();

        $return = fn (string $quantity, string $condition) => $this->postJson("/api/v1/sales/orders/{$order}/returns", [
            'reason' => 'Not as described', 'condition' => $condition, 'delivery_id' => $delivery, 'lines' => [['line_id' => $chairs, 'quantity' => $quantity]],
        ])->assertCreated()->json('data.id');

        $received = $return('1', 'damaged');
        $this->postJson("/api/v1/sales/returns/{$received}/submit")->assertOk();
        $this->postJson("/api/v1/sales/returns/{$received}/authorise")->assertOk();
        $this->postJson("/api/v1/sales/returns/{$received}/receive", ['received_on' => '2026-10-28'])->assertOk();

        $this->postJson('/api/v1/sales/returns/'.$return('2', 'opened').'/submit')->assertOk();

        $rejected = $return('1', 'unopened');
        $this->postJson("/api/v1/sales/returns/{$rejected}/submit")->assertOk();
        $this->postJson("/api/v1/sales/returns/{$rejected}/reject", ['reason' => 'Not bought here'])->assertOk();
    }

    /** @return list<list<mixed>> the rows of $report, each as the values of $keys */
    private function rows(string $report, array $keys): array
    {
        return array_map(
            fn (array $row) => array_values(array_intersect_key(array_replace(array_flip($keys), $row), array_flip($keys))),
            $this->getJson("/api/v1/reports/{$report}")->assertOk()->json('data.rows'),
        );
    }

    public function test_the_four_come_with_the_full_app_each_for_a_role_that_may_see_it(): void
    {
        $keys   = fn () => array_column($this->getJson('/api/v1/reports')->assertOk()->json('data'), 'key');
        $theirs = ['invoice-payments', 'returns', 'credit-refunds', 'credit-exposure'];

        $this->assertSame([], array_intersect($theirs, $keys()));

        $this->full();
        $all = $keys();
        $this->assertSame(['invoice-payments', 'returns', 'credit-refunds'], array_slice($all, array_search('delivery-performance', $all, true) + 1, 3));
        $this->assertSame('credit-exposure', $all[array_search('price-list-usage', $all, true) + 1]);

        // The Returns Report shows no values: returns' View is enough. The others need sales seen.
        $member = User::factory()->create();
        $role   = $this->business->roles()->create(['name' => 'Returns Desk', 'permissions' => ['reports.view', 'sales.returns.view']]);
        $this->business->members()->create(['user_id' => $member->id, 'status' => 'active', 'role_id' => $role->id]);
        Sanctum::actingAs($member);

        $this->assertSame(['returns'], array_values(array_intersect($keys(), $theirs)));
        $this->getJson('/api/v1/reports/credit-exposure')->assertNotFound();

        $role->forceFill(['permissions' => [...$role->permissions, 'sales.invoices.view', 'sales.values.view']])->save();
        $this->assertSame($theirs, array_values(array_intersect($keys(), $theirs)));
    }

    public function test_the_returns_report_counts_each_return_where_it_stands(): void
    {
        $this->returnChairs();

        $keys = ['group', 'returns', 'quantity', 'open', 'received', 'rejected'];

        $this->assertSame([['Oct 2026', 3, '4.000', 1, 1, 1]], $this->rows('returns', $keys));
        $this->assertSame([
            ['Damaged', 1, '1.000', 0, 1, 0],
            ['Opened', 1, '2.000', 1, 0, 0],
            ['Unopened', 1, '1.000', 0, 0, 1],
        ], $this->rows('returns?group_by=condition', $keys));
        $this->assertSame([['Office Chair', 3, '4.000', 1, 1, 1]], $this->rows('returns?group_by=item', $keys));
        $this->assertSame([['Not decided', 3, '4.000', 1, 1, 1]], $this->rows('returns?group_by=resolution', $keys));

        $this->getJson('/api/v1/reports/returns?group_by=item')->assertJsonPath('data.totals', ['group' => 'Total', 'returns' => 3, 'quantity' => '4.000', 'open' => 1, 'received' => 1, 'rejected' => 1]);
    }

    public function test_credit_notes_and_refunds_follow_what_became_of_the_credit(): void
    {
        $this->invoiceAndSettle();

        $keys = ['group', 'notes', 'credited', 'applied', 'refunded', 'available'];

        // CN-00001 went back as money; CN-00002 stayed on its invoice.
        $this->assertSame([['Oct 2026', 2, '50.00', '25.00', '25.00', '0.00']], $this->rows('credit-refunds', $keys));
        $this->assertSame([['Uddin Traders', 2, '50.00', '25.00', '25.00', '0.00']], $this->rows('credit-refunds?group_by=customer', $keys));
    }

    public function test_the_invoice_and_payment_report_shows_how_each_invoice_was_settled(): void
    {
        $this->invoiceAndSettle();

        $keys = ['number', 'total', 'payments', 'advances', 'credit', 'outstanding', 'status'];

        // October's three; INV-00004 is September's.
        $this->assertSame([
            ['INV-00001', '335.50', '100.00', '50.00', '0.00', '185.50', 'Partially Paid'],
            ['INV-00002', '335.50', '335.50', '0.00', '0.00', '0.00', 'Paid'],
            ['INV-00003', '335.50', '0.00', '0.00', '25.00', '310.50', 'Partially Paid'],
        ], $this->rows('invoice-payments', $keys));

        $year = $this->getJson('/api/v1/reports/invoice-payments?range=this_year')->assertOk()->json('data');
        $this->assertSame(['INV-00004', '0.00', '335.50', 'Unpaid'], [$year['rows'][0]['number'], $year['rows'][0]['payments'], $year['rows'][0]['outstanding'], $year['rows'][0]['status']]);
        $this->assertSame(['1342.00', '435.50', '50.00', '25.00', '831.50'], [$year['totals']['total'], $year['totals']['payments'], $year['totals']['advances'], $year['totals']['credit'], $year['totals']['outstanding']]);
        $this->assertSame("/sales/invoices/{$this->invoices[0]}", $year['rows'][1]['link']);
    }

    public function test_credit_exposure_is_each_customers_as_their_card_has_it(): void
    {
        $this->invoiceAndSettle();
        $this->putJson("/api/v1/sales/customers/{$this->customer->id}/credit", ['credit_limit' => '400.00', 'credit_sales' => true, 'credit_action' => 'warn', 'credit_warning' => null])->assertOk();

        // A customer with a limit and nothing owed shows too; one with neither does not.
        $other = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Karim Stores', 'credit_limit' => '1000.00']);
        $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Quiet Customer']);

        $keys = ['customer', 'limit', 'outstanding', 'overdue', 'unapplied', 'exposure', 'available', 'standing'];

        // 831.50 owed, 335.50 of it overdue, less the 50.00 left of the advance.
        $this->assertSame([
            ['Uddin Traders', '400.00', '831.50', '335.50', '50.00', '781.50', '-381.50', 'Over the limit'],
            ['Karim Stores', '1000.00', '0.00', '0.00', '0.00', '0.00', '1000.00', null],
        ], $this->rows('credit-exposure', $keys));

        $report = $this->getJson('/api/v1/reports/credit-exposure')->assertOk()->json('data');
        $this->assertSame(['As of 28 Oct 2026', "/contacts/{$other->id}"], [$report['period'], $report['rows'][1]['link']]);
        // The card's figures for the same customer.
        $card = $this->getJson("/api/v1/sales/customers/{$this->customer->id}/credit")->assertOk()->json('data');
        $this->assertSame([$card['exposure'], $card['available']], ['781.50', '-381.50']);
        $this->assertNotContains('orders', array_column($report['columns'], 'key'));
    }
}
