<?php

namespace Tests\Feature;

use App\Models\User\Location;
use App\Models\User\SalesDocument;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Services\Documents\DocumentRenderer;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * What Doc 05 keeps on a payment account besides its balance: its location
 * and its type's details (sections 5 to 7), the accounts each kind of
 * payment starts with (section 8), what may be recorded in it (section 6),
 * and the public details customer documents print (section 9).
 */
class PaymentAccountDetailsTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private Location $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $this->user = $this->signIn();
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
        $this->setUpSales();

        $this->business = $this->businessOf($this->user);
        $this->branch   = $this->business->locations()->create(['name' => 'Gulshan Branch', 'user_id' => $this->user->id]);
    }

    private function bank(array $fields = []): array
    {
        return [
            'type'                 => 'bank',
            'name'                 => 'City Bank Current',
            'currency'             => 'USD',
            'bank_name'            => 'City Bank',
            'holder_name'          => 'Uddin Traders Ltd',
            'account_number'       => '0012345678',
            'branch'               => 'Gulshan',
            'swift_code'           => 'CIBLBDDH',
            'bank_country'         => 'bd',
            'payment_instructions' => 'Quote the invoice number.',
            'show_on_documents'    => true,
            'notes'                => 'Signatory: the owner',
            ...$fields,
        ];
    }

    public function test_an_account_keeps_its_location_type_details_and_flags_and_its_name_is_its_own(): void
    {
        $data = $this->postJson('/api/v1/payment-accounts', $this->bank(['location_id' => $this->branch->id]))->assertCreated()->json('data');

        $this->assertSame(
            ['Gulshan Branch', 'Uddin Traders Ltd', 'Gulshan', 'CIBLBDDH', 'BD', true, true, true, false, false],
            [$data['location'], $data['holder_name'], $data['branch'], $data['swift_code'], $data['bank_country'], $data['show_on_documents'], $data['allow_customer_payments'], $data['allow_supplier_payments'], $data['default_receiving'], $data['default_payment']],
        );

        $this->postJson('/api/v1/payment-accounts', $this->bank())->assertJsonValidationErrors(['name' => 'Another payment account already has this name.']);
        $this->putJson("/api/v1/payment-accounts/{$data['id']}", $this->bank(['branch' => 'Banani']))->assertOk()->assertJsonPath('data.branch', 'Banani');

        $theirs = $this->user->workspaces()->create(['name' => 'Second Business'])->locations()->create(['name' => 'Elsewhere', 'user_id' => $this->user->id]);
        $this->postJson('/api/v1/payment-accounts', $this->bank(['name' => 'Other', 'location_id' => $theirs->id]))->assertJsonValidationErrors(['location_id' => 'Choose one of your locations.']);

        $this->postJson('/api/v1/payment-accounts', ['type' => 'payment_gateway', 'name' => 'Stripe', 'currency' => 'USD', 'bank_name' => 'Stripe', 'merchant_name' => 'Uddin Traders', 'settlement_currency' => 'usd'])
            ->assertCreated()
            ->assertJsonPath('data.settlement_currency', 'USD');
        $this->postJson('/api/v1/payment-accounts', ['type' => 'payment_gateway', 'name' => 'PayPal', 'currency' => 'USD', 'settlement_currency' => 'JPY'])->assertJsonValidationErrors('settlement_currency');
    }

    public function test_each_currency_and_location_has_one_default_of_each_kind(): void
    {
        $first  = $this->account(['name' => 'First']);
        $second = $this->account(['name' => 'Second']);
        $branch = $this->account(['name' => 'Branch Till', 'location_id' => $this->branch->id]);
        $paying = $this->account(['name' => 'Payroll', 'allow_customer_payments' => false]);

        $default = fn ($account, string $for) => $this->postJson("/api/v1/payment-accounts/{$account->id}/default", ['for' => $for]);

        $default($first, 'receiving')->assertOk()->assertJsonPath('data.default_receiving', true)->assertJsonPath('message', 'First is now the default receiving account for USD.');
        $default($second, 'receiving')->assertOk();
        $default($branch, 'receiving')->assertOk();
        $default($first, 'payment')->assertOk();

        $this->assertSame(
            ['First' => [false, true], 'Second' => [true, false], 'Branch Till' => [true, false], 'Payroll' => [false, false]],
            collect([$first, $second, $branch, $paying])->mapWithKeys(fn ($account) => [$account->name => [$account->fresh()->default_receiving, $account->fresh()->default_payment]])->all(),
        );

        $default($paying, 'receiving')->assertJsonValidationErrors(['for' => 'This account does not take customer payments.']);
        $second->update(['is_active' => false]);
        $default($second, 'payment')->assertJsonValidationErrors(['for' => 'An inactive account cannot be a default. Activate it first.']);
        $default($first, 'refunds')->assertJsonValidationErrors('for');
    }

    public function test_a_default_hands_over_when_it_is_deactivated_and_goes_when_it_moves_or_stops_taking_its_kind(): void
    {
        $first  = $this->account(['name' => 'First', 'default_receiving' => true, 'default_payment' => true]);
        $second = $this->account(['name' => 'Second']);

        $this->postJson("/api/v1/payment-accounts/{$first->id}/deactivate")
            ->assertJsonValidationErrors([
                'replacement_receiving_id' => 'Choose the account that becomes the default receiving account for USD instead.',
                'replacement_payment_id'   => 'Choose the account that becomes the default payment account for USD instead.',
            ]);

        // The same through an edit.
        $this->putJson("/api/v1/payment-accounts/{$first->id}", ['type' => 'cash', 'name' => 'First', 'currency' => 'USD', 'is_active' => false])->assertJsonValidationErrors('replacement_receiving_id');

        $this->postJson("/api/v1/payment-accounts/{$first->id}/deactivate", ['replacement_receiving_id' => $second->id, 'replacement_payment_id' => $second->id])
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.default_receiving', false);
        $this->assertSame([true, true], [$second->fresh()->default_receiving, $second->fresh()->default_payment]);

        // With nobody to take over, the default just goes.
        $this->postJson("/api/v1/payment-accounts/{$second->id}/deactivate")->assertOk()->assertJsonPath('data.default_payment', false);
        $this->postJson("/api/v1/payment-accounts/{$second->id}/activate")->assertOk()->assertJsonPath('data.is_active', true);

        $till = $this->account(['name' => 'Till', 'default_receiving' => true, 'default_payment' => true]);
        $this->putJson("/api/v1/payment-accounts/{$till->id}", ['type' => 'cash', 'name' => 'Till', 'currency' => 'USD', 'allow_supplier_payments' => false])
            ->assertJsonPath('data.default_receiving', true)
            ->assertJsonPath('data.default_payment', false);
        $this->putJson("/api/v1/payment-accounts/{$till->id}", ['type' => 'cash', 'name' => 'Till', 'currency' => 'USD', 'location_id' => $this->branch->id])
            ->assertJsonPath('data.default_receiving', false);
    }

    public function test_customer_money_only_goes_through_an_account_that_takes_it(): void
    {
        $id      = $this->issuedInvoice($this->atBranch());
        $payroll = $this->account(['name' => 'Payroll', 'allow_customer_payments' => false]);

        $this->pay($id, $payroll, '10')->assertJsonValidationErrors(['payment_account_id' => 'Choose one of your active payment accounts that takes customer payments.']);
        $this->pay($id, $this->account(), '10')->assertCreated();
    }

    public function test_proformas_and_invoices_print_the_accounts_shown_to_customers_as_they_were_when_numbered(): void
    {
        $this->business->forceFill(['additional_currencies' => ['EUR']])->save();
        $this->postJson('/api/v1/payment-accounts', $this->bank())->assertCreated();
        $this->postJson('/api/v1/payment-accounts', $this->bank(['name' => 'Euro Account', 'currency' => 'EUR', 'account_number' => 'EUR-ONLY']))->assertCreated();
        $this->postJson('/api/v1/payment-accounts', $this->bank(['name' => 'Hidden', 'account_number' => 'NOT-SHOWN', 'show_on_documents' => false]))->assertCreated();
        $this->postJson('/api/v1/payment-accounts', ['type' => 'bank', 'name' => 'London', 'currency' => 'USD', 'account_number' => '31926819', 'swift_code' => '60-16-13', 'bank_country' => 'GB', 'show_on_documents' => true])->assertCreated();

        $invoice = $this->issuedInvoice($this->atBranch());
        $html    = $this->html($invoice);

        $this->assertStringContainsString('Payment Details', $html);
        $this->assertStringContainsString('City Bank Current', $html);
        $this->assertStringContainsString('Account Number: 0012345678', $html);
        $this->assertStringContainsString('SWIFT / BIC: CIBLBDDH', $html);
        $this->assertStringContainsString('Bank Country: Bangladesh', $html);
        $this->assertStringContainsString('Sort Code: 60-16-13', $html);
        $this->assertStringContainsString('Quote the invoice number.', $html);
        $this->assertStringNotContainsString('EUR-ONLY', $html);
        $this->assertStringNotContainsString('NOT-SHOWN', $html);
        $this->assertStringNotContainsString('Signatory', $html);

        // Numbered, it keeps what it printed; a draft shows the accounts as they are.
        $this->business->paymentAccounts()->where('name', 'City Bank Current')->update(['account_number' => '9999999999']);
        $this->assertStringContainsString('0012345678', $this->html($invoice));
        $this->assertStringContainsString('9999999999', $this->html($this->draft('proformas', $this->atBranch())));
        $this->assertStringNotContainsString('Payment Details', $this->html($this->draft('quotations', $this->atBranch())));

        // The template's payment instructions switch covers them.
        $this->putJson('/api/v1/business-settings/templates/invoice', [
            'color' => '#1d4ed8', 'font' => 'sans', 'paper_size' => 'a4', 'header_layout' => 'logo_left', 'show_logo' => true,
            'visible_fields' => ['business_contact', 'customer_contact'], 'line_columns' => ['quantity', 'unit_price'],
            'show_notes' => true, 'show_terms' => true, 'show_payment_instructions' => false, 'footer_text' => null,
            'language' => 'en', 'date_format' => 'd M Y', 'number_format' => 'comma_dot',
        ])->assertOk();
        $this->assertStringNotContainsString('Payment Details', $this->html($this->issuedInvoice($this->atBranch())));
    }

    public function test_the_statement_says_where_to_pay(): void
    {
        $this->postJson('/api/v1/payment-accounts', $this->bank())->assertCreated();
        $this->issuedInvoice($this->atBranch());

        $notes = $this->getJson("/api/v1/reports/customer-statement?contact_id={$this->customer->id}")->assertOk()->json('data.notes');

        $this->assertContains('Pay to City Bank Current - Bank City Bank, Account Holder Uddin Traders Ltd, Account Number 0012345678, Branch Gulshan, SWIFT / BIC CIBLBDDH, Bank Country Bangladesh. Quote the invoice number.', $notes);
    }

    public function test_a_location_an_account_belongs_to_is_not_deleted(): void
    {
        $this->account(['name' => 'Branch Till', 'location_id' => $this->branch->id]);

        $this->deleteJson("/api/v1/locations/{$this->branch->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Payment accounts belong to this location. Move them to another location, or to the whole business, first.');
    }

    /** A document's fields at the branch - with a location of its own, the business's documents need one. */
    private function atBranch(): array
    {
        return ['location_id' => $this->branch->id];
    }

    /** The document as its PDF and secure link draw it. */
    private function html(int $id): string
    {
        $document = SalesDocument::with(['lines', 'contact', 'location', 'source'])->findOrFail($id);

        return app(DocumentRenderer::class)->sales($this->business, $document)->html;
    }
}
