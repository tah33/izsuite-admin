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
use ZipArchive;

/**
 * What Doc 07 asks of every report beyond its figures: the filters a report
 * takes (section 3), opening the records behind a row and Export Excel
 * (section 4), tax columns only where tax applies (section 16), and Payment
 * Account Transactions as a report of its own (section 18). The base invoice
 * is INV-00001 for 335.50: two chairs, 270.00 + VAT 40.50, and a 25.00
 * delivery line without an item.
 */
class ReportActionsTest extends TestCase
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

    public function test_sales_summary_narrows_to_an_item_or_a_category_and_its_rows_open_their_documents(): void
    {
        $category = $this->postJson('/api/v1/item-categories', ['name' => 'Chairs'])->assertCreated()->json('data.id');
        $this->chair->update(['category_id' => $category]);
        $this->issuedInvoice();

        $chairs = ['invoices' => 1, 'subtotal' => '270.00', 'net_sales' => '270.00', 'tax' => '40.50', 'total' => '310.50'];

        $byItem = $this->getJson("/api/v1/reports/sales-summary?item_id={$this->chair->id}")->assertOk()->assertJsonPath('data.period', '1 Oct 2026 - 31 Oct 2026 · Office Chair')->json('data.rows.0');
        $this->assertSame($chairs, array_intersect_key($byItem, $chairs));

        $byCategory = $this->getJson("/api/v1/reports/sales-summary?category_id={$category}")->assertJsonPath('data.period', '1 Oct 2026 - 31 Oct 2026 · Chairs items')->json('data.rows.0');
        $this->assertSame($chairs, array_intersect_key($byCategory, $chairs));

        $this->getJson("/api/v1/reports/top-items?category_id={$category}")->assertJsonCount(1, 'data.rows')->assertJsonPath('data.rows.0.item', 'Office Chair');
        $this->getJson('/api/v1/reports/sales-summary?item_id=999999')->assertUnprocessable()->assertJsonValidationErrors('item_id');

        // A currency's row opens that currency's issued documents of the period.
        $this->getJson('/api/v1/reports/sales-summary?group_by=currency')
            ->assertJsonPath('data.rows.0.group', 'USD')
            ->assertJsonPath('data.rows.0.link', '/documents?status=issued&date_from=2026-10-01&date_to=2026-10-31&currency=USD');
        $this->getJson('/api/v1/reports/sales-trend')->assertJsonPath('data.rows.27.link', '/documents?status=issued&date_from=2026-10-28&date_to=2026-10-28');
    }

    public function test_outstanding_invoices_narrow_by_payment_status_and_show_how_overdue_each_is(): void
    {
        $unpaid = $this->issuedInvoice();
        $partly = $this->issuedInvoice();
        $this->pay($partly, $this->account(), '100')->assertCreated();

        $report = $this->getJson('/api/v1/reports/outstanding-invoices?payment_status=unpaid')->assertOk()->json('data');

        $this->assertSame([$unpaid], array_map(fn (array $row) => (int) basename($row['link']), $report['rows']));
        $this->assertSame('Not Due', $report['rows'][0]['aging']);
        $this->assertContains('aging', array_column($report['columns'], 'key'));
        $this->assertNotContains('withholding', array_column($report['columns'], 'key'));

        $this->getJson('/api/v1/reports/outstanding-invoices?payment_status=partially_paid')
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.link', "/sales/invoices/{$partly}")
            ->assertJsonPath('data.rows.0.payment_status', 'Partially Paid');
        $this->getJson('/api/v1/reports/outstanding-invoices?as_of=2026-12-28&payment_status=unpaid')->assertJsonPath('data.rows.0.aging', '31-60 Days Overdue');
        $this->getJson('/api/v1/reports/outstanding-invoices?payment_status=paid')->assertUnprocessable()->assertJsonValidationErrors('payment_status');

        // With withholding on, what was withheld shows again.
        $this->businessOf($this->user)->forceFill(['withholding_tax' => true])->save();
        $this->assertContains('withholding', array_column($this->getJson('/api/v1/reports/outstanding-invoices')->json('data.columns'), 'key'));
    }

    public function test_payments_received_narrow_to_one_account(): void
    {
        $cash = $this->account();
        $bank = $this->account(['type' => 'bank', 'name' => 'City Bank']);
        $this->pay($this->issuedInvoice(), $cash, '100')->assertCreated();
        $this->pay($this->issuedInvoice(), $bank, '60')->assertCreated();

        $this->getJson("/api/v1/reports/payments-received?payment_account_id={$bank->id}")
            ->assertOk()
            ->assertJsonPath('data.period', '1 Oct 2026 - 31 Oct 2026 · City Bank')
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.amount', '60.00');

        $other = $this->user->workspaces()->create(['name' => 'Second Business'])->paymentAccounts()->create(['type' => 'cash', 'name' => 'Till', 'currency' => 'USD']);
        $this->getJson("/api/v1/reports/payments-received?payment_account_id={$other->id}")->assertUnprocessable()->assertJsonValidationErrors('payment_account_id');
    }

    public function test_tax_summary_narrows_by_treatment_and_a_rate_opens_its_tax_details(): void
    {
        $invoice = $this->issuedInvoice();

        $this->getJson('/api/v1/reports/tax-summary?tax_treatment=exempt')->assertOk()->assertJsonPath('data.rows', [])->assertJsonPath('data.period', '1 Oct 2026 - 31 Oct 2026 · Exempt');

        $link = $this->getJson('/api/v1/reports/tax-summary')->json('data.rows.0.link');
        $this->assertStringStartsWith('/reports/tax-details?', $link);

        $details = $this->getJson('/api/v1/'.ltrim($link, '/'))->assertOk()->json('data');

        $this->assertSame('1 Oct 2026 - 31 Oct 2026 · VAT 15%', $details['period']);
        $this->assertSame(
            ['INV-00001', 'Invoice', 'Uddin Traders', 'VAT 15%', 'USD', '270.00', '40.50', '270.00', '40.50', "/sales/invoices/{$invoice}"],
            array_values(array_intersect_key($details['rows'][0], array_flip(['number', 'type', 'customer', 'tax', 'currency', 'taxable', 'tax_amount', 'base_taxable', 'base_tax', 'link']))),
        );
        $this->assertSame('40.50', $details['totals']['base_tax']);
    }

    public function test_payment_account_transactions_run_from_the_balance_brought_forward(): void
    {
        $cash    = $this->account(['opening_balance' => '500.00', 'opening_balance_date' => '2026-10-01']);
        $invoice = $this->issuedInvoice();
        $this->pay($invoice, $cash, '100')->assertCreated();
        $bounced = $this->pay($invoice, $cash, '50')->assertCreated()->json('data.id');
        $this->postJson("/api/v1/sales/payments/{$bounced}/reverse", ['reason' => 'Cheque bounced'])->assertOk();

        // With no account chosen, the first active one.
        $report = $this->getJson('/api/v1/reports/account-transactions')->assertOk()->assertJsonPath('data.sortable', false)->json('data');

        $this->assertSame('1 Oct 2026 - 31 Oct 2026 · Cash (USD)', $report['period']);
        $this->assertSame(
            [['Balance Brought Forward', null, null, '0.00', null], ['Opening Balance', '500.00', null, '500.00', 'Completed'], ['Customer Payment', '100.00', null, '600.00', 'Completed'], ['Customer Payment', '50.00', null, '600.00', 'Reversed']],
            array_map(fn (array $row) => [$row['type'], $row['money_in'], $row['money_out'], $row['balance'], $row['status']], $report['rows']),
        );
        $this->assertSame(['date' => 'Total', 'money_in' => '600.00', 'money_out' => '0.00', 'balance' => '600.00'], $report['totals']);

        $this->getJson("/api/v1/reports/account-transactions?payment_account_id={$cash->id}&date_from=2026-11-01&date_to=2026-11-30")
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.balance', '600.00');

        $other = $this->user->workspaces()->create(['name' => 'Second Business']);
        $this->getJson('/api/v1/reports/account-transactions', $this->pick($other))->assertUnprocessable()->assertJsonValidationErrors('payment_account_id');
    }

    public function test_a_report_downloads_as_an_excel_workbook(): void
    {
        $this->issuedInvoice();

        $response = $this->get('/api/v1/reports/sales-summary/excel', ['Accept' => 'application/json'])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertHeader('Content-Disposition', 'attachment; filename=sales-summary-2026-10-28.xlsx');

        [$workbook, $sheet] = $this->unzipped($response->getContent(), ['xl/workbook.xml', 'xl/worksheets/sheet1.xml']);

        $this->assertStringContainsString('<sheet name="Sales Summary"', $workbook);
        $this->assertStringContainsString('<t xml:space="preserve">Net Sales</t>', $sheet);
        // Money is a number in money's format; the totals row is bold.
        $this->assertStringContainsString('<c r="F2" s="3"><v>295.00</v></c>', $sheet);
        $this->assertStringContainsString('<c r="F3" s="4"><v>295.00</v></c>', $sheet);

        // A day is a date to the spreadsheet: 28 Oct 2026.
        $outstanding = $this->get('/api/v1/reports/outstanding-invoices/excel', ['Accept' => 'application/json'])->assertOk();
        [$rows] = $this->unzipped($outstanding->getContent(), ['xl/worksheets/sheet1.xml']);
        $this->assertStringContainsString('<c r="C2" s="2"><v>46323</v></c>', $rows);
    }

    public function test_tax_columns_hide_while_taxes_are_off_and_none_was_ever_charged(): void
    {
        $this->businessOf($this->user)->forceFill(['tax_mode' => 'disabled'])->save();
        $this->pay($this->issuedInvoice(['lines' => [['name' => 'Delivery', 'quantity' => '1', 'unit_price' => '25.00']]]), $this->account(), '10')->assertCreated();

        $this->assertNotContains('tax', array_column($this->getJson('/api/v1/reports/sales-summary')->json('data.columns'), 'key'));
        $this->assertNotContains('withheld', array_column($this->getJson('/api/v1/reports/payments-received')->json('data.columns'), 'key'));
        $this->assertNotContains('tax-summary', array_column($this->getJson('/api/v1/reports')->json('data'), 'key'));
    }

    /** @return list<string> the named files of the workbook in $bytes */
    private function unzipped(string $bytes, array $names): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $bytes);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $files = array_map(fn (string $name) => (string) $zip->getFromName($name), $names);
        $zip->close();
        unlink($path);

        return $files;
    }
}
