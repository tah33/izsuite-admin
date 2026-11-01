<?php

namespace Tests\Feature;

use App\Models\User\Contact;
use App\Models\User\ExpenseCategory;
use App\Models\User\PaymentAccount;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\OffersExpensesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Expenses Lite's payments (Doc 10, Phase B): a recorded expense paid in part
 * and in full - never past what it owes - out of an account that pays
 * suppliers and expenses, each payment with its voucher; reversed, never
 * deleted; from an account in another currency; with tax withheld; listed in
 * Payments Made; and its voucher as a PDF. Most tests pay this expense to
 * City Power, recorded and unpaid:
 *
 *   utilities, 1 at 200.00      200.00
 *   internet, 2 at 15.00         30.00
 *   total 230.00
 */
class ExpensePaymentsTest extends TestCase
{
    use OffersExpensesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Contact $payee;

    private ExpenseCategory $utilities;

    private PaymentAccount $cash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerExpensesLite();
        $this->user = $this->signIn();
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));

        $business        = $this->businessOf($this->user);
        $this->payee     = $business->contacts()->create(['contact_type' => 'business', 'role' => 'supplier', 'name' => 'City Power']);
        $this->utilities = $business->expenseCategories()->create(['name' => 'Utilities']);
        $this->cash      = $business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Cash', 'currency' => 'USD']);
    }

    public function test_payments_settle_an_expense_and_leave_the_account(): void
    {
        $id = $this->recorded();

        $this->pay($id, '100.00')
            ->assertCreated()
            ->assertJsonPath('message', 'Payment recorded. Voucher EPV-00001.')
            ->assertJsonPath('data.expense.number', 'EXP-00001')
            ->assertJsonPath('data.payee', ['id' => $this->payee->id, 'name' => 'City Power'])
            ->assertJsonPath('data.balance_after', '130.00')
            ->assertJsonPath('data.actions', ['reverse']);

        // Part paid, it can no longer be voided - and takes the rest, never more.
        $this->getJson("/api/v1/expenses/{$id}")
            ->assertJsonPath('data.payment_status', 'partially_paid')
            ->assertJsonPath('data.amount_paid', '100.00')
            ->assertJsonPath('data.balance_due', '130.00')
            ->assertJsonPath('data.actions', ['record_payment'])
            ->assertJsonPath('data.payments.0.number', 'EPV-00001')
            ->assertJsonPath('data.payments.0.account', ['name' => 'Cash', 'currency' => 'USD']);

        $this->pay($id, '130.01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount' => 'That comes to more than the USD 130.00 still owed. Expenses Lite takes no advances.']);
        $this->pay($id, '130.00')->assertCreated()->assertJsonPath('data.balance_after', '0.00');

        $this->getJson("/api/v1/expenses/{$id}")
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.balance_due', '0.00')
            ->assertJsonPath('data.actions', []);

        // The account's history names the expense and opens the voucher; its balance went down.
        $this->getJson("/api/v1/payment-accounts/{$this->cash->id}/transactions")
            ->assertJsonPath('data.0.type', 'expense_payment')
            ->assertJsonPath('data.0.direction', 'out')
            ->assertJsonPath('data.0.reference', 'EPV-00002')
            ->assertJsonPath('data.0.document', ['id' => $id, 'kind' => 'expenses', 'number' => 'EXP-00001'])
            ->assertJsonPath('data.0.source.type', 'expense_payment')
            ->assertJsonPath('data.0.running_balance', '-230.00');
    }

    public function test_a_payment_is_reversed_and_then_the_expense_can_be_voided(): void
    {
        $id      = $this->recorded();
        $payment = $this->pay($id, '230.00')->assertCreated()->json('data.id');

        $this->postJson("/api/v1/expenses/{$id}/void")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Payments have settled some of this expense. Reverse them first.');

        $this->postJson("/api/v1/expenses/payments/{$payment}/reverse", ['reason' => 'Paid from the wrong account'])
            ->assertOk()
            ->assertJsonPath('message', 'Payment EPV-00001 reversed.')
            ->assertJsonPath('data.status', 'reversed')
            ->assertJsonPath('data.reversal_reason', 'Paid from the wrong account')
            ->assertJsonPath('data.actions', []);
        $this->postJson("/api/v1/expenses/payments/{$payment}/reverse")->assertStatus(409)->assertJsonPath('message', 'This payment was reversed already.');

        $this->getJson("/api/v1/expenses/{$id}")
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.balance_due', '230.00')
            ->assertJsonPath('data.actions', ['record_payment', 'void'])
            ->assertJsonPath('data.payments.0.status', 'reversed');
        $this->getJson("/api/v1/payment-accounts/{$this->cash->id}/transactions")
            ->assertJsonPath('data.0.status', 'reversed')
            ->assertJsonPath('data.0.running_balance', '0.00');

        $this->postJson("/api/v1/expenses/{$id}/void")->assertOk();
        $this->pay($id, '10.00')->assertStatus(409);
    }

    public function test_a_payment_is_refused_where_it_cannot_be_made(): void
    {
        $draft = $this->postJson('/api/v1/expenses', $this->expense())->assertCreated()->json('data.id');
        $this->pay($draft, '10.00')
            ->assertStatus(409)
            ->assertJsonPath('message', 'Only a recorded expense with something still to pay takes a payment.');

        $id       = $this->recorded(['receipt_number' => 'CP-2002']);
        $business = $this->businessOf($this->user);
        $receives = $business->paymentAccounts()->create(['type' => 'bank', 'name' => 'Collections', 'currency' => 'USD', 'allow_supplier_payments' => false]);

        $this->pay($id, '10.00', ['payment_account_id' => $receives->id])
            ->assertJsonValidationErrors(['payment_account_id' => 'Choose one of your active payment accounts that pays suppliers and expenses.']);
        $this->pay($id, '10.00', ['payment_date' => '2026-10-29'])
            ->assertJsonValidationErrors(['payment_date' => 'Money is recorded once it has moved: use today or an earlier date.']);
        $this->pay($id, '10.00', ['payment_date' => '2026-10-27'])
            ->assertJsonValidationErrors(['payment_date' => 'A payment cannot come before its expense, dated 2026-10-28.']);
        $this->pay($id, '0')->assertJsonValidationErrors('amount');

        // Another business's expense and payments are not found - even paid from an account of its own.
        $payment = $this->pay($id, '10.00')->assertCreated()->json('data.id');
        $other   = $this->signIn();
        $till    = $this->businessOf($other)->paymentAccounts()->create(['type' => 'cash', 'name' => 'Till', 'currency' => 'USD']);
        $this->pay($id, '10.00', ['payment_account_id' => $till->id])->assertNotFound();
        $this->getJson("/api/v1/expenses/payments/{$payment}")->assertNotFound();
        $this->postJson("/api/v1/expenses/payments/{$payment}/reverse")->assertNotFound();
    }

    public function test_a_payment_from_an_account_in_another_currency_keeps_both_amounts(): void
    {
        $business = $this->businessOf($this->user);
        $business->forceFill(['additional_currencies' => ['EUR']])->save();
        $euros = $business->paymentAccounts()->create(['type' => 'bank', 'name' => 'Euro Account', 'currency' => 'EUR']);
        $id    = $this->recorded();

        $this->pay($id, '200.00', ['payment_account_id' => $euros->id])
            ->assertJsonValidationErrors(['applied_amount' => 'Enter what this payment pays off the expense, in USD.']);

        $this->pay($id, '200.00', ['payment_account_id' => $euros->id, 'applied_amount' => '230.00'])
            ->assertCreated()
            ->assertJsonPath('data.amount', '200.00')
            ->assertJsonPath('data.applied_amount', '230.00')
            ->assertJsonPath('data.account', ['id' => $euros->id, 'name' => 'Euro Account', 'currency' => 'EUR']);

        $this->getJson("/api/v1/expenses/{$id}")->assertJsonPath('data.payment_status', 'paid');
        $this->getJson("/api/v1/payment-accounts/{$euros->id}/transactions")->assertJsonPath('data.0.amount', '200.00');
    }

    public function test_tax_withheld_settles_the_expense_without_leaving_the_account(): void
    {
        $business = $this->businessOf($this->user);
        $business->forceFill(['withholding_tax' => true])->save();
        $wht      = $business->withholdingTaxes()->create(['name' => 'WHT', 'code' => 'WHT5', 'rate' => '5', 'for_expense_payments' => true, 'effective_from' => '2026-01-01']);
        $supplier = $business->withholdingTaxes()->create(['name' => 'Supplier WHT', 'code' => 'SWHT', 'rate' => '5', 'for_supplier_payments' => true, 'effective_from' => '2026-01-01']);
        $id       = $this->recorded();

        $this->pay($id, '218.50', ['withholding_tax_id' => $supplier->id, 'withholding_amount' => '11.50'])
            ->assertJsonValidationErrors(['withholding_tax_id' => 'Choose an active withholding tax for expense payments, in effect on the payment date.']);

        $this->pay($id, '218.50', ['withholding_tax_id' => $wht->id, 'withholding_amount' => '11.50'])
            ->assertCreated()
            ->assertJsonPath('data.withholding', ['name' => 'WHT', 'rate' => '5.0000', 'amount' => '11.50'])
            ->assertJsonPath('data.balance_after', '0.00');

        $this->getJson("/api/v1/expenses/{$id}")
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.amount_paid', '218.50')
            ->assertJsonPath('data.withholding_amount', '11.50');
        $this->getJson("/api/v1/payment-accounts/{$this->cash->id}/transactions")->assertJsonPath('data.0.amount', '218.50');

        // A rate withheld on an expense payment is kept: deactivated, never deleted.
        $this->deleteJson("/api/v1/withholding-taxes/{$wht->id}")->assertStatus(409);
    }

    public function test_payments_made_lists_searches_and_filters(): void
    {
        $first  = $this->recorded();
        $second = $this->recorded(['contact_id' => null, 'payee_name' => 'Corner Taxi', 'receipt_number' => 'CT-9']);

        $reversed = $this->pay($first, '20.00', ['reference' => 'CHQ-100'])->json('data.id');
        $this->pay($second, '30.00', ['method' => 'bank_transfer'])->assertCreated();
        $this->postJson("/api/v1/expenses/payments/{$reversed}/reverse")->assertOk();

        $numbers = fn (string $query = '') => collect($this->getJson("/api/v1/expenses/payments{$query}")->assertOk()->json('data'))->pluck('number')->all();

        $this->assertSame(['EPV-00002', 'EPV-00001'], $numbers());
        $this->assertSame(['EPV-00002'], $numbers('?status=completed'));
        $this->assertSame(['EPV-00001'], $numbers('?search=CHQ-100'));
        $this->assertSame(['EPV-00002'], $numbers('?search=EXP-00002'));
        $this->assertSame(['EPV-00002'], $numbers('?search=Corner'));
        $this->assertSame(['EPV-00001'], $numbers("?contact_id={$this->payee->id}"));

        $this->getJson('/api/v1/expenses/payments')
            ->assertJsonPath('data.0.payee', 'Corner Taxi')
            ->assertJsonPath('data.0.expense', ['id' => $second, 'number' => 'EXP-00002'])
            ->assertJsonPath('data.0.method', 'bank_transfer')
            ->assertJsonPath('data.0.amount', '30.00');
    }

    public function test_a_voucher_is_a_pdf_with_a_template_and_numbering(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])]);

        $id      = $this->recorded();
        $payment = $this->pay($id, '50.00', ['reference' => 'PETTY-7'])->json('data.id');

        $this->get("/api/v1/expenses/payments/{$payment}/pdf?download=1", ['Accept' => 'application/json'])
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=Voucher-EPV-00001.pdf');

        $html = $this->sentHtml();
        foreach (['Payment Voucher', 'EPV-00001', 'Paid To', 'City Power', 'Amount Paid', 'EXP-00001', 'PETTY-7', 'Still to Pay After It'] as $text) {
            $this->assertStringContainsString($text, $html);
        }

        $this->postJson("/api/v1/expenses/payments/{$payment}/reverse")->assertOk();
        $this->get("/api/v1/expenses/payments/{$payment}/pdf", ['Accept' => 'application/json'])->assertOk();
        $this->assertStringContainsString('stamp--reversed', $this->sentHtml());

        // Its template is there to edit and preview, and its numbering.
        $this->assertContains('expense_voucher', collect($this->getJson('/api/v1/business-settings/templates')->assertOk()->json('data'))->pluck('type')->all());
        $this->post('/api/v1/business-settings/templates/expense_voucher/preview', [
            'color' => '#7c3aed', 'font' => 'sans', 'paper_size' => 'a4', 'header_layout' => 'logo_left', 'show_logo' => true,
            'visible_fields' => ['business_contact', 'customer_contact', 'references', 'location', 'descriptions'],
            'line_columns' => ['quantity', 'unit_price', 'discount', 'tax'], 'show_notes' => true, 'show_terms' => true,
            'show_payment_instructions' => true, 'footer_text' => null, 'language' => 'en', 'date_format' => 'd M Y', 'number_format' => 'comma_dot',
        ], ['Accept' => 'application/json'])->assertOk();
        $this->assertStringContainsString('PETTY-118', $this->sentHtml());
        $this->assertContains('expense_voucher', collect($this->getJson('/api/v1/business-settings/numbering')->assertOk()->json('data'))->pluck('type')->all());
    }

    /** The HTML of the last PDF asked of Gotenberg. */
    private function sentHtml(): string
    {
        /** @var Request $request */
        [$request] = Http::recorded()->last();

        return (string) collect($request->data())->firstWhere('filename', 'index.html')['contents'];
    }

    /** City Power's expense, with $fields over it. */
    private function expense(array $fields = []): array
    {
        return [
            'contact_id'     => $this->payee->id,
            'document_date'  => '2026-10-28',
            'currency'       => 'USD',
            'receipt_number' => 'CP-1001',
            'lines'          => [
                ['expense_category_id' => $this->utilities->id, 'description' => 'Electricity', 'quantity' => '1', 'unit_price' => '200.00'],
                ['expense_category_id' => $this->utilities->id, 'description' => 'Internet', 'quantity' => '2', 'unit_price' => '15.00'],
            ],
            ...$fields,
        ];
    }

    /** A recorded expense - its id. */
    private function recorded(array $fields = []): int
    {
        $id = $this->postJson('/api/v1/expenses', $this->expense($fields))->assertCreated()->json('data.id');

        $this->postJson("/api/v1/expenses/{$id}/record")->assertOk();

        return $id;
    }

    /** A payment of $amount against expense $id, out of the cash account unless told otherwise. */
    private function pay(int $id, string $amount, array $fields = []): TestResponse
    {
        return $this->postJson("/api/v1/expenses/{$id}/payments", [
            'payment_date'       => '2026-10-28',
            'payment_account_id' => $this->cash->id,
            'amount'             => $amount,
            'method'             => 'cash',
            ...$fields,
        ]);
    }
}
