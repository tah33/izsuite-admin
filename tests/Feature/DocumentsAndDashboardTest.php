<?php

namespace Tests\Feature;

use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * The Documents page (Doc 06 sections 4 and 9) - every sales document as one
 * list, searched, filtered and exported - and the Dashboard (Doc 02): Sales
 * Lite's cards, chart, outstanding invoices and recent transactions, and the
 * accounts' balances.
 */
class DocumentsAndDashboardTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $this->user = $this->signIn();
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
        $this->setUpSales();
    }

    public function test_documents_lists_every_sales_document_newest_first_with_its_source_and_balance(): void
    {
        $invoice   = $this->issuedInvoice();
        $quotation = $this->draft('quotations', ['lines' => [['name' => 'Survey visit', 'quantity' => '1', 'unit_price' => '80.00']]]);
        $other     = $this->user->workspaces()->create(['name' => 'Second Business']);

        $rows = $this->getJson('/api/v1/documents')->assertOk()->assertJsonPath('pagination.total', 2)->json('data');

        $this->assertSame([$quotation, $invoice], array_column($rows, 'id'));
        $this->assertSame(['quotations', null, 'not_applicable', null, 'draft'], [$rows[0]['kind'], $rows[0]['number'], $rows[0]['payment_status'], $rows[0]['balance_due'], $rows[0]['status']]);
        $this->assertSame(['invoices', 'INV-00001', 'Sales Lite', 'unpaid', '335.50', 'Uddin Traders'], [$rows[1]['kind'], $rows[1]['number'], $rows[1]['source'], $rows[1]['payment_status'], $rows[1]['balance_due'], $rows[1]['contact']]);

        $this->getJson('/api/v1/documents?search=Survey')->assertJsonPath('data.0.id', $quotation)->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/documents?search=335.50')->assertJsonPath('data.0.id', $invoice)->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/documents?type=invoice&payment_status=unpaid')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/documents?type=credit_note')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/documents', $this->pick($other))->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/documents?type=order')->assertUnprocessable()->assertJsonValidationErrors('type');

        $this->travelTo(Carbon::parse('2026-12-28 10:00:00', 'UTC'));

        $this->getJson('/api/v1/documents?status=expired')->assertJsonPath('data.0.id', $quotation)->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/documents?status=draft')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/documents?overdue=1')->assertJsonPath('data.0.overdue', true)->assertJsonCount(1, 'data');
    }

    public function test_documents_filter_by_source_contact_and_currency_and_say_what_each_allows(): void
    {
        $invoice   = $this->issuedInvoice();
        $draft     = $this->draft('invoices');
        $karim     = $this->businessOf($this->user)->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Karim Stores']);
        $quotation = $this->draft('quotations', ['contact_id' => $karim->id]);

        // Only Sales keeps documents so far: the other apps' sources have none yet.
        $this->getJson('/api/v1/documents?source=sales')->assertJsonCount(3, 'data');
        $this->getJson('/api/v1/documents?source=purchases')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/documents?source=inventory')->assertUnprocessable()->assertJsonValidationErrors('source');
        $this->getJson("/api/v1/documents?contact_id={$karim->id}")->assertJsonPath('data.0.id', $quotation)->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/documents?currency=USD')->assertJsonCount(3, 'data');
        $this->getJson('/api/v1/documents?currency=EUR')->assertJsonCount(0, 'data');

        // What each record allows now, and how it can be sent - the page opens the record to do it.
        $rows = collect($this->getJson('/api/v1/documents')->json('data'))->keyBy('id');

        $this->assertSame([['record_payment', 'credit_note', 'void'], ['email', 'reminder', 'share']], [$rows[$invoice]['actions'], $rows[$invoice]['delivery']]);
        $this->assertSame([['edit', 'issue', 'delete'], []], [$rows[$draft]['actions'], $rows[$draft]['delivery']]);
        $this->assertSame([['edit', 'send', 'convert_to_proforma', 'convert_to_invoice', 'delete'], ['email']], [$rows[$quotation]['actions'], $rows[$quotation]['delivery']]);
    }

    public function test_documents_export_as_csv(): void
    {
        $this->issuedInvoice();

        $csv = $this->get('/api/v1/documents/export?type=invoice', ['Accept' => 'application/json'])->assertOk()->streamedContent();

        $this->assertStringContainsString('Date,Number,Type,Contact,"Source Application",Status,"Payment Status",Total,Currency,"Balance Due",Location', $csv);
        $this->assertStringContainsString('2026-10-28,INV-00001,Invoice,"Uddin Traders","Sales Lite",Issued,Unpaid,335.50,USD,335.50', $csv);
    }

    public function test_the_dashboard_shows_this_month_by_default(): void
    {
        $invoice = $this->issuedInvoice();
        $cash    = $this->account();

        // A minute apart, so the newest is plain.
        $this->travel(1)->minutes();
        $this->pay($invoice, $cash, '100')->assertCreated();
        $this->travel(1)->minutes();
        $this->draft('quotations');

        $data = $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.from', '2026-10-01')
            ->assertJsonPath('data.to', '2026-10-31')
            ->assertJsonPath('data.as_of', '2026-10-28')
            ->assertJsonPath('data.sales.net_sales', '295.00')
            ->assertJsonPath('data.sales.invoices', 1)
            ->assertJsonPath('data.sales.received', '100.00')
            ->assertJsonPath('data.sales.outstanding', '235.50')
            ->assertJsonPath('data.sales.outstanding_count', 1)
            ->assertJsonPath('data.sales.overdue_count', 0)
            ->assertJsonPath('data.sales.outstanding_rows.0.number', 'INV-00001')
            ->assertJsonPath('data.sales.outstanding_rows.0.overdue', false)
            ->assertJsonPath('data.accounts.0.balance', '100.00')
            ->json('data');

        $this->assertCount(31, $data['sales']['chart']);
        $this->assertSame(['label' => '28 Oct 2026', 'value' => '295.00'], $data['sales']['chart'][27]);
        $this->assertSame(['Quotation', 'Customer Payment', 'Invoice'], array_column($data['sales']['recent'], 'type'));
        $this->assertSame('/sales/payments/1', $data['sales']['recent'][1]['link']);

        $this->getJson('/api/v1/dashboard?range=this_year')->assertJsonPath('data.from', '2026-01-01')->assertJsonCount(12, 'data.sales.chart');
        $this->getJson('/api/v1/dashboard?date_from=2026-09-01&date_to=2026-09-30')->assertJsonPath('data.sales.net_sales', '0.00')->assertJsonPath('data.accounts.0.balance', '0.00');
        $this->getJson('/api/v1/dashboard?date_from=2026-09-01')->assertUnprocessable()->assertJsonValidationErrors('date_to');
    }

    public function test_an_overdue_invoice_shows_on_the_dashboard_first(): void
    {
        $late = $this->issuedInvoice(['document_date' => '2026-09-01']);
        $this->issuedInvoice();

        $this->getJson('/api/v1/dashboard?range=this_quarter')
            ->assertJsonPath('data.sales.overdue_count', 1)
            ->assertJsonPath('data.sales.overdue', '335.50')
            ->assertJsonPath('data.sales.outstanding', '671.00')
            ->assertJsonPath('data.sales.outstanding_rows.0.id', $late)
            ->assertJsonPath('data.sales.outstanding_rows.0.overdue', true);
    }
}
