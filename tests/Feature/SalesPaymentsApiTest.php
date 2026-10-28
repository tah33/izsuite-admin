<?php

namespace Tests\Feature;

use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Customer payments (Doc 08 sections 13 and 14): recorded against an issued
 * invoice, numbered as receipts, settling the invoice and reaching the
 * account's history - in another currency, with tax withheld - reversed, and
 * listed as Payments Received.
 */
class SalesPaymentsApiTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded first, so super-admin holds id 1 and the owner is not taken for an admin.
        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $this->user = $this->signIn();
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
        $this->setUpSales();
    }

    public function test_a_payment_is_numbered_settles_the_invoice_and_reaches_the_account(): void
    {
        $id   = $this->issuedInvoice();
        $cash = $this->account(['opening_balance' => '100.00', 'opening_balance_date' => '2026-10-01']);

        $this->pay($id, $cash, '100')
            ->assertCreated()
            ->assertJsonPath('message', 'Payment recorded. Receipt RCT-00001.')
            ->assertJsonPath('data.number', 'RCT-00001')
            ->assertJsonPath('data.amount', '100.00')
            ->assertJsonPath('data.balance_after', '235.50')
            ->assertJsonPath('data.actions', ['reverse']);

        $invoice = $this->getJson("/api/v1/sales/invoices/{$id}")->json('data');
        $this->assertSame(['100.00', '235.50', 'partially_paid'], [$invoice['amount_paid'], $invoice['balance_due'], $invoice['payment_status']]);
        $this->assertSame(['record_payment', 'credit_note', 'refund'], $invoice['actions']);
        $this->assertSame(['RCT-00001'], array_column($invoice['payments'], 'number'));

        $this->pay($id, $cash, '235.50')->assertCreated()->assertJsonPath('data.number', 'RCT-00002')->assertJsonPath('data.balance_after', '0.00');

        $this->getJson("/api/v1/sales/invoices/{$id}")
            ->assertJsonPath('data.balance_due', '0.00')
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.actions', ['credit_note', 'refund']);

        $this->getJson("/api/v1/payment-accounts/{$cash->id}")->assertJsonPath('data.balance', '435.50')->assertJsonPath('data.has_transactions', true);

        $history = $this->getJson("/api/v1/payment-accounts/{$cash->id}/transactions")->assertOk()->json('data');
        $this->assertSame(['RCT-00002', 'RCT-00001'], array_column($history, 'reference'));
        $this->assertSame(['435.50', '200.00'], array_column($history, 'running_balance'));
        $this->assertSame(['customer_payment', 'in', 'INV-00001', 'Uddin Traders'], [$history[0]['type'], $history[0]['direction'], $history[0]['document']['number'], $history[0]['contact']['name']]);

        $this->assertContains('Recorded payment RCT-00001 of USD 100.00 for invoice INV-00001', ActivityLog::where('user_id', $this->user->id)->pluck('description')->all());
    }

    public function test_more_than_is_owed_or_a_date_or_account_out_of_bounds_is_refused(): void
    {
        $id   = $this->issuedInvoice();
        $cash = $this->account();

        $this->pay($id, $cash, '400')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount' => 'That comes to more than the USD 335.50 still due. Overpayments are not taken in Sales Lite.']);

        $this->pay($id, $cash, '10', ['payment_date' => '2026-10-27'])->assertJsonValidationErrors(['payment_date' => 'A payment cannot come before its invoice, dated 2026-10-28.']);
        $this->pay($id, $cash, '10', ['payment_date' => '2026-10-29'])->assertJsonValidationErrors(['payment_date' => 'Money is recorded once it has moved: use today or an earlier date.']);
        $this->pay($id, $cash, '0')->assertJsonValidationErrors(['amount' => 'The amount must be more than zero.']);

        $later = $this->account(['name' => 'Bank', 'type' => 'bank', 'opening_balance' => '50', 'opening_balance_date' => '2026-10-29']);
        $this->pay($id, $later, '10')->assertJsonValidationErrors(['payment_date' => "Bank's opening balance is dated 2026-10-29. Use a date on or after it, or change that date first."]);

        $cash->update(['is_active' => false]);
        $this->pay($id, $cash, '10')->assertJsonValidationErrors(['payment_account_id' => 'Choose one of your active payment accounts.']);

        $draft = $this->draft('invoices');
        $this->pay($draft, $this->account(['name' => 'Till']), '10')->assertConflict()->assertJsonPath('message', 'Only an issued invoice with something still to pay takes a payment.');
    }

    public function test_a_payment_in_another_currency_keeps_what_it_paid_off_and_its_base_value(): void
    {
        $this->businessOf($this->user)->forceFill(['additional_currencies' => ['BDT']])->save();

        $id   = $this->issuedInvoice();
        $bank = $this->account(['name' => 'Dhaka Bank', 'type' => 'bank', 'currency' => 'BDT']);

        $this->pay($id, $bank, '40260')->assertJsonValidationErrors(['applied_amount' => 'Enter what this payment pays off the invoice, in USD.']);

        $this->pay($id, $bank, '40260', ['applied_amount' => '335.50'])
            ->assertCreated()
            ->assertJsonPath('data.amount', '40260.00')
            ->assertJsonPath('data.applied_amount', '335.50')
            ->assertJsonPath('data.exchange_rate', '120.00000000')
            ->assertJsonPath('data.account.currency', 'BDT');

        $this->getJson("/api/v1/sales/invoices/{$id}")->assertJsonPath('data.payment_status', 'paid');
        $this->getJson("/api/v1/payment-accounts/{$bank->id}")->assertJsonPath('data.balance', '40260.00');
        $this->assertDatabaseHas('account_transactions', ['payment_account_id' => $bank->id, 'amount' => '40260.00', 'base_amount' => '335.50']);
    }

    public function test_withheld_tax_settles_the_invoice_without_reaching_the_account(): void
    {
        $id   = $this->issuedInvoice();
        $cash = $this->account();
        $wht  = $this->businessOf($this->user)->withholdingTaxes()->create(['name' => 'WHT', 'code' => 'WHT5', 'rate' => '5', 'for_customer_payments' => true, 'effective_from' => '2026-01-01']);

        $this->pay($id, $cash, '320.75', ['withholding_tax_id' => $wht->id, 'withholding_amount' => '14.75'])
            ->assertJsonValidationErrors(['withholding_tax_id' => 'Choose an active withholding tax for customer payments, in effect on the payment date.']);

        $this->businessOf($this->user)->forceFill(['withholding_tax' => true])->save();

        $this->pay($id, $cash, '320.75', ['withholding_tax_id' => $wht->id])->assertJsonValidationErrors(['withholding_amount' => 'Enter the amount withheld.']);

        $this->pay($id, $cash, '320.75', ['withholding_tax_id' => $wht->id, 'withholding_amount' => '14.75'])
            ->assertCreated()
            ->assertJsonPath('data.withholding', ['name' => 'WHT', 'code' => 'WHT5', 'rate' => '5.0000', 'amount' => '14.75']);

        $invoice = $this->getJson("/api/v1/sales/invoices/{$id}")->json('data');
        $this->assertSame(['320.75', '14.75', '0.00', 'paid'], [$invoice['amount_paid'], $invoice['withholding_amount'], $invoice['balance_due'], $invoice['payment_status']]);
        $this->getJson("/api/v1/payment-accounts/{$cash->id}")->assertJsonPath('data.balance', '320.75');

        // A rate withheld on a payment keeps its percentage and cannot be deleted.
        $this->putJson("/api/v1/withholding-taxes/{$wht->id}", ['name' => 'WHT', 'code' => 'WHT5', 'rate' => '7', 'applies_to' => ['customer_payments'], 'effective_from' => '2026-01-01'])
            ->assertJsonValidationErrors(['rate' => 'This rate was withheld on payments, so its percentage stays as it is. Add a new rate and switch this one off instead.']);
        $this->deleteJson("/api/v1/withholding-taxes/{$wht->id}")->assertConflict();
    }

    public function test_a_reversed_payment_leaves_every_balance_and_the_invoice_owes_again(): void
    {
        $id   = $this->issuedInvoice();
        $cash = $this->account();

        $this->pay($id, $cash, '10')->assertCreated();
        $this->postJson("/api/v1/sales/invoices/{$id}/void")
            ->assertConflict()
            ->assertJsonPath('message', 'Payments or credit notes have settled some of this invoice. Reverse its payments and void its credit notes first.');

        $payment = $this->getJson("/api/v1/sales/invoices/{$id}")->json('data.payments.0.id');

        $this->postJson("/api/v1/sales/payments/{$payment}/reverse", ['reason' => 'Cheque bounced'])
            ->assertOk()
            ->assertJsonPath('message', 'Payment RCT-00001 reversed.')
            ->assertJsonPath('data.status', 'reversed')
            ->assertJsonPath('data.reversal_reason', 'Cheque bounced')
            ->assertJsonPath('data.actions', []);

        $this->getJson("/api/v1/sales/invoices/{$id}")->assertJsonPath('data.balance_due', '335.50')->assertJsonPath('data.payment_status', 'unpaid');
        $this->getJson("/api/v1/payment-accounts/{$cash->id}")->assertJsonPath('data.balance', '0.00');
        $this->getJson("/api/v1/payment-accounts/{$cash->id}/transactions")->assertJsonPath('data.0.status', 'reversed')->assertJsonPath('data.0.running_balance', '0.00');

        $this->postJson("/api/v1/sales/payments/{$payment}/reverse")->assertConflict()->assertJsonPath('message', 'This payment was reversed already.');
        $this->postJson("/api/v1/sales/invoices/{$id}/void")->assertOk();
    }

    public function test_payments_received_lists_payments_searched_and_filtered(): void
    {
        $cash = $this->account();
        $id   = $this->issuedInvoice();

        $this->pay($id, $cash, '35.50')->assertCreated();
        $this->pay($id, $cash, '300', ['reference' => 'BANK-77'])->assertCreated();

        $rows = $this->getJson('/api/v1/sales/payments')->assertOk()->json('data');
        $this->assertSame(['RCT-00002', 'RCT-00001'], array_column($rows, 'number'));
        $this->assertSame(
            ['id', 'kind', 'number', 'status', 'payment_date', 'method', 'customer', 'invoice', 'account', 'amount', 'applied_amount', 'withholding_amount'],
            array_keys($rows[0]),
        );
        $this->assertSame(['INV-00001', 'Uddin Traders', 'Cash'], [$rows[0]['invoice']['number'], $rows[0]['customer']['name'], $rows[0]['account']['name']]);

        $this->getJson('/api/v1/sales/payments?search=BANK-77')->assertJsonPath('data.*.number', ['RCT-00002']);
        $this->getJson('/api/v1/sales/payments?status=reversed')->assertJsonPath('data', []);

        // Another business's payment answers like one that does not exist.
        $other = $this->user->workspaces()->create(['name' => 'Second Business']);
        $this->getJson("/api/v1/sales/payments/{$rows[0]['id']}", $this->pick($other))->assertNotFound();
    }
}
