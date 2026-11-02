<?php

namespace Tests\Feature;

use App\Models\User\Contact;
use App\Models\User\Item;
use App\Models\User\PaymentAccount;
use App\Models\User\TaxRate;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersPurchasesLite;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Purchases Lite in the pages every app shares (Doc 09 sections 17 to 19,
 * Phase C): the Documents page, the Dashboard, Reports, and the contact and
 * item profiles. A business with both Sales Lite and Purchases Lite on, its
 * invoice INV-00001 for 335.50 (MakesSalesDocuments), and purchases like this
 * one from Karim Stores:
 *
 *   10 reams of paper at 5.00   50.00 + VAT 7.50
 *   courier, no tax             12.00
 *   subtotal 62.00, tax 7.50, total 69.50
 */
class PurchasesInSharedPagesTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersPurchasesLite;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Contact $supplier;

    private Item $paper;

    private TaxRate $inputVat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $this->offerPurchasesLite();
        $this->user = $this->signIn();
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
        $this->setUpSales();

        $business       = $this->businessOf($this->user);
        $this->inputVat = $business->taxRates()->create(['name' => 'Input VAT', 'code' => 'IN15', 'rate' => '15', 'treatment' => 'standard', 'for_purchases' => true, 'effective_from' => '2026-01-01']);
        $this->supplier = $business->contacts()->create(['contact_type' => 'business', 'role' => 'supplier', 'name' => 'Karim Stores', 'supplier_payment_terms' => 15]);
        $this->paper    = $business->items()->create(['type' => 'physical', 'usage' => 'purchase', 'name' => 'Printer Paper', 'unit' => 'Ream', 'purchase_price' => '5.00', 'purchase_tax_id' => $this->inputVat->id]);
    }

    public function test_documents_list_purchases_beside_sales_documents(): void
    {
        $invoice  = $this->issuedInvoice();
        $purchase = $this->recordedPurchase(['document_date' => '2026-10-27']);
        $draft    = $this->draftPurchase(['document_date' => '2026-10-20', 'supplier_invoice_number' => 'KS-0900']);

        $rows = $this->getJson('/api/v1/documents')->assertOk()->assertJsonPath('pagination.total', 3)->json('data');

        $this->assertSame([['invoices', $invoice], ['purchases', $purchase], ['purchases', $draft]], array_map(fn (array $row) => [$row['kind'], $row['id']], $rows));
        $this->assertSame(
            ['PUR-00001', 'Karim Stores', 'Purchases Lite', 'recorded', 'unpaid', '69.50', '69.50', ['record_payment', 'void'], []],
            [$rows[1]['number'], $rows[1]['contact'], $rows[1]['source'], $rows[1]['status'], $rows[1]['payment_status'], $rows[1]['grand_total'], $rows[1]['balance_due'], $rows[1]['actions'], $rows[1]['delivery']],
        );
        $this->assertSame(['draft', 'not_applicable', null, ['edit', 'record', 'delete']], [$rows[2]['status'], $rows[2]['payment_status'], $rows[2]['balance_due'], $rows[2]['actions']]);

        $ids = fn (string $query) => array_map(fn (array $row) => "{$row['kind']}:{$row['id']}", $this->getJson("/api/v1/documents{$query}")->assertOk()->json('data'));

        $this->assertSame(["purchases:{$purchase}", "purchases:{$draft}"], $ids('?source=purchases'));
        $this->assertSame(["invoices:{$invoice}"], $ids('?source=sales'));
        $this->assertSame(["purchases:{$purchase}", "purchases:{$draft}"], $ids('?type=purchase'));
        $this->assertSame(["purchases:{$purchase}"], $ids('?status=recorded'));
        $this->assertSame(["purchases:{$draft}"], $ids('?status=draft'));
        $this->assertSame(["invoices:{$invoice}", "purchases:{$purchase}"], $ids('?payment_status=unpaid'));
        $this->assertSame(["purchases:{$purchase}"], $ids('?search=KS-1001'));
        $this->assertSame(["purchases:{$purchase}", "purchases:{$draft}"], $ids('?search=Printer'));
        $this->assertSame(["purchases:{$purchase}", "purchases:{$draft}"], $ids("?contact_id={$this->supplier->id}"));
        $this->assertSame([], $ids('?source=expenses'));
        $this->assertSame([], $ids('?type=purchase&status=issued'));

        $this->travelTo(Carbon::parse('2026-12-28 10:00:00', 'UTC'));
        $this->assertSame(["invoices:{$invoice}", "purchases:{$purchase}"], $ids('?overdue=1'));

        $csv = $this->get('/api/v1/documents/export?source=purchases')->assertOk()->streamedContent();
        $this->assertStringContainsString('2026-10-27,PUR-00001,Purchase,"Karim Stores","Purchases Lite",Recorded,Unpaid,69.50,USD,69.50', $csv);
    }

    public function test_the_dashboard_counts_purchases_beside_sales(): void
    {
        $this->issuedInvoice();
        $paid = $this->recordedPurchase();
        $this->recordedPurchase(['document_date' => '2026-10-05', 'supplier_invoice_number' => 'KS-0901', 'discount_type' => 'amount', 'discount_value' => '2.00']);
        $this->draftPurchase(['supplier_invoice_number' => 'KS-0902']);
        $bank = $this->account(['name' => 'Bank']);
        $this->paySupplier($paid, $bank, '69.50');

        $data = $this->getJson('/api/v1/dashboard')->assertOk()->json('data');

        // After discounts and without tax: 62.00 and 60.00 - the draft not at all.
        $this->assertSame(['amount' => '122.00', 'count' => 2], $data['purchases']);
        $this->assertSame(['label' => '28 Oct 2026', 'sales' => '295.00', 'purchases' => '62.00', 'expenses' => null], $data['chart'][27]);
        $this->assertSame(['label' => '05 Oct 2026', 'sales' => '0.00', 'purchases' => '60.00', 'expenses' => null], $data['chart'][4]);
        $this->assertSame('-69.50', collect($data['accounts'])->firstWhere('name', 'Bank')['balance']);

        $recent = collect($data['recent']);
        $this->assertSame(['Purchase', 'Sales Lite', 'Purchases Lite'], [$recent->firstWhere('type', 'Purchase')['type'], $recent->firstWhere('type', 'Invoice')['source'], $recent->firstWhere('type', 'Supplier Payment')['source']]);
        $this->assertSame('/purchases/payments/1', $recent->firstWhere('type', 'Supplier Payment')['link']);

        // With Sales off, only purchases count.
        $this->businessOf($this->user)->workspaceApps()->whereHas('app', fn ($query) => $query->where('family_key', 'sales'))->delete();
        $data = $this->getJson('/api/v1/dashboard')->assertOk()->json('data');
        $this->assertSame([null, null, '62.00'], [$data['sales'], $data['chart'][27]['sales'], $data['chart'][27]['purchases']]);
        $this->assertSame([], collect($data['recent'])->where('source', 'Sales Lite')->values()->all());
    }

    public function test_reports_cover_purchases(): void
    {
        $this->issuedInvoice();
        $first  = $this->recordedPurchase();
        $second = $this->recordedPurchase([
            'document_date' => '2026-10-05', 'supplier_invoice_number' => 'KS-0901', 'discount_type' => 'amount', 'discount_value' => '2.00',
            'lines'         => [['name' => 'Courier', 'quantity' => '1', 'unit_price' => '40.00']],
        ]);
        $this->draftPurchase(['supplier_invoice_number' => 'KS-0902']);
        $this->paySupplier($first, $this->account(['name' => 'Bank']), '30.00', ['reference' => 'TRX-9']);

        $catalog = collect($this->getJson('/api/v1/reports')->assertOk()->json('data'));
        $this->assertSame(['purchase-summary', 'outstanding-purchases', 'payments-made'], $catalog->where('category', 'Purchases & Payables')->pluck('key')->values()->all());
        $this->assertSame(['period', 'contact', 'side', 'currency'], $catalog->firstWhere('key', 'contact-statement')['filters']);

        // Purchase Summary: after discounts and without tax - 62.00 and 38.00 - the draft not at all.
        $this->getJson('/api/v1/reports/purchase-summary')
            ->assertOk()
            ->assertJsonPath('data.totals', ['group' => 'Total', 'purchases' => 2, 'subtotal' => '102.00', 'discounts' => '2.00', 'amount' => '100.00', 'tax' => '7.50', 'total' => '107.50']);
        $this->getJson('/api/v1/reports/purchase-summary?group_by=supplier')
            ->assertJsonPath('data.rows.0.group', 'Karim Stores')
            ->assertJsonPath('data.rows.0.link', "/contacts/{$this->supplier->id}");
        $items = $this->getJson('/api/v1/reports/purchase-summary?group_by=item')->assertOk()->json('data.rows');
        $this->assertSame([['Courier', 2, '50.00'], ['Printer Paper', 1, '50.00']], array_map(fn (array $row) => [$row['group'], $row['purchases'], $row['amount']], $items));
        $this->assertSame('No category', $this->getJson('/api/v1/reports/purchase-summary?group_by=category')->json('data.rows.0.group'));

        // Outstanding Purchases: the one past due first, the part paid one with what is left.
        $outstanding = $this->getJson('/api/v1/reports/outstanding-purchases')->assertOk()->json('data.rows');
        $this->assertSame(
            [['PUR-00002', '38.00', 'Unpaid', '1-30 Days Overdue'], ['PUR-00001', '39.50', 'Partially Paid', 'Not Due']],
            array_map(fn (array $row) => [$row['number'], $row['outstanding'], $row['payment_status'], $row['aging']], $outstanding),
        );
        $this->assertSame("/purchases/{$second}", $outstanding[0]['link']);

        $this->getJson('/api/v1/reports/payments-made')
            ->assertOk()
            ->assertJsonPath('data.rows.0.number', 'SPV-00001')
            ->assertJsonPath('data.rows.0.document', 'PUR-00001')
            ->assertJsonPath('data.rows.0.reference', 'TRX-9')
            ->assertJsonPath('data.totals.base_amount', '30.00');

        // Tax Summary: tax paid on purchases comes off the position; Tax Details lists the purchase.
        $tax = $this->getJson('/api/v1/reports/tax-summary')->assertOk()->json('data');
        $this->assertSame(['7.50', '-7.50'], [collect($tax['rows'])->firstWhere('tax', 'Input VAT 15%')['purchase_tax'], collect($tax['rows'])->firstWhere('tax', 'Input VAT 15%')['net_tax']]);
        $this->assertSame(['7.50', '33.00'], [collect($tax['summary'])->firstWhere('label', 'Purchase Tax')['value'], collect($tax['summary'])->firstWhere('label', 'Indicative Net Tax Position')['value']]);
        $this->assertSame(['Purchase', "/purchases/{$first}"], [
            collect($this->getJson('/api/v1/reports/tax-details')->json('data.rows'))->firstWhere('type', 'Purchase')['type'],
            collect($this->getJson('/api/v1/reports/tax-details')->json('data.rows'))->firstWhere('type', 'Purchase')['link'],
        ]);

        // A supplier's statement: what was bought, less what was paid them.
        $statement = $this->getJson("/api/v1/reports/contact-statement?contact_id={$this->supplier->id}")->assertOk()->json('data');
        $this->assertSame(
            [['Opening Balance', null, '0.00'], ['Purchase', '38.00', '38.00'], ['Purchase', '69.50', '107.50'], ['Payment', null, '77.50']],
            array_map(fn (array $row) => [$row['type'], $row['type'] === 'Purchase' ? $row['debit'] : null, $row['balance']], $statement['rows']),
        );
        $this->assertSame('Purchased', $statement['summary'][1]['label']);
        $this->getJson("/api/v1/reports/contact-statement?contact_id={$this->customer->id}")->assertJsonPath('data.rows.1.type', 'Invoice');
    }

    public function test_contact_and_item_profiles_show_purchases(): void
    {
        $invoice  = $this->issuedInvoice();
        $purchase = $this->recordedPurchase([
            'document_date' => '2026-10-27',
            'lines'         => [['item_id' => $this->chair->id, 'name' => 'Office Chair', 'quantity' => '4', 'unit' => 'Piece', 'unit_price' => '90.00']],
        ]);
        $draft = $this->draftPurchase(['document_date' => '2026-10-20', 'supplier_invoice_number' => 'KS-0900']);
        $this->paySupplier($purchase, $this->account(['name' => 'Bank']), '100.00');

        // The chair was sold on the invoice and bought on the purchase - newest first.
        $chair = $this->getJson("/api/v1/items/{$this->chair->id}/transactions")->assertOk()->assertJsonPath('pagination.total', 2)->json('data');
        $this->assertSame(
            [['invoices', $invoice, 'INV-00001', 'Uddin Traders', 'issued', '2.000', '270.00'], ['purchases', $purchase, 'PUR-00001', 'Karim Stores', 'recorded', '4.000', '360.00']],
            array_map(fn (array $row) => [$row['kind'], $row['document_id'], $row['number'], $row['contact'], $row['status'], $row['quantity'], $row['amount']], $chair),
        );
        $this->assertSame(
            [['purchases', $draft, null, 'draft', '50.00']],
            array_map(fn (array $row) => [$row['kind'], $row['document_id'], $row['number'], $row['status'], $row['amount']], $this->getJson("/api/v1/items/{$this->paper->id}/transactions")->json('data')),
        );
        $this->getJson("/api/v1/items/{$this->paper->id}")->assertJsonPath('data.in_use', true)->assertJsonPath('data.in_sales', false)->assertJsonPath('data.in_purchases', true);

        // The supplier: on purchases, never sold to - their documents and the payments made to them.
        $this->getJson("/api/v1/contacts/{$this->supplier->id}")->assertJsonPath('data.in_use', true)->assertJsonPath('data.in_sales', false)->assertJsonPath('data.in_purchases', true);
        $this->getJson("/api/v1/contacts/{$this->customer->id}")->assertJsonPath('data.in_sales', true)->assertJsonPath('data.in_purchases', false);
        $this->getJson("/api/v1/purchases/payments?contact_id={$this->supplier->id}")->assertOk()->assertJsonPath('pagination.total', 1)->assertJsonPath('data.0.purchase.number', 'PUR-00001');
        $this->getJson("/api/v1/purchases/payments?contact_id={$this->customer->id}")->assertOk()->assertJsonPath('pagination.total', 0);

        // One who is both has a statement for each side, and all their documents together.
        $this->customer->update(['role' => 'both']);
        $bought = $this->recordedPurchase(['contact_id' => $this->customer->id, 'document_date' => '2026-10-26', 'supplier_invoice_number' => 'UT-1']);

        $this->getJson("/api/v1/reports/contact-statement?contact_id={$this->customer->id}")->assertJsonPath('data.rows.1.type', 'Invoice');
        $supplierSide = $this->getJson("/api/v1/reports/contact-statement?contact_id={$this->customer->id}&side=supplier")->assertOk()->json('data');
        $this->assertSame(['Purchase', '69.50'], [$supplierSide['rows'][1]['type'], $supplierSide['rows'][1]['debit']]);
        $this->assertStringEndsWith(' · As supplier', $supplierSide['period']);
        $this->assertSame(
            ["invoices:{$invoice}", "purchases:{$bought}"],
            array_map(fn (array $row) => "{$row['kind']}:{$row['id']}", $this->getJson("/api/v1/documents?contact_id={$this->customer->id}")->json('data')),
        );
    }

    /** Karim Stores' purchase, with $fields over it. */
    private function purchase(array $fields = []): array
    {
        return [
            'contact_id'              => $this->supplier->id,
            'document_date'           => '2026-10-28',
            'currency'                => 'USD',
            'supplier_invoice_number' => 'KS-1001',
            'lines'                   => [
                ['item_id' => $this->paper->id, 'name' => 'Printer Paper', 'quantity' => '10', 'unit' => 'Ream', 'unit_price' => '5.00', 'tax_rate_id' => $this->inputVat->id],
                ['name' => 'Courier', 'quantity' => '1', 'unit_price' => '12.00'],
            ],
            ...$fields,
        ];
    }

    /** A draft purchase - its id. */
    private function draftPurchase(array $fields = []): int
    {
        return $this->postJson('/api/v1/purchases', $this->purchase($fields))->assertCreated()->json('data.id');
    }

    /** A recorded purchase - its id. */
    private function recordedPurchase(array $fields = []): int
    {
        $id = $this->draftPurchase($fields);

        $this->postJson("/api/v1/purchases/{$id}/record")->assertOk();

        return $id;
    }

    /** A payment of $amount against purchase $id, out of $account. */
    private function paySupplier(int $id, PaymentAccount $account, string $amount, array $fields = []): void
    {
        $this->postJson("/api/v1/purchases/{$id}/payments", [
            'payment_date'       => '2026-10-28',
            'payment_account_id' => $account->id,
            'amount'             => $amount,
            'method'             => 'bank_transfer',
            ...$fields,
        ])->assertCreated();
    }
}
