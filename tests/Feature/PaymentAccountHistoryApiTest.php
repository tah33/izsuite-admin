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
 * Payment accounts as money moves through them (Doc 05 sections 5, 10 to 14
 * and 17): the history with its running balance - filtered and exported -
 * transfers between accounts, drafts, voids and other currencies, and what
 * money moving fixes about an account.
 */
class PaymentAccountHistoryApiTest extends TestCase
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

    private function transfer(int $from, int $to, string $amount, array $fields = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/account-transfers', [
            'from_account_id' => $from,
            'to_account_id'   => $to,
            'transfer_date'   => '2026-10-28',
            'amount'          => $amount,
            'reference'       => 'DEP-1',
            'complete'        => true,
            ...$fields,
        ]);
    }

    private function balance(int $account): string
    {
        return $this->getJson("/api/v1/payment-accounts/{$account}")->assertOk()->json('data.balance');
    }

    public function test_a_transfer_moves_money_between_accounts_and_voiding_it_takes_it_back(): void
    {
        $cash = $this->account(['opening_balance' => '500', 'opening_balance_date' => '2026-10-01'])->id;
        $bank = $this->account(['name' => 'City Bank', 'type' => 'bank'])->id;

        $id = $this->transfer($cash, $bank, '200')
            ->assertCreated()
            ->assertJsonPath('message', 'Transfer completed.')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.actions', ['void'])
            ->json('data.id');

        $this->assertSame(['300.00', '200.00'], [$this->balance($cash), $this->balance($bank)]);
        $this->getJson("/api/v1/payment-accounts/{$cash}/transactions")
            ->assertJsonPath('data.0.type', 'transfer_out')
            ->assertJsonPath('data.0.reference', 'DEP-1')
            ->assertJsonPath('data.0.running_balance', '300.00')
            ->assertJsonPath('data.0.source', ['type' => 'transfer', 'id' => $id]);
        $this->getJson("/api/v1/payment-accounts/{$bank}/transactions")->assertJsonPath('data.0.type', 'transfer_in')->assertJsonPath('data.0.running_balance', '200.00');

        $this->postJson("/api/v1/account-transfers/{$id}/void", ['reason' => 'Wrong account'])->assertOk()->assertJsonPath('message', 'Transfer voided.');

        $this->assertSame(['500.00', '0.00'], [$this->balance($cash), $this->balance($bank)]);
        $this->getJson("/api/v1/payment-accounts/{$bank}/transactions")->assertJsonPath('data.0.status', 'void');

        $this->postJson("/api/v1/account-transfers/{$id}/void")->assertConflict()->assertJsonPath('message', 'Only a completed transfer can be voided. A draft is deleted instead.');
        $this->deleteJson("/api/v1/account-transfers/{$id}")->assertConflict()->assertJsonPath('message', 'A completed transfer cannot be deleted. Void it instead.');
    }

    public function test_a_draft_transfer_moves_nothing_until_it_is_completed(): void
    {
        $cash = $this->account(['opening_balance' => '500', 'opening_balance_date' => '2026-10-01'])->id;
        $bank = $this->account(['name' => 'City Bank', 'type' => 'bank'])->id;

        $id = $this->transfer($cash, $bank, '200', ['complete' => false])
            ->assertCreated()
            ->assertJsonPath('message', 'Draft transfer saved.')
            ->assertJsonPath('data.actions', ['edit', 'complete', 'delete'])
            ->json('data.id');

        $this->assertSame('500.00', $this->balance($cash));
        $this->getJson("/api/v1/payment-accounts/{$cash}/transactions")->assertJsonPath('data.0.status', 'draft');

        $this->putJson("/api/v1/account-transfers/{$id}", ['from_account_id' => $cash, 'to_account_id' => $bank, 'transfer_date' => '2026-10-28', 'amount' => '150', 'reference' => 'DEP-1'])
            ->assertOk()
            ->assertJsonPath('data.amount', '150.00');
        $this->postJson("/api/v1/account-transfers/{$id}/complete")->assertOk()->assertJsonPath('message', 'Transfer completed.');
        $this->assertSame(['350.00', '150.00'], [$this->balance($cash), $this->balance($bank)]);

        $draft = $this->transfer($cash, $bank, '10', ['complete' => false])->json('data.id');
        $this->deleteJson("/api/v1/account-transfers/{$draft}")->assertOk()->assertJsonPath('message', 'Draft transfer deleted.');
        $this->assertCount(1, $this->getJson("/api/v1/payment-accounts/{$cash}/transactions")->json('data'));

        $this->transfer($cash, $cash, '10')->assertJsonValidationErrors(['to_account_id' => 'Money is transferred to another account.']);
        $this->transfer($cash, $bank, '10', ['transfer_date' => '2026-10-29'])->assertJsonValidationErrors(['transfer_date' => 'Money is recorded once it has moved: use today or an earlier date.']);
    }

    public function test_a_transfer_between_currencies_keeps_what_arrived_and_its_base_value(): void
    {
        $this->businessOf($this->user)->forceFill(['additional_currencies' => ['BDT']])->save();

        $cash  = $this->account(['opening_balance' => '500', 'opening_balance_date' => '2026-10-01'])->id;
        $dhaka = $this->account(['name' => 'Dhaka Bank', 'type' => 'bank', 'currency' => 'BDT'])->id;

        $this->transfer($cash, $dhaka, '100')->assertJsonValidationErrors(['amount_received' => 'Enter what arrived in Dhaka Bank, in BDT.']);

        $this->transfer($cash, $dhaka, '100', ['amount_received' => '12000'])
            ->assertCreated()
            ->assertJsonPath('data.exchange_rate', '120.00000000')
            ->assertJsonPath('data.to.currency', 'BDT');

        $this->assertSame(['400.00', '12000.00'], [$this->balance($cash), $this->balance($dhaka)]);
        $this->assertDatabaseHas('account_transactions', ['payment_account_id' => $dhaka, 'amount' => '12000.00', 'base_amount' => '100.00']);
    }

    public function test_money_moved_fixes_the_account_and_it_can_no_longer_be_deleted(): void
    {
        $cash = $this->account(['opening_balance' => '500', 'opening_balance_date' => '2026-10-01']);
        $bank = $this->account(['name' => 'City Bank', 'type' => 'bank']);

        $this->transfer($cash->id, $bank->id, '50')->assertCreated();

        $fields = ['type' => 'cash', 'name' => 'Cash', 'currency' => 'USD', 'opening_balance' => '500', 'opening_balance_date' => '2026-10-01'];

        $this->putJson("/api/v1/payment-accounts/{$cash->id}", [...$fields, 'name' => 'Front Desk Cash'])->assertOk()->assertJsonPath('data.balance', '450.00');
        $this->putJson("/api/v1/payment-accounts/{$cash->id}", [...$fields, 'opening_balance' => '600'])
            ->assertJsonValidationErrors(['opening_balance' => 'Money has moved through this account, so its opening balance stays as it is.']);
        $this->putJson("/api/v1/payment-accounts/{$cash->id}", [...$fields, 'opening_balance_date' => '2026-09-01'])->assertJsonValidationErrors(['opening_balance_date']);

        $this->deleteJson("/api/v1/payment-accounts/{$bank->id}")->assertConflict();

        $list = $this->getJson('/api/v1/payment-accounts')->json('data');
        $this->assertSame([['City Bank', '50.00', true], ['Front Desk Cash', '450.00', true]], array_map(fn (array $row) => [$row['name'], $row['balance'], $row['has_transactions']], $list));
    }

    public function test_the_history_filters_keep_the_real_running_balance_and_export_as_csv(): void
    {
        $cash = $this->account(['opening_balance' => '100', 'opening_balance_date' => '2026-10-01'])->id;
        $bank = $this->account(['name' => 'City Bank', 'type' => 'bank'])->id;

        $this->transfer($cash, $bank, '30', ['transfer_date' => '2026-10-20'])->assertCreated();
        $this->pay($this->issuedInvoice(), $this->account(['name' => 'Ignored']), '1')->assertCreated();
        $this->postJson('/api/v1/sales/invoices/'.$this->issuedInvoice().'/payments', ['payment_date' => '2026-10-28', 'payment_account_id' => $cash, 'amount' => '50'])->assertCreated();

        $this->getJson("/api/v1/payment-accounts/{$cash}/transactions")->assertJsonPath('data.*.running_balance', ['120.00', '70.00']);
        $this->getJson("/api/v1/payment-accounts/{$cash}/transactions?type=customer_payment")->assertJsonPath('data.*.running_balance', ['120.00']);
        $this->getJson("/api/v1/payment-accounts/{$cash}/transactions?date_to=2026-10-27")->assertJsonPath('data.*.running_balance', ['70.00']);

        $csv = $this->get("/api/v1/payment-accounts/{$cash}/transactions/export")->assertOk()->streamedContent();
        $lines = array_values(array_filter(explode("\n", str_replace("\xEF\xBB\xBF", '', $csv))));

        $this->assertSame('Date,Type,Reference,Contact,"Source Document","Money In","Money Out",Currency,"Running Balance",Status,Location', trim($lines[0]));
        $this->assertStringStartsWith('2026-10-20,"Transfer Out",DEP-1,,,,30.00,USD,70.00,Completed', trim($lines[1]));
        $this->assertStringContainsString('"Customer Payment",RCT-00002,"Uddin Traders",INV-00002,50.00,,USD,120.00', trim($lines[2]));
    }

    public function test_an_opening_balance_needs_its_date(): void
    {
        $this->postJson('/api/v1/payment-accounts', ['type' => 'cash', 'name' => 'Till', 'currency' => 'USD', 'opening_balance' => '50'])
            ->assertJsonValidationErrors(['opening_balance_date' => 'Enter the date the opening balance was the balance on.']);

        $this->postJson('/api/v1/payment-accounts', ['type' => 'cash', 'name' => 'Till', 'currency' => 'USD', 'opening_balance' => '50', 'opening_balance_date' => '2026-10-28'])
            ->assertCreated()
            ->assertJsonPath('data.balance', '50.00');
    }
}
