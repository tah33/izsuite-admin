<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Frontend\MenuItem;
use App\Models\Shared\ActivityLog;
use App\Models\User\PaymentAccount;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * A business's cash drawers, bank accounts, wallets and cards (Doc 01 sections
 * 6.2 and 11): each in one of the business's currencies, and the first one
 * brings Payment Accounts into the sidebar.
 */
class PaymentAccountsApiTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded first, so super-admin holds id 1 and the owner is not taken for an admin.
        $this->seed(RoleSeeder::class);

        Currency::create(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);
        Currency::create(['name' => 'Euro', 'code' => 'EUR', 'symbol' => '€', 'is_default' => false, 'is_active' => true]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type'            => 'bank',
            'name'            => 'City Bank Current',
            'currency'        => 'USD',
            'bank_name'       => 'City Bank',
            'account_number'       => '0012-3456',
            'opening_balance'      => '1500.5',
            'opening_balance_date' => '2026-10-01',
        ], $overrides);
    }

    /** An account with nothing in it - the only kind that can be deleted (Doc 05 section 17). */
    private function emptyAccount(array $overrides = []): array
    {
        return $this->payload(['opening_balance' => null, 'opening_balance_date' => null, ...$overrides]);
    }

    private function accountOf(User $user, array $overrides = []): PaymentAccount
    {
        return $this->businessOf($user)->paymentAccounts()->create($this->payload($overrides));
    }

    /* ---------------------------------------------------------- access */

    public function test_every_endpoint_needs_a_token_and_a_business(): void
    {
        $account = $this->accountOf(User::factory()->create());

        $this->getJson('/api/v1/payment-accounts')->assertUnauthorized();
        $this->postJson('/api/v1/payment-accounts', $this->payload())->assertUnauthorized();
        $this->putJson("/api/v1/payment-accounts/{$account->id}", $this->payload(['name' => 'Hijacked']))->assertUnauthorized();
        $this->deleteJson("/api/v1/payment-accounts/{$account->id}")->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/payment-accounts')
            ->assertNotFound()
            ->assertJsonPath('message', 'Create a workspace first.');

        $this->assertDatabaseHas('payment_accounts', ['id' => $account->id, 'name' => 'City Bank Current']);
    }

    /* ---------------------------------------------------------- add */

    public function test_an_account_is_added_in_one_of_the_business_currencies(): void
    {
        $user = $this->signIn();

        $response = $this->postJson('/api/v1/payment-accounts', $this->payload(['currency' => 'usd']))
            ->assertCreated()
            ->assertJsonPath('message', 'Payment account added successfully.')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.opening_balance', '1500.50')
            ->assertJsonPath('data.is_active', true);

        $this->assertSame(
            [
                'id', 'type', 'name', 'currency', 'location_id', 'location', 'bank_name', 'holder_name', 'account_number', 'branch', 'swift_code', 'bank_country',
                'merchant_name', 'merchant_number', 'settlement_currency', 'opening_balance', 'opening_balance_date', 'balance', 'has_transactions',
                'payment_instructions', 'show_on_documents', 'allow_customer_payments', 'allow_supplier_payments', 'default_receiving', 'default_payment', 'notes', 'is_active',
            ],
            array_keys($response->json('data')),
        );
        $this->assertSame(['2026-10-01', '1500.50', false], [$response->json('data.opening_balance_date'), $response->json('data.balance'), $response->json('data.has_transactions')]);
        $this->assertDatabaseHas('payment_accounts', ['id' => $response->json('data.id'), 'workspace_id' => $this->businessOf($user)->id]);
    }

    public function test_the_currency_must_be_the_base_one_or_one_switched_on(): void
    {
        $this->signIn();

        // EUR is a platform currency, but this business has not switched it on.
        $this->postJson('/api/v1/payment-accounts', $this->payload(['currency' => 'EUR']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['currency' => 'Use the base currency or one switched on under Settings > Currencies.']);

        $this->putJson('/api/v1/business-settings/currencies', ['base_currency' => 'USD', 'additional_currencies' => ['EUR']])->assertOk();

        $this->postJson('/api/v1/payment-accounts', $this->payload(['currency' => 'EUR']))->assertCreated();
    }

    public function test_the_type_and_name_and_currency_are_required(): void
    {
        $this->signIn();

        $this->postJson('/api/v1/payment-accounts', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'name', 'currency'])
            ->assertJsonMissingValidationErrors(['opening_balance', 'bank_name']);

        $this->postJson('/api/v1/payment-accounts', $this->payload(['type' => 'crypto']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type' => 'Choose what kind of account this is.']);

        foreach (['cash', 'bank', 'mobile_money', 'payment_gateway', 'other'] as $type) {
            $this->postJson('/api/v1/payment-accounts', ['type' => $type, 'name' => ucfirst($type), 'currency' => 'USD'])->assertCreated();
        }

        // Doc 05 has no "card" type: a card machine's money comes through a payment gateway.
        $this->postJson('/api/v1/payment-accounts', ['type' => 'card', 'name' => 'Card', 'currency' => 'USD'])->assertJsonValidationErrors('type');
    }

    public function test_the_opening_balance_is_to_the_cent_may_be_negative_and_is_zero_when_empty(): void
    {
        $this->signIn();

        $this->postJson('/api/v1/payment-accounts', $this->payload(['name' => 'Overdrawn', 'opening_balance' => '-250.75']))
            ->assertCreated()
            ->assertJsonPath('data.opening_balance', '-250.75');

        $this->postJson('/api/v1/payment-accounts', $this->payload(['name' => 'Nothing Yet', 'opening_balance' => null]))
            ->assertCreated()
            ->assertJsonPath('data.opening_balance', '0.00');

        $id = $this->postJson('/api/v1/payment-accounts', $this->payload(['opening_balance' => '']))
            ->assertCreated()
            ->assertJsonPath('data.opening_balance', '0.00')
            ->json('data.id');

        foreach (['1.005', 'lots', '99999999999999'] as $balance) {
            $this->postJson('/api/v1/payment-accounts', $this->payload(['opening_balance' => $balance]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('opening_balance');
        }

        // Left out of an edit, it is left alone; cleared, it is zero.
        $this->putJson("/api/v1/payment-accounts/{$id}", $this->payload(['opening_balance' => '10']))->assertOk();
        $this->putJson("/api/v1/payment-accounts/{$id}", array_diff_key($this->payload(), ['opening_balance' => true]))
            ->assertOk()
            ->assertJsonPath('data.opening_balance', '10.00');
        $this->putJson("/api/v1/payment-accounts/{$id}", $this->payload(['opening_balance' => null]))
            ->assertOk()
            ->assertJsonPath('data.opening_balance', '0.00');
    }

    /* ---------------------------------------------------------- list, edit, delete */

    public function test_the_list_holds_every_account_alphabetically(): void
    {
        $user = $this->signIn();

        $this->getJson('/api/v1/payment-accounts')->assertOk()->assertExactJson(['data' => []]);

        $this->accountOf($user, ['name' => 'Petty Cash', 'type' => 'cash']);
        $this->accountOf($user, ['name' => 'Mobile Wallet', 'type' => 'mobile_money']);
        $this->accountOf($user, ['name' => 'City Bank Current']);

        $this->getJson('/api/v1/payment-accounts')
            ->assertOk()
            ->assertJsonMissingPath('pagination')
            ->assertJsonPath('data.*.name', ['City Bank Current', 'Mobile Wallet', 'Petty Cash']);
    }

    public function test_an_account_is_updated_and_deleted(): void
    {
        $user    = $this->signIn();
        $account = $this->accountOf($user);

        $this->putJson("/api/v1/payment-accounts/{$account->id}", $this->payload(['name' => 'City Bank Savings', 'is_active' => false]))
            ->assertOk()
            ->assertJsonPath('message', 'Payment account updated successfully.')
            ->assertJsonPath('data.name', 'City Bank Savings')
            ->assertJsonPath('data.is_active', false);

        // An account with an opening balance is deactivated, not deleted (Doc 05 section 17).
        $this->deleteJson("/api/v1/payment-accounts/{$account->id}")
            ->assertConflict()
            ->assertJsonPath('message', 'This account has an opening balance or money recorded through it, so it cannot be deleted. Deactivate it instead.');

        $this->putJson("/api/v1/payment-accounts/{$account->id}", $this->emptyAccount(['name' => 'City Bank Savings']))->assertOk();

        $this->deleteJson("/api/v1/payment-accounts/{$account->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Payment account deleted successfully.');

        $this->assertDatabaseMissing('payment_accounts', ['id' => $account->id]);
    }

    public function test_another_business_account_is_not_found(): void
    {
        $user    = $this->signIn();
        $second  = $user->workspaces()->create(['name' => 'Second Business']);
        $sibling = $second->paymentAccounts()->create($this->payload(['name' => 'In Second']));
        $foreign = $this->accountOf(User::factory()->create(), ['name' => 'Someone Else']);
        $this->accountOf($user);

        $this->getJson('/api/v1/payment-accounts')->assertJsonPath('data.*.name', ['City Bank Current']);
        $this->getJson('/api/v1/payment-accounts', $this->pick($second))->assertJsonPath('data.*.name', ['In Second']);

        foreach ([$sibling, $foreign] as $account) {
            $this->putJson("/api/v1/payment-accounts/{$account->id}", $this->payload(['name' => 'Hijacked']))
                ->assertNotFound()
                ->assertJsonPath('message', 'Payment account not found.');
            $this->deleteJson("/api/v1/payment-accounts/{$account->id}")->assertNotFound();
        }

        $this->assertSame(['In Second', 'Someone Else'], [$sibling->fresh()->name, $foreign->fresh()->name]);

        $second->delete();
        $this->assertDatabaseMissing('payment_accounts', ['id' => $sibling->id]);
    }

    /* ---------------------------------------------------------- the sidebar */

    public function test_payment_accounts_shows_in_the_sidebar_from_the_first_account_to_the_last(): void
    {
        MenuItem::create(['section' => 'core', 'label' => 'Dashboard', 'route' => '/dashboard', 'sort_order' => 1]);
        MenuItem::create(['section' => 'core', 'label' => 'Payment Accounts', 'route' => '/payment-accounts', 'sort_order' => 2, 'show_if' => 'has_payment_account']);

        $this->signIn();
        $core = fn () => array_column($this->getJson('/api/v1/navigation')->assertOk()->json('data.menu.core'), 'label');

        $this->assertSame(['Dashboard'], $core());

        $first  = $this->postJson('/api/v1/payment-accounts', $this->emptyAccount())->assertCreated()->json('data.id');
        $this->assertSame(['Dashboard', 'Payment Accounts'], $core());

        $second = $this->postJson('/api/v1/payment-accounts', $this->emptyAccount(['name' => 'Cash', 'type' => 'cash']))->json('data.id');
        $this->deleteJson("/api/v1/payment-accounts/{$first}")->assertOk();
        $this->assertSame(['Dashboard', 'Payment Accounts'], $core());

        $this->deleteJson("/api/v1/payment-accounts/{$second}")->assertOk();
        $this->assertSame(['Dashboard'], $core());
    }

    public function test_only_the_first_and_last_account_change_the_sidebar_version(): void
    {
        $user     = $this->signIn();
        $business = $this->businessOf($user);
        $version  = fn () => $business->fresh()->nav_version;
        $start    = $version();

        $first = $this->accountOf($user);
        $this->assertSame($start + 1, $version());

        $second = $this->accountOf($user, ['name' => 'Cash']);
        $second->delete();
        $this->assertSame($start + 1, $version());

        $first->delete();
        $this->assertSame($start + 2, $version());
    }

    /* ---------------------------------------------------------- activity */

    public function test_each_write_is_logged_once_by_name(): void
    {
        $user = $this->signIn();

        $id = $this->postJson('/api/v1/payment-accounts', $this->emptyAccount())->json('data.id');
        $this->putJson("/api/v1/payment-accounts/{$id}", $this->emptyAccount(['account_number' => '9999']))->assertOk();
        $this->deleteJson("/api/v1/payment-accounts/{$id}")->assertOk();

        $logs = ActivityLog::where('user_id', $user->id)->orderBy('id')->get();

        $this->assertSame(
            ['Added payment account "City Bank Current"', 'Updated payment account "City Bank Current"', 'Deleted payment account "City Bank Current"'],
            $logs->pluck('description')->all(),
        );
        $this->assertSame(['account_number'], $logs[1]->properties['fields']);
    }
}
