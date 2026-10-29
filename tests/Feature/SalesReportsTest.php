<?php

namespace Tests\Feature;

use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Basic Reports for Sales Lite (Doc 07, Doc 08 section 22), over one story:
 * INV-00001 for 335.50 (295.00 + VAT 40.50, due 27 Nov), 100.00 paid on it,
 * and CN-00001 crediting its 25.00 delivery - so it owes 210.50.
 */
class SalesReportsTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private int $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $this->user = $this->signIn();
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
        $this->setUpSales();

        $this->invoice = $this->issuedInvoice();
        $this->pay($this->invoice, $this->account(), '100')->assertCreated();

        $note = $this->postJson("/api/v1/sales/invoices/{$this->invoice}/credit-notes", [
            'document_date' => '2026-10-28', 'reason' => 'Collected it himself',
            'lines'         => [['source_line_id' => $this->lineOf($this->invoice, 1), 'quantity' => '1', 'unit_price' => '25.00']],
        ])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/sales/credit-notes/{$note}/issue")->assertOk();
    }

    public function test_the_catalog_lists_sales_reports_and_tax_summary_while_taxes_are_used(): void
    {
        $catalog = $this->getJson('/api/v1/reports')->assertOk()->json('data');

        $this->assertSame(
            ['sales-summary', 'sales-trend', 'top-items', 'top-customers', 'outstanding-invoices', 'payments-received', 'credit-notes', 'tax-summary', 'customer-statement', 'account-transactions'],
            array_column($catalog, 'key'),
        );
        $this->assertSame(['Sales & Receivables', 'Taxes', '/payment-accounts'], [$catalog[0]['category'], $catalog[7]['category'], $catalog[9]['link']]);

        $this->getJson('/api/v1/reports/no-such-report')->assertNotFound();
        $this->getJson('/api/v1/reports/account-transactions')->assertNotFound();
    }

    public function test_sales_summary_takes_credit_notes_off_and_groups_as_asked(): void
    {
        $this->getJson('/api/v1/reports/sales-summary?group_by=month')
            ->assertOk()
            ->assertJsonPath('data.period', '1 Oct 2026 - 31 Oct 2026')
            ->assertJsonPath('data.rows.0', ['group' => 'Oct 2026', 'invoices' => 1, 'subtotal' => '295.00', 'discounts' => '0.00', 'credit_notes' => '-25.00', 'net_sales' => '270.00', 'tax' => '40.50', 'total' => '310.50', 'link' => null])
            ->assertJsonPath('data.totals.net_sales', '270.00');

        $items = $this->getJson('/api/v1/reports/sales-summary?group_by=item')->assertOk()->json('data.rows');
        $this->assertSame([['Office Chair', '270.00', '40.50'], ['Delivery', '0.00', '0.00']], array_map(fn (array $row) => [$row['group'], $row['net_sales'], $row['tax']], $items));

        $this->getJson('/api/v1/reports/sales-summary?group_by=customer&range=this_year')->assertJsonPath('data.rows.0.group', 'Uddin Traders')->assertJsonPath('data.rows.0.net_sales', '270.00');
        $this->getJson('/api/v1/reports/sales-summary?group_by=day&date_from=2024-01-01&date_to=2026-10-28')->assertUnprocessable()->assertJsonValidationErrors('group_by');
        $this->getJson('/api/v1/reports/sales-summary?date_from=2026-10-28')->assertUnprocessable()->assertJsonValidationErrors('date_to');
    }

    public function test_trend_items_and_customers(): void
    {
        $trend = $this->getJson('/api/v1/reports/sales-trend')->assertOk()->assertJsonPath('data.chart', ['label' => 'period', 'value' => 'net_sales'])->json('data');
        $this->assertCount(31, $trend['rows']);
        $this->assertSame(['28 Oct 2026', 1, '295.00', '-25.00', '270.00'], array_values(array_diff_key($trend['rows'][27], ['link' => true])));

        $this->getJson('/api/v1/reports/top-items')
            ->assertJsonPath('data.rows.0', ['item' => 'Office Chair', 'quantity' => '2.000', 'invoices' => 1, 'net_sales' => '270.00', 'credit_quantity' => '0', 'credit_amount' => '0.00', 'net' => '270.00', 'link' => "/items/{$this->chair->id}"])
            ->assertJsonPath('data.rows.1.credit_amount', '25.00')
            ->assertJsonPath('data.rows.1.net', '0.00');

        $this->getJson('/api/v1/reports/top-customers')
            ->assertJsonPath('data.rows.0.customer', 'Uddin Traders')
            ->assertJsonPath('data.rows.0.net_sales', '270.00')
            ->assertJsonPath('data.rows.0.received', '100.00')
            ->assertJsonPath('data.rows.0.outstanding', '210.50');
    }

    public function test_outstanding_invoices_are_worked_out_as_of_a_day_with_their_age(): void
    {
        $this->getJson('/api/v1/reports/outstanding-invoices')
            ->assertOk()
            ->assertJsonPath('data.period', 'As of 28 Oct 2026')
            ->assertJsonPath('data.rows.0.number', 'INV-00001')
            ->assertJsonPath('data.rows.0.outstanding', '210.50')
            ->assertJsonPath('data.rows.0.payments', '100.00')
            ->assertJsonPath('data.rows.0.credit_notes', '25.00')
            ->assertJsonPath('data.rows.0.age', 'Not due')
            ->assertJsonPath('data.rows.0.payment_status', 'Partially Paid')
            ->assertJsonPath('data.rows.0.link', "/sales/invoices/{$this->invoice}")
            ->assertJsonPath('data.summary.0', ['label' => 'Not Due', 'value' => '210.50', 'currency' => 'USD']);

        $this->getJson('/api/v1/reports/outstanding-invoices?as_of=2026-12-28')
            ->assertJsonPath('data.rows.0.age', '31 days overdue')
            ->assertJsonPath('data.summary.2', ['label' => '31-60 Days Overdue', 'value' => '210.50', 'currency' => 'USD']);

        $this->getJson('/api/v1/reports/outstanding-invoices?as_of=2026-10-27')->assertJsonPath('data.rows', []);
    }

    public function test_payments_received_credit_notes_and_tax_summary(): void
    {
        $this->getJson('/api/v1/reports/payments-received')
            ->assertJsonPath('data.rows.0.number', 'RCT-00001')
            ->assertJsonPath('data.rows.0.amount', '100.00')
            ->assertJsonPath('data.summary.2', ['label' => 'Net Received', 'value' => '100.00', 'currency' => 'USD']);

        $this->getJson('/api/v1/reports/credit-notes')
            ->assertJsonPath('data.rows.0.number', 'CN-00001')
            ->assertJsonPath('data.rows.0.invoice', 'INV-00001')
            ->assertJsonPath('data.rows.0.applied', '25.00')
            ->assertJsonPath('data.rows.0.status', 'Fully Applied');

        $this->getJson('/api/v1/reports/tax-summary')
            ->assertOk()
            ->assertJsonPath('data.rows.0', ['tax' => 'VAT 15%', 'treatment' => 'Standard', 'sales_taxable' => '270.00', 'sales_tax' => '40.50', 'credit_taxable' => '0.00', 'credit_tax' => '0.00', 'net_taxable' => '270.00', 'net_tax' => '40.50', 'link' => null])
            ->assertJsonPath('data.summary.6', ['label' => 'Indicative Net Tax Position', 'value' => '40.50', 'currency' => 'USD']);
    }

    public function test_a_customer_statement_runs_its_balance_from_the_opening_one(): void
    {
        $rows = $this->getJson("/api/v1/reports/customer-statement?contact_id={$this->customer->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Statement for Uddin Traders')
            ->assertJsonPath('data.summary.4', ['label' => 'Closing Balance', 'value' => '210.50', 'currency' => 'USD'])
            ->json('data.rows');

        $this->assertSame(
            [['Opening Balance', null, '0.00'], ['Invoice', 'INV-00001', '335.50'], ['Credit Note', 'CN-00001', '310.50'], ['Payment', 'RCT-00001', '210.50']],
            array_map(fn (array $row) => [$row['type'], $row['number'], $row['balance']], $rows),
        );
        $this->assertSame('210.50', $rows[1]['outstanding']);

        $this->getJson("/api/v1/reports/customer-statement?contact_id={$this->customer->id}&date_from=2026-11-01&date_to=2026-11-30")
            ->assertJsonPath('data.rows.0.balance', '210.50')
            ->assertJsonCount(1, 'data.rows');

        $this->getJson('/api/v1/reports/customer-statement')->assertUnprocessable()->assertJsonValidationErrors('contact_id');
    }

    public function test_a_report_downloads_as_csv_and_as_pdf(): void
    {
        $csv = $this->get('/api/v1/reports/sales-summary/export', ['Accept' => 'application/json'])->assertOk()->streamedContent();

        $this->assertStringContainsString('Month,Invoices,Subtotal,Discounts,"Credit Notes","Net Sales",Tax,"Grand Total"', $csv);
        $this->assertStringContainsString('"Oct 2026",1,295.00,0.00,-25.00,270.00,40.50,310.50', $csv);

        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200)]);

        $this->get('/api/v1/reports/outstanding-invoices/pdf', ['Accept' => 'application/json'])
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=outstanding-invoices-2026-10-28.pdf');

        // A wide report is turned on its side, and shows its rows.
        Http::assertSent(fn ($request) => collect($request->data())->firstWhere('name', 'paperWidth')['contents'] === '11.69'
            && str_contains((string) collect($request->data())->firstWhere('filename', 'index.html')['contents'], 'INV-00001'));
    }
}
