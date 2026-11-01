<?php

namespace Tests\Feature;

use App\Models\User\Contact;
use App\Models\User\ExpenseCategory;
use App\Models\User\PaymentAccount;
use App\Models\User\TaxRate;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersExpensesLite;
use Tests\Concerns\OffersPurchasesLite;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Expenses Lite in the pages every app shares (Doc 10 sections 16 to 18,
 * Phase C): the Documents page, the Dashboard, Reports and the contact
 * profile. A business with all three Lite apps on, its invoice INV-00001 for
 * 335.50 (MakesSalesDocuments), and expenses like this one to City Power:
 *
 *   utilities, electricity   200.00 + Input VAT 30.00 (can be claimed back)
 *   meals and travel, hotel  100.00 + Hotel Tax 10.00 (cannot)
 *   subtotal 300.00, tax 40.00, total 340.00 - an expense amount of 310.00
 *
 * and a taxi to Metro Taxi, typed in: transport, 2 at 12.50, no tax, 25.00.
 */
class ExpensesInSharedPagesTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersExpensesLite;
    use OffersPurchasesLite;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Contact $payee;

    private TaxRate $inputVat;

    private TaxRate $hotelTax;

    private ExpenseCategory $utilities;

    private ExpenseCategory $travel;

    private ExpenseCategory $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $this->offerPurchasesLite();
        $this->offerExpensesLite();
        $this->user = $this->signIn();
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
        $this->setUpSales();

        $business        = $this->businessOf($this->user);
        $this->inputVat  = $business->taxRates()->create(['name' => 'Input VAT', 'code' => 'IN15', 'rate' => '15', 'treatment' => 'standard', 'for_expenses' => true, 'recoverability' => 'recoverable', 'effective_from' => '2026-01-01']);
        $this->hotelTax  = $business->taxRates()->create(['name' => 'Hotel Tax', 'code' => 'HT10', 'rate' => '10', 'treatment' => 'standard', 'for_expenses' => true, 'recoverability' => 'non_recoverable', 'effective_from' => '2026-01-01']);
        $this->payee     = $business->contacts()->create(['contact_type' => 'business', 'role' => 'supplier', 'name' => 'City Power']);
        $this->utilities = $business->expenseCategories()->create(['name' => 'Utilities']);
        $this->travel    = $business->expenseCategories()->create(['name' => 'Meals and Travel']);
        $this->transport = $business->expenseCategories()->create(['name' => 'Transport']);
    }

    public function test_documents_list_expenses_beside_sales_documents(): void
    {
        $invoice = $this->issuedInvoice();
        $power   = $this->recordedExpense();
        $taxi    = $this->recordedExpense($this->taxi());
        $draft   = $this->draftExpense(['document_date' => '2026-10-15', 'receipt_number' => 'CP-0990']);
        $this->payExpense($power, $this->account(), '100.00');

        $rows = $this->getJson('/api/v1/documents')->assertOk()->assertJsonPath('pagination.total', 4)->json('data');

        $this->assertSame([['invoices', $invoice], ['expenses', $power], ['expenses', $taxi], ['expenses', $draft]], array_map(fn (array $row) => [$row['kind'], $row['id']], $rows));
        $this->assertSame(
            ['EXP-00001', 'City Power', 'Expenses Lite', 'recorded', 'partially_paid', '340.00', '240.00', ['record_payment'], []],
            [$rows[1]['number'], $rows[1]['contact'], $rows[1]['source'], $rows[1]['status'], $rows[1]['payment_status'], $rows[1]['grand_total'], $rows[1]['balance_due'], $rows[1]['actions'], $rows[1]['delivery']],
        );
        $this->assertSame(['Metro Taxi', '25.00', ['record_payment', 'void']], [$rows[2]['contact'], $rows[2]['grand_total'], $rows[2]['actions']]);
        $this->assertSame(['draft', 'not_applicable', null, ['edit', 'record', 'delete']], [$rows[3]['status'], $rows[3]['payment_status'], $rows[3]['balance_due'], $rows[3]['actions']]);

        $ids = fn (string $query) => array_map(fn (array $row) => "{$row['kind']}:{$row['id']}", $this->getJson("/api/v1/documents{$query}")->assertOk()->json('data'));

        $this->assertSame(["expenses:{$power}", "expenses:{$taxi}", "expenses:{$draft}"], $ids('?source=expenses'));
        $this->assertSame(["expenses:{$power}", "expenses:{$taxi}", "expenses:{$draft}"], $ids('?type=expense'));
        $this->assertSame(["invoices:{$invoice}"], $ids('?source=sales'));
        $this->assertSame([], $ids('?source=purchases'));
        $this->assertSame(["expenses:{$power}", "expenses:{$taxi}"], $ids('?status=recorded'));
        $this->assertSame(["invoices:{$invoice}", "expenses:{$taxi}"], $ids('?payment_status=unpaid'));
        // By receipt number, a typed-in payee, a line's category or description.
        $this->assertSame(["expenses:{$power}"], $ids('?search=CP-1001'));
        $this->assertSame(["expenses:{$taxi}"], $ids('?search=Metro'));
        $this->assertSame(["expenses:{$power}", "expenses:{$draft}"], $ids('?search=Hotel'));
        $this->assertSame(["expenses:{$taxi}"], $ids('?search=Transport'));
        $this->assertSame(["expenses:{$power}", "expenses:{$draft}"], $ids("?contact_id={$this->payee->id}"));
        $this->assertSame([], $ids('?type=expense&status=issued'));
        $this->assertSame([], $ids('?type=expense&payment_status=refunded'));

        $this->travelTo(Carbon::parse('2026-12-28 10:00:00', 'UTC'));
        $this->assertSame(["invoices:{$invoice}", "expenses:{$power}", "expenses:{$taxi}"], $ids('?overdue=1'));

        $csv = $this->get('/api/v1/documents/export?source=expenses')->assertOk()->streamedContent();
        $this->assertStringContainsString('2026-10-27,EXP-00001,Expense,"City Power","Expenses Lite",Recorded,"Partially Paid",340.00,USD,240.00', $csv);
        $this->assertStringContainsString('2026-10-20,EXP-00002,Expense,"Metro Taxi","Expenses Lite",Recorded,Unpaid,25.00,USD,25.00', $csv);
    }

    public function test_the_dashboard_counts_expenses_without_the_tax_they_claim_back(): void
    {
        $this->issuedInvoice();
        $power = $this->recordedExpense();
        $this->recordedExpense($this->taxi());
        $this->draftExpense(['receipt_number' => 'CP-0990']);
        $this->payExpense($power, $this->account(['name' => 'Bank']), '100.00');

        $data = $this->getJson('/api/v1/dashboard')->assertOk()->json('data');

        // 310.00 - the hotel tax stays in, the claimable VAT does not - and 25.00; the draft not at all.
        $this->assertSame(['amount' => '335.00', 'count' => 2], $data['expenses']);
        $this->assertSame(['label' => '27 Oct 2026', 'sales' => '0.00', 'purchases' => '0.00', 'expenses' => '310.00'], $data['chart'][26]);
        $this->assertSame('25.00', $data['chart'][19]['expenses']);
        $this->assertSame('-100.00', collect($data['accounts'])->firstWhere('name', 'Bank')['balance']);

        $recent = collect($data['recent']);
        $this->assertSame(['Expenses Lite', 'City Power'], [$recent->firstWhere('type', 'Expense')['source'], $recent->firstWhere('type', 'Expense Payment')['contact']]);
        $this->assertSame(['/expenses/payments/1', "/expenses/{$power}"], [$recent->firstWhere('type', 'Expense Payment')['link'], $recent->firstWhere('number', 'EXP-00001')['link']]);
        $this->assertSame('Metro Taxi', $recent->firstWhere('number', 'EXP-00002')['contact']);

        // With Expenses off, no card, no series and no expense rows.
        $this->businessOf($this->user)->workspaceApps()->whereHas('app', fn ($query) => $query->where('family_key', 'expenses'))->delete();
        $data = $this->getJson('/api/v1/dashboard')->assertOk()->json('data');
        $this->assertSame([null, null], [$data['expenses'], $data['chart'][26]['expenses']]);
        $this->assertSame([], collect($data['recent'])->where('source', 'Expenses Lite')->values()->all());
    }

    public function test_expense_reports_keep_claimable_tax_apart(): void
    {
        $this->issuedInvoice();
        $power = $this->recordedExpense();
        $this->recordedExpense($this->taxi());
        $this->draftExpense(['receipt_number' => 'CP-0990']);
        $this->payExpense($power, $this->account(), '100.00', ['reference' => 'CHQ-77']);

        $catalog = collect($this->getJson('/api/v1/reports')->assertOk()->json('data'));
        $this->assertSame(['expense-summary', 'expenses-by-category'], $catalog->where('category', 'Expenses')->pluck('key')->values()->all());
        $this->assertSame(['period', 'location', 'contact', 'currency', 'expense_category'], $catalog->firstWhere('key', 'expense-summary')['filters']);

        // Expense Summary: each recorded expense, oldest first, in its own currency.
        $summary = $this->getJson('/api/v1/reports/expense-summary')->assertOk()->json('data');
        $this->assertSame(
            [['EXP-00002', 'Transport', 'Metro Taxi', 'Taxi', '25.00', '0.00', '25.00', 'Unpaid'], ['EXP-00001', 'Utilities, Meals and Travel', 'City Power', 'Electricity; Hotel', '310.00', '30.00', '340.00', 'Partially Paid']],
            array_map(fn (array $row) => [$row['number'], $row['category'], $row['payee'], $row['description'], $row['amount'], $row['tax'], $row['total'], $row['payment_status']], $summary['rows']),
        );
        $this->assertSame("/expenses/{$power}", $summary['rows'][1]['link']);
        $this->assertSame(['expense_date' => 'Total', 'base_amount' => '335.00'], $summary['totals']);
        $this->assertSame([['Expense Amount', '335.00'], ['Recoverable Tax', '30.00'], ['Total', '365.00']], array_map(fn (array $item) => [$item['label'], $item['value']], $summary['summary']));
        $this->assertArrayNotHasKey('base_total', $summary['rows'][0]);

        // One category: only each expense's lines in it.
        $this->getJson("/api/v1/reports/expense-summary?expense_category_id={$this->travel->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.rows')
            ->assertJsonPath('data.rows.0.category', 'Meals and Travel')
            ->assertJsonPath('data.rows.0.amount', '110.00')
            ->assertJsonPath('data.rows.0.total', '110.00');
        $this->getJson('/api/v1/reports/expense-summary?expense_category_id=999')->assertStatus(422)->assertJsonValidationErrors('expense_category_id');

        // Expenses by Category: what paid City Power's expense shared by its lines' totals - 230 and 110 of 340.
        $byCategory = $this->getJson('/api/v1/reports/expenses-by-category')->assertOk()->json('data');
        $this->assertSame(
            [['Utilities', 1, '200.00', '30.00', '67.65', '162.35'], ['Meals and Travel', 1, '110.00', '0.00', '32.35', '77.65'], ['Transport', 1, '25.00', '0.00', '0.00', '25.00']],
            array_map(fn (array $row) => [$row['group'], $row['expenses'], $row['amount'], $row['tax'], $row['paid'], $row['outstanding']], $byCategory['rows']),
        );
        $this->assertSame(['group' => 'Total', 'expenses' => 2, 'amount' => '335.00', 'tax' => '30.00', 'paid' => '100.00', 'withheld' => '0.00', 'outstanding' => '265.00'], $byCategory['totals']);

        $payees = $this->getJson('/api/v1/reports/expenses-by-category?group_by=payee')->assertOk()->json('data.rows');
        $this->assertSame(
            [['City Power', '310.00', '100.00', "/contacts/{$this->payee->id}"], ['Metro Taxi', '25.00', '0.00', null]],
            array_map(fn (array $row) => [$row['group'], $row['amount'], $row['paid'], $row['link']], $payees),
        );
        $this->getJson('/api/v1/reports/expenses-by-category?group_by=month')->assertOk()->assertJsonCount(1, 'data.rows')->assertJsonPath('data.rows.0.amount', '335.00');

        // Payments Made: expense payments too, named for both apps.
        $made = $this->getJson('/api/v1/reports/payments-made')->assertOk()->json('data');
        $this->assertSame(
            ['EPV-00001', 'Expense Payment', 'City Power', 'EXP-00001', 'CHQ-77', '100.00', '/expenses/payments/1'],
            [$made['rows'][0]['number'], $made['rows'][0]['type'], $made['rows'][0]['payee'], $made['rows'][0]['document'], $made['rows'][0]['reference'], $made['rows'][0]['amount'], $made['rows'][0]['link']],
        );
        $this->assertSame(['Supplier or Payee', 'Purchase or Expense'], [collect($made['columns'])->firstWhere('key', 'payee')['label'], collect($made['columns'])->firstWhere('key', 'document')['label']]);
        $this->getJson("/api/v1/reports/payments-made?contact_id={$this->customer->id}")->assertJsonCount(0, 'data.rows');

        // Tax Summary: the claimable VAT comes off the position, the hotel tax does not.
        $tax  = $this->getJson('/api/v1/reports/tax-summary')->assertOk()->json('data');
        $rate = fn (string $name) => collect($tax['rows'])->firstWhere('tax', $name);
        $this->assertSame([['30.00', '200.00', '-30.00'], ['10.00', '100.00', '0.00']], [
            [$rate('Input VAT 15%')['expense_tax'], $rate('Input VAT 15%')['expense_taxable'], $rate('Input VAT 15%')['net_tax']],
            [$rate('Hotel Tax 10%')['expense_tax'], $rate('Hotel Tax 10%')['expense_taxable'], $rate('Hotel Tax 10%')['net_tax']],
        ]);
        $figure = fn (string $label) => collect($tax['summary'])->firstWhere('label', $label)['value'];
        $this->assertSame(['40.00', '10.00', '10.50'], [$figure('Expense Tax'), $figure('Expense Tax Not Claimable'), $figure('Indicative Net Tax Position')]);

        $details = collect($this->getJson('/api/v1/reports/tax-details')->assertOk()->json('data.rows'))->where('type', 'Expense')->values();
        $this->assertSame([['EXP-00001', 'City Power', 'Input VAT 15%', "/expenses/{$power}"], ['EXP-00001', 'City Power', 'Hotel Tax 10%', "/expenses/{$power}"]], $details->map(fn (array $row) => [$row['number'], $row['contact'], $row['tax'], $row['link']])->all());
    }

    public function test_a_payee_contact_has_expenses_on_their_profile_and_statement(): void
    {
        $power = $this->recordedExpense();
        $this->recordedExpense($this->taxi());
        $this->payExpense($power, $this->account(), '100.00');

        $this->getJson("/api/v1/contacts/{$this->payee->id}")
            ->assertJsonPath('data.in_use', true)
            ->assertJsonPath('data.in_expenses', true)
            ->assertJsonPath('data.in_purchases', false)
            ->assertJsonPath('data.in_sales', false);
        $this->getJson("/api/v1/contacts/{$this->customer->id}")->assertJsonPath('data.in_expenses', false);
        $this->getJson("/api/v1/expenses/payments?contact_id={$this->payee->id}")->assertOk()->assertJsonPath('pagination.total', 1);

        // Their statement is the supplier side: the expense owed them, then what was paid them.
        $statement = $this->getJson("/api/v1/reports/contact-statement?contact_id={$this->payee->id}")->assertOk()->json('data');
        $this->assertSame(
            [['Opening Balance', null, null, '0.00'], ['Expense', '340.00', '240.00', '340.00'], ['Payment', '0.00', null, '240.00']],
            array_map(fn (array $row) => [$row['type'], $row['debit'], $row['outstanding'], $row['balance']], $statement['rows']),
        );
        $this->assertSame("/expenses/{$power}", $statement['rows'][1]['link']);
        $this->assertSame(['Purchases & Expenses', '340.00'], [$statement['summary'][1]['label'], $statement['summary'][1]['value']]);

        // Owed as it stood on a day: before the payment, all of it.
        $this->getJson("/api/v1/reports/contact-statement?contact_id={$this->payee->id}&range=custom&date_from=2026-10-01&date_to=2026-10-27")
            ->assertOk()
            ->assertJsonPath('data.rows.1.outstanding', '340.00')
            ->assertJsonCount(2, 'data.rows');
    }

    /** City Power's expense, with $fields over it. */
    private function expense(array $fields = []): array
    {
        return [
            'contact_id'     => $this->payee->id,
            'document_date'  => '2026-10-27',
            'currency'       => 'USD',
            'receipt_number' => 'CP-1001',
            'lines'          => [
                ['expense_category_id' => $this->utilities->id, 'description' => 'Electricity', 'quantity' => '1', 'unit_price' => '200.00', 'tax_rate_id' => $this->inputVat->id],
                ['expense_category_id' => $this->travel->id, 'description' => 'Hotel', 'quantity' => '1', 'unit_price' => '100.00', 'tax_rate_id' => $this->hotelTax->id],
            ],
            ...$fields,
        ];
    }

    /** The taxi, to a payee typed in. */
    private function taxi(): array
    {
        return [
            'contact_id'     => null,
            'payee_name'     => 'Metro Taxi',
            'document_date'  => '2026-10-20',
            'receipt_number' => null,
            'lines'          => [['expense_category_id' => $this->transport->id, 'description' => 'Taxi', 'quantity' => '2', 'unit_price' => '12.50']],
        ];
    }

    /** A draft expense - its id. */
    private function draftExpense(array $fields = []): int
    {
        return $this->postJson('/api/v1/expenses', $this->expense($fields))->assertCreated()->json('data.id');
    }

    /** A recorded expense - its id. */
    private function recordedExpense(array $fields = []): int
    {
        $id = $this->draftExpense($fields);

        $this->postJson("/api/v1/expenses/{$id}/record")->assertOk();

        return $id;
    }

    /** A payment of $amount against expense $id, out of $account. */
    private function payExpense(int $id, PaymentAccount $account, string $amount, array $fields = []): void
    {
        $this->postJson("/api/v1/expenses/{$id}/payments", [
            'payment_date'       => '2026-10-28',
            'payment_account_id' => $account->id,
            'amount'             => $amount,
            'method'             => 'cheque',
            ...$fields,
        ])->assertCreated();
    }
}
