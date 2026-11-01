<?php

namespace Tests\Feature;

use App\Models\User\Contact;
use App\Models\User\Item;
use App\Models\User\PaymentAccount;
use App\Models\User\TaxRate;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\OffersPurchasesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Supplier payments (Doc 09, Phase B): recorded against a recorded purchase,
 * numbered as their voucher, settling the purchase and leaving the payment
 * account; reversed, never deleted; in another currency and with tax withheld;
 * and listed under Payments Made. Most tests pay this purchase, recorded as
 * PUR-00001:
 *
 *   10 reams of paper at 5.00   50.00 + VAT 7.50
 *   courier, no tax             12.00
 *   subtotal 62.00, tax 7.50, total 69.50
 */
class SupplierPaymentsTest extends TestCase
{
    use OffersPurchasesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Contact $supplier;

    private Item $paper;

    private TaxRate $vat;

    private PaymentAccount $cash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerPurchasesLite();
        $this->user = $this->signIn();
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));

        $business = $this->businessOf($this->user);
        $business->forceFill(['tax_mode' => 'enabled'])->save();

        $this->vat      = $business->taxRates()->create(['name' => 'VAT', 'code' => 'VAT15', 'rate' => '15', 'treatment' => 'standard', 'for_purchases' => true, 'effective_from' => '2026-01-01']);
        $this->supplier = $business->contacts()->create(['contact_type' => 'business', 'role' => 'supplier', 'name' => 'Karim Stores', 'supplier_payment_terms' => 15]);
        $this->paper    = $business->items()->create(['type' => 'physical', 'usage' => 'purchase', 'name' => 'Printer Paper', 'unit' => 'Ream', 'purchase_price' => '5.00', 'purchase_tax_id' => $this->vat->id]);
        $this->cash     = $business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Cash', 'currency' => 'USD']);
    }

    public function test_payments_settle_a_purchase_and_leave_the_account(): void
    {
        $id = $this->recorded();

        $this->pay($id, '50.00')
            ->assertCreated()
            ->assertJsonPath('message', 'Payment recorded. Voucher SPV-00001.')
            ->assertJsonPath('data.purchase.number', 'PUR-00001')
            ->assertJsonPath('data.supplier.name', 'Karim Stores')
            ->assertJsonPath('data.balance_after', '19.50')
            ->assertJsonPath('data.actions', ['reverse']);

        // Part paid, it can no longer be voided - and takes the rest, never more.
        $this->getJson("/api/v1/purchases/{$id}")
            ->assertJsonPath('data.payment_status', 'partially_paid')
            ->assertJsonPath('data.amount_paid', '50.00')
            ->assertJsonPath('data.balance_due', '19.50')
            ->assertJsonPath('data.actions', ['record_payment'])
            ->assertJsonPath('data.payments.0.number', 'SPV-00001')
            ->assertJsonPath('data.payments.0.account', ['name' => 'Cash', 'currency' => 'USD']);

        $this->pay($id, '19.51')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount' => 'That comes to more than the USD 19.50 still owed. Purchases Lite takes no supplier advances.']);
        $this->pay($id, '19.50')->assertCreated()->assertJsonPath('data.balance_after', '0.00');

        $this->getJson("/api/v1/purchases/{$id}")
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.balance_due', '0.00')
            ->assertJsonPath('data.actions', []);

        // The account's history names the purchase and opens the voucher; its balance went down.
        $this->getJson("/api/v1/payment-accounts/{$this->cash->id}/transactions")
            ->assertJsonPath('data.0.type', 'supplier_payment')
            ->assertJsonPath('data.0.direction', 'out')
            ->assertJsonPath('data.0.reference', 'SPV-00002')
            ->assertJsonPath('data.0.document', ['id' => $id, 'kind' => 'purchases', 'number' => 'PUR-00001'])
            ->assertJsonPath('data.0.source.type', 'supplier_payment')
            ->assertJsonPath('data.0.running_balance', '-69.50');
    }

    public function test_a_payment_is_reversed_and_then_the_purchase_can_be_voided(): void
    {
        $id      = $this->recorded();
        $payment = $this->pay($id, '69.50')->assertCreated()->json('data.id');

        $this->postJson("/api/v1/purchases/{$id}/void")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Payments have settled some of this purchase. Reverse them first.');

        $this->postJson("/api/v1/purchases/payments/{$payment}/reverse", ['reason' => 'Paid from the wrong account'])
            ->assertOk()
            ->assertJsonPath('message', 'Payment SPV-00001 reversed.')
            ->assertJsonPath('data.status', 'reversed')
            ->assertJsonPath('data.reversal_reason', 'Paid from the wrong account')
            ->assertJsonPath('data.actions', []);
        $this->postJson("/api/v1/purchases/payments/{$payment}/reverse")->assertStatus(409)->assertJsonPath('message', 'This payment was reversed already.');

        $this->getJson("/api/v1/purchases/{$id}")
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.balance_due', '69.50')
            ->assertJsonPath('data.actions', ['record_payment', 'void'])
            ->assertJsonPath('data.payments.0.status', 'reversed');
        $this->getJson("/api/v1/payment-accounts/{$this->cash->id}/transactions")
            ->assertJsonPath('data.0.status', 'reversed')
            ->assertJsonPath('data.0.running_balance', '0.00');

        $this->postJson("/api/v1/purchases/{$id}/void")->assertOk();
        $this->pay($id, '10.00')->assertStatus(409);
    }

    public function test_a_payment_is_refused_where_it_cannot_be_made(): void
    {
        $draft = $this->postJson('/api/v1/purchases', $this->purchase())->assertCreated()->json('data.id');
        $this->pay($draft, '10.00')
            ->assertStatus(409)
            ->assertJsonPath('message', 'Only a recorded purchase with something still to pay takes a payment.');

        $id       = $this->recorded(['supplier_invoice_number' => 'KS-2002']);
        $business = $this->businessOf($this->user);
        $receives = $business->paymentAccounts()->create(['type' => 'bank', 'name' => 'Collections', 'currency' => 'USD', 'allow_supplier_payments' => false]);
        $closed   = $business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Old Till', 'currency' => 'USD', 'is_active' => false]);

        $this->pay($id, '10.00', ['payment_account_id' => $receives->id])
            ->assertJsonValidationErrors(['payment_account_id' => 'Choose one of your active payment accounts that pays suppliers.']);
        $this->pay($id, '10.00', ['payment_account_id' => $closed->id])->assertJsonValidationErrors('payment_account_id');
        $this->pay($id, '10.00', ['payment_date' => '2026-10-29'])
            ->assertJsonValidationErrors(['payment_date' => 'Money is recorded once it has moved: use today or an earlier date.']);
        $this->pay($id, '10.00', ['payment_date' => '2026-10-27'])
            ->assertJsonValidationErrors(['payment_date' => 'A payment cannot come before its purchase, dated 2026-10-28.']);
        $this->pay($id, '0')->assertJsonValidationErrors('amount');

        // Another business's purchase and payments are not found - even paid from an account of its own.
        $payment = $this->pay($id, '10.00')->assertCreated()->json('data.id');
        $other   = $this->signIn();
        $till    = $this->businessOf($other)->paymentAccounts()->create(['type' => 'cash', 'name' => 'Till', 'currency' => 'USD']);
        $this->pay($id, '10.00', ['payment_account_id' => $till->id])->assertNotFound();
        $this->getJson("/api/v1/purchases/payments/{$payment}")->assertNotFound();
        $this->postJson("/api/v1/purchases/payments/{$payment}/reverse")->assertNotFound();
    }

    public function test_a_payment_from_an_account_in_another_currency_keeps_both_amounts(): void
    {
        $business = $this->businessOf($this->user);
        $business->forceFill(['additional_currencies' => ['EUR']])->save();
        $euros = $business->paymentAccounts()->create(['type' => 'bank', 'name' => 'Euro Account', 'currency' => 'EUR']);
        $id    = $this->recorded();

        $this->pay($id, '60.00', ['payment_account_id' => $euros->id])
            ->assertJsonValidationErrors(['applied_amount' => 'Enter what this payment pays off the purchase, in USD.']);

        $this->pay($id, '60.00', ['payment_account_id' => $euros->id, 'applied_amount' => '69.50'])
            ->assertCreated()
            ->assertJsonPath('data.amount', '60.00')
            ->assertJsonPath('data.applied_amount', '69.50')
            ->assertJsonPath('data.account', ['id' => $euros->id, 'name' => 'Euro Account', 'currency' => 'EUR']);

        $this->getJson("/api/v1/purchases/{$id}")->assertJsonPath('data.payment_status', 'paid');
        $this->getJson("/api/v1/payment-accounts/{$euros->id}/transactions")->assertJsonPath('data.0.amount', '60.00');
    }

    public function test_tax_withheld_settles_the_purchase_without_leaving_the_account(): void
    {
        $business = $this->businessOf($this->user);
        $business->forceFill(['withholding_tax' => true])->save();
        $wht   = $business->withholdingTaxes()->create(['name' => 'WHT', 'code' => 'WHT5', 'rate' => '5', 'for_supplier_payments' => true, 'effective_from' => '2026-01-01']);
        $theirs = $business->withholdingTaxes()->create(['name' => 'Customer WHT', 'code' => 'CWHT', 'rate' => '5', 'for_customer_payments' => true, 'effective_from' => '2026-01-01']);
        $id    = $this->recorded();

        $this->pay($id, '66.40', ['withholding_tax_id' => $theirs->id, 'withholding_amount' => '3.10'])
            ->assertJsonValidationErrors(['withholding_tax_id' => 'Choose an active withholding tax for supplier payments, in effect on the payment date.']);

        $this->pay($id, '66.40', ['withholding_tax_id' => $wht->id, 'withholding_amount' => '3.10'])
            ->assertCreated()
            ->assertJsonPath('data.withholding', ['name' => 'WHT', 'rate' => '5.0000', 'amount' => '3.10'])
            ->assertJsonPath('data.balance_after', '0.00');

        $this->getJson("/api/v1/purchases/{$id}")
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.amount_paid', '66.40')
            ->assertJsonPath('data.withholding_amount', '3.10');
        $this->getJson("/api/v1/payment-accounts/{$this->cash->id}/transactions")->assertJsonPath('data.0.amount', '66.40');

        // A rate withheld on a supplier payment is kept: deactivated, never deleted.
        $this->deleteJson("/api/v1/withholding-taxes/{$wht->id}")->assertStatus(409);
    }

    public function test_payments_made_lists_searches_and_filters(): void
    {
        $first  = $this->recorded();
        $second = $this->recorded(['supplier_invoice_number' => 'KS-2002']);

        $reversed = $this->pay($first, '20.00', ['reference' => 'CHQ-100'])->json('data.id');
        $this->pay($second, '30.00', ['method' => 'bank_transfer'])->assertCreated();
        $this->postJson("/api/v1/purchases/payments/{$reversed}/reverse")->assertOk();

        $numbers = fn (string $query = '') => collect($this->getJson("/api/v1/purchases/payments{$query}")->assertOk()->json('data'))->pluck('number')->all();

        $this->assertSame(['SPV-00002', 'SPV-00001'], $numbers());
        $this->assertSame(['SPV-00002'], $numbers('?status=completed'));
        $this->assertSame(['SPV-00001'], $numbers('?search=CHQ-100'));
        $this->assertSame(['SPV-00002'], $numbers('?search=PUR-00002'));
        $this->assertSame(['SPV-00002', 'SPV-00001'], $numbers('?search=Karim'));

        $this->getJson('/api/v1/purchases/payments')
            ->assertJsonPath('data.0.supplier', 'Karim Stores')
            ->assertJsonPath('data.0.purchase', ['id' => $second, 'number' => 'PUR-00002'])
            ->assertJsonPath('data.0.method', 'bank_transfer')
            ->assertJsonPath('data.0.amount', '30.00');
    }

    public function test_a_purchase_record_and_its_vouchers_are_pdfs(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])]);

        $draft = $this->postJson('/api/v1/purchases', $this->purchase(['supplier_invoice_number' => 'KS-0999']))->assertCreated()->json('data.id');
        $this->get("/api/v1/purchases/{$draft}/pdf", ['Accept' => 'application/json'])->assertOk();
        $this->assertStringContainsString('stamp--draft', $this->sentHtml());

        $id = $this->recorded();
        $this->get("/api/v1/purchases/{$id}/pdf?download=1", ['Accept' => 'application/json'])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename=Purchase-PUR-00001.pdf');

        $html = $this->sentHtml();
        foreach (['Purchase', 'PUR-00001', 'Supplier', 'Karim Stores', 'Supplier Invoice No.', 'KS-1001', 'Printer Paper', 'Balance Due'] as $text) {
            $this->assertStringContainsString($text, $html);
        }

        $payment = $this->pay($id, '50.00', ['reference' => 'CHQ-100'])->json('data.id');
        $this->get("/api/v1/purchases/payments/{$payment}/pdf", ['Accept' => 'application/json'])->assertOk();

        $html = $this->sentHtml();
        foreach (['Payment Voucher', 'SPV-00001', 'Paid To', 'Karim Stores', 'Amount Paid', 'PUR-00001', 'CHQ-100', 'Still to Pay After It'] as $text) {
            $this->assertStringContainsString($text, $html);
        }

        $this->postJson("/api/v1/purchases/payments/{$payment}/reverse")->assertOk();
        $this->get("/api/v1/purchases/payments/{$payment}/pdf", ['Accept' => 'application/json'])->assertOk();
        $this->assertStringContainsString('stamp--reversed', $this->sentHtml());
    }

    public function test_purchases_and_vouchers_have_templates_and_numbering(): void
    {
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])]);

        $types = collect($this->getJson('/api/v1/business-settings/templates')->assertOk()->json('data'))->pluck('type')->all();
        $this->assertSame(['purchase', 'supplier_voucher'], $types);

        $settings = [
            'color' => '#7c3aed', 'font' => 'sans', 'paper_size' => 'a4', 'header_layout' => 'logo_left', 'show_logo' => true,
            'visible_fields' => ['business_contact', 'customer_contact', 'references', 'location', 'descriptions'],
            'line_columns' => ['quantity', 'unit_price', 'discount', 'tax'], 'show_notes' => true, 'show_terms' => true,
            'show_payment_instructions' => true, 'footer_text' => null, 'language' => 'en', 'date_format' => 'd M Y', 'number_format' => 'comma_dot',
        ];

        $this->post('/api/v1/business-settings/templates/purchase/preview', $settings, ['Accept' => 'application/json'])->assertOk();
        $this->assertStringContainsString('Sample Supplies Ltd.', $this->sentHtml());
        $this->post('/api/v1/business-settings/templates/supplier_voucher/preview', $settings, ['Accept' => 'application/json'])->assertOk();
        $this->assertStringContainsString('Payment Voucher', $this->sentHtml());

        $this->assertSame(['purchase', 'supplier_voucher'], collect($this->getJson('/api/v1/business-settings/numbering')->assertOk()->json('data'))->pluck('type')->all());
    }

    /** The HTML of the last PDF asked of Gotenberg. */
    private function sentHtml(): string
    {
        /** @var Request $request */
        [$request] = Http::recorded()->last();

        return (string) collect($request->data())->firstWhere('filename', 'index.html')['contents'];
    }

    /** The purchase most tests pay, with $fields over it. */
    private function purchase(array $fields = []): array
    {
        return [
            'contact_id'              => $this->supplier->id,
            'document_date'           => '2026-10-28',
            'currency'                => 'USD',
            'supplier_invoice_number' => 'KS-1001',
            'lines'                   => [
                ['item_id' => $this->paper->id, 'name' => 'Printer Paper', 'quantity' => '10', 'unit' => 'Ream', 'unit_price' => '5.00', 'tax_rate_id' => $this->vat->id],
                ['name' => 'Courier', 'quantity' => '1', 'unit_price' => '12.00'],
            ],
            ...$fields,
        ];
    }

    /** A recorded purchase - its id. */
    private function recorded(array $fields = []): int
    {
        $id = $this->postJson('/api/v1/purchases', $this->purchase($fields))->assertCreated()->json('data.id');

        $this->postJson("/api/v1/purchases/{$id}/record")->assertOk();

        return $id;
    }

    /** POST a payment of $amount against purchase $id out of the cash account, dated the purchase's day unless told otherwise. */
    private function pay(int $id, string $amount, array $fields = []): TestResponse
    {
        return $this->postJson("/api/v1/purchases/{$id}/payments", [
            'payment_date'       => '2026-10-28',
            'payment_account_id' => $this->cash->id,
            'amount'             => $amount,
            'method'             => 'cash',
            ...$fields,
        ]);
    }
}
