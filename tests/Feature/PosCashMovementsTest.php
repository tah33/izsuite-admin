<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\AccountTransaction;
use App\Models\User\AccountTransfer;
use App\Models\User\Contact;
use App\Models\User\Expense;
use App\Models\User\ExpensePayment;
use App\Models\User\Item;
use App\Models\User\Location;
use App\Models\User\PaymentAccount;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Support\Permissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersExpensesLite;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 18 Phase F1: cash movements (section 18.26) - at Front Counter (R1) in
 * Dhaka, taking Cash into the Shop Till, with a Safe (a cash account) and
 * City Bank beside it. Cash in, cash out, a safe drop, a bank deposit and a
 * float adjustment, each a completed transfer between the till and another
 * account, with why; the session counts them. Above Settings' limit, one
 * waits for a manager's approval.
 *
 * And (Phase F2) a paid out: an expense paid from the drawer, recorded in
 * Expenses and paid from the till.
 *
 * A float of 50.00 and a cash sale of a mug, 15.00. Friday 20 November 2026.
 */
class PosCashMovementsTest extends TestCase
{
    use OffersExpensesLite;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const MOVES = '/api/v1/pos/cash-movements';

    private const REGISTERS = '/api/v1/pos/registers';

    private User $user;

    private Workspace $business;

    private Location $dhaka;

    private Contact $walkIn;

    private Item $mug;

    private PaymentAccount $till;

    private PaymentAccount $bank;

    private PaymentAccount $safe;

    private int $cash;

    private int $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);

        $this->offerSalesLite();
        Application::create(['app_key' => 'pos', 'name' => 'Point of Sale', 'family_key' => 'pos', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 30, 'is_active' => true, 'status' => 'available'])
            ->packages()->attach(Package::pluck('id'));
        $this->offerExpensesLite();

        $this->travelTo(Carbon::parse('2026-11-20 10:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->business->forceFill(['base_currency' => 'USD', 'tax_mode' => 'later'])->save();
        $this->dhaka = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka', 'code' => 'DHK']);
        $this->till  = $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Shop Till', 'currency' => 'USD']);
        $this->bank  = $this->business->paymentAccounts()->create(['type' => 'bank', 'name' => 'City Bank', 'currency' => 'USD']);

        $this->postJson('/api/v1/apps/pos/activate')->assertOk();
        $this->cash = (int) $this->getJson(self::REGISTERS.'/options')->assertOk()->json('data.payment_methods.0.id');
        $this->safe = $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Safe', 'currency' => 'USD']);

        $this->walkIn = $this->business->contacts()->create(['contact_type' => 'individual', 'role' => 'customer', 'name' => 'Walk-in Customer']);
        $this->business->posSetting()->firstOrFail()->update(['walk_in_contact_id' => $this->walkIn->id]);
        $this->mug = $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Mug', 'unit' => 'Piece', 'sale_price' => '15.00']);

        $register      = $this->postJson(self::REGISTERS, [
            'name' => 'Front Counter', 'code' => 'R1', 'location_id' => $this->dhaka->id, 'currency' => 'USD', 'payment_method_ids' => [$this->cash], 'cashier_ids' => [$this->user->id],
        ])->assertCreated()->json('data');
        $this->session = $this->postJson('/api/v1/pos/sessions', ['pos_register_id' => $register['id'], 'opening_float' => '50'])->assertCreated()->json('data.id');

        $this->postJson('/api/v1/pos/orders', [
            'contact_id' => $this->walkIn->id, 'lines' => [['item_id' => $this->mug->id, 'quantity' => '1']], 'total' => '15.00', 'payments' => [['method_id' => $this->cash, 'amount' => '15.00']],
        ])->assertCreated();
    }

    private function move(string $type, PaymentAccount $account, string $amount, string $reason = 'Too much cash in the drawer', array $with = []): TestResponse
    {
        return $this->postJson(self::MOVES, ['type' => $type, 'account_id' => $account->id, 'amount' => $amount, 'reason' => $reason, ...$with]);
    }

    private function totals(int $session): array
    {
        $totals = $this->getJson("/api/v1/pos/sessions/{$session}")->assertOk()->json('data.totals');

        return [$totals['cash_in'], $totals['cash_out'], $totals['expected_cash']];
    }

    private function balanceOf(PaymentAccount $account): string
    {
        $moved = fn (string $direction) => (float) AccountTransaction::where('payment_account_id', $account->id)->where('status', 'completed')->where('direction', $direction)->sum('amount');

        return number_format($moved(AccountTransaction::IN) - $moved(AccountTransaction::OUT), 2, '.', '');
    }

    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => Permissions::expand($permissions)]);
        $this->business->members()->create(['user_id' => $user->id, 'name' => $user->name, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    /** $user's own session, at a second register taking Cash, with $float in its drawer: its id. */
    private function backCounter(User $user, string $float): int
    {
        Sanctum::actingAs($this->user);
        $back = $this->postJson(self::REGISTERS, [
            'name' => 'Back Counter', 'code' => 'R2', 'location_id' => $this->dhaka->id, 'currency' => 'USD', 'payment_method_ids' => [$this->cash], 'cashier_ids' => [$user->id],
        ])->assertCreated()->json('data');
        Sanctum::actingAs($user);

        return $this->postJson('/api/v1/pos/sessions', ['pos_register_id' => $back['id'], 'opening_float' => $float])->assertCreated()->json('data.id');
    }

    public function test_cash_moves_between_the_drawer_and_another_account_and_the_session_counts_it(): void
    {
        $options = $this->getJson(self::MOVES.'/options')->assertOk()->json('data');
        $this->assertSame(['id' => $this->till->id, 'name' => 'Shop Till'], $options['drawer']);
        $this->assertSame([['id' => $this->bank->id, 'name' => 'City Bank', 'type' => 'bank'], ['id' => $this->safe->id, 'name' => 'Safe', 'type' => 'cash']], $options['accounts']);
        $this->assertSame(['USD', '65.00', null], [$options['currency'], $options['expected'], $options['limit']]);

        // A safe drop: a completed transfer out of the till into the safe.
        $this->move('safe_drop', $this->safe, '40')->assertCreated()
            ->assertJsonPath('message', 'Safe drop CM-00001 recorded.')
            ->assertJsonPath('data', [
                'id' => 1, 'number' => 'CM-00001', 'type' => 'safe_drop', 'direction' => 'out', 'amount' => '40.00', 'currency' => 'USD', 'account' => 'Safe',
                'payee' => null, 'expense' => null,
                'reason' => 'Too much cash in the drawer', 'status' => 'completed', 'recorded_by' => $this->user->name, 'recorded_at' => '2026-11-20T10:00:00+00:00', 'decline_reason' => null,
            ]);
        $transfer = AccountTransfer::sole();
        $this->assertSame([$this->till->id, $this->safe->id, '40.00', 'completed', 'CM-00001'], [$transfer->from_account_id, $transfer->to_account_id, (string) $transfer->amount, $transfer->status, $transfer->reference]);
        $this->assertSame(['-25.00', '40.00'], [$this->balanceOf($this->till), $this->balanceOf($this->safe)]);
        $this->assertSame(['0.00', '40.00', '25.00'], $this->totals($this->session));
        $this->assertSame(1, ActivityLog::where('description', 'Recorded the safe drop CM-00001 of USD 40.00, from Shop Till into Safe in the POS session S-00001: Too much cash in the drawer')->count());

        // A bank deposit goes into a bank account - and no more out of the drawer than it should hold.
        $this->move('bank_deposit', $this->safe, '10', 'Banking')->assertUnprocessable()->assertJsonPath('errors.account_id.0', 'A bank deposit goes into a bank account.');
        $this->move('bank_deposit', $this->bank, '30', 'Banking')->assertUnprocessable()->assertJsonPath('errors.amount.0', 'The drawer should hold USD 25.00: no more can come out of it.');
        $this->move('bank_deposit', $this->bank, '20', 'Banking')->assertCreated()->assertJsonPath('data.account', 'City Bank');

        // Cash in from the safe; the float made smaller, saying which way.
        $this->move('cash_in', $this->safe, '10', 'Change for the evening')->assertCreated()->assertJsonPath('data.direction', 'in');
        $this->move('float_adjustment', $this->safe, '5', 'A smaller float')->assertUnprocessable()->assertJsonPath('errors.direction.0', 'Say which way the float changes: into the drawer, or out of it.');
        $this->move('float_adjustment', $this->safe, '5', 'A smaller float', ['direction' => 'out'])->assertCreated();
        $this->assertSame(['10.00', '65.00', '10.00'], $this->totals($this->session));
        $this->assertSame(['-40.00', '35.00', '20.00'], [$this->balanceOf($this->till), $this->balanceOf($this->safe), $this->balanceOf($this->bank)]);

        // Never the drawer's own account.
        $this->move('cash_out', $this->till, '1', 'Wrong one')->assertUnprocessable()
            ->assertJsonPath('errors.account_id.0', "Choose another of the business's active accounts in USD: not the drawer's own, Shop Till.");

        $listed = $this->getJson("/api/v1/pos/sessions/{$this->session}/cash-movements")->assertOk()->json('data');
        $this->assertSame([['CM-00004', 'float_adjustment', 'out'], ['CM-00003', 'cash_in', 'in'], ['CM-00002', 'bank_deposit', 'out'], ['CM-00001', 'safe_drop', 'out']],
            array_map(fn (array $row) => [$row['number'], $row['type'], $row['direction']], $listed));
    }

    public function test_above_the_limit_a_movement_waits_for_a_managers_approval(): void
    {
        $this->putJson('/api/v1/business-settings/pos/cash-count', ['variance_limit' => null, 'counts_denominations' => false, 'cash_movement_limit' => '50'])->assertOk()
            ->assertJsonPath('data.cash_movement_limit', '50.00');

        // Whoever may approve them records any amount at once.
        $this->move('safe_drop', $this->safe, '60')->assertCreated()->assertJsonPath('data.status', 'completed');

        $clerk   = $this->member(['pos.cash.move']);
        $session = $this->backCounter($clerk, '100');

        $waits = $this->move('safe_drop', $this->safe, '80', 'End of shift')->assertCreated()
            ->assertJsonPath('message', "Safe drop CM-00002 waits for a manager's approval: nothing moves until then.")
            ->assertJsonPath('data.status', 'pending')->json('data.id');
        $this->assertSame(1, AccountTransfer::count());
        $this->assertSame(['0.00', '0.00', '100.00'], $this->totals($session));
        $this->move('safe_drop', $this->safe, '20', 'Lunch')->assertCreated()->assertJsonPath('data.status', 'completed');

        // The session closes once nothing waits; its cashier may not approve it.
        $this->postJson("/api/v1/pos/sessions/{$session}/close", ['counted_cash' => '80'])->assertConflict()
            ->assertJsonPath('message', 'CM-00002 waits for approval: approve or decline it, then close the session.');
        $this->postJson(self::MOVES."/{$waits}/approve")->assertForbidden();

        Sanctum::actingAs($this->user);
        $this->postJson(self::MOVES."/{$waits}/approve")->assertOk()
            ->assertJsonPath('message', 'Safe drop CM-00002 approved: the cash has moved.')->assertJsonPath('data.status', 'completed');
        $this->postJson(self::MOVES."/{$waits}/approve")->assertConflict()->assertJsonPath('message', 'CM-00002 is not waiting for approval.');
        $this->assertSame([3, ['0.00', '100.00', '0.00']], [AccountTransfer::count(), $this->totals($session)]);

        // Another, declined with why: nothing moves.
        Sanctum::actingAs($clerk);
        $second = $this->move('cash_in', $this->safe, '70', 'More change')->assertCreated()->json('data.id');
        Sanctum::actingAs($this->user);
        $this->postJson(self::MOVES."/{$second}/decline")->assertUnprocessable()->assertJsonPath('errors.reason.0', 'Say why it is declined.');
        $this->postJson(self::MOVES."/{$second}/decline", ['reason' => 'Not needed'])->assertOk()
            ->assertJsonPath('message', 'Cash in CM-00004 declined: nothing moves.')->assertJsonPath('data.decline_reason', 'Not needed');
        $this->assertSame([3, ['0.00', '100.00', '0.00']], [AccountTransfer::count(), $this->totals($session)]);

        foreach ([
            'Recorded the safe drop CM-00002 of USD 80.00, from Shop Till into Safe in the POS session S-00002, waiting for its approval: End of shift',
            'Approved the safe drop CM-00002 of USD 80.00 in the POS session S-00002',
            'Declined the cash in CM-00004 of USD 70.00 in the POS session S-00002: Not needed',
        ] as $logged) {
            $this->assertSame(1, ActivityLog::where('description', $logged)->count(), $logged);
        }
    }

    public function test_a_paid_out_is_an_expense_paid_from_the_drawer(): void
    {
        $cleaning   = $this->business->expenseCategories()->create(['name' => 'Window Cleaning']);
        $retired    = $this->business->expenseCategories()->create(['name' => 'Retired', 'is_active' => false]);
        $categories = $this->getJson(self::MOVES.'/options')->assertOk()->json('data.categories');
        $this->assertContains(['id' => $cleaning->id, 'name' => 'Window Cleaning'], $categories);
        $this->assertNotContains(['id' => $retired->id, 'name' => 'Retired'], $categories);

        $paidOut = fn (array $with) => $this->postJson(self::MOVES, ['type' => 'paid_out', 'amount' => '12.50', 'reason' => 'The shop windows', ...$with]);

        // Who it is paid to and its category - an active one - and no other account.
        $paidOut([])->assertUnprocessable()->assertJsonValidationErrors(['payee', 'expense_category_id'])->assertJsonMissingValidationErrors('account_id');
        $paidOut(['payee' => 'Shine Cleaners', 'expense_category_id' => $retired->id])->assertUnprocessable()
            ->assertJsonPath('errors.expense_category_id.0', "Choose one of the business's active expense categories.");

        $paidOut(['payee' => 'Shine Cleaners', 'expense_category_id' => $cleaning->id])->assertCreated()
            ->assertJsonPath('message', 'Paid out CM-00001 recorded.')
            ->assertJsonPath('data.payee', 'Shine Cleaners')->assertJsonPath('data.account', null)->assertJsonPath('data.expense', Expense::sole()->number);

        // Underneath: its expense, recorded and paid from the till.
        $expense = Expense::sole();
        $this->assertSame(['Shine Cleaners', 'recorded', 'paid', '12.50', 'USD', $this->dhaka->id, 'CM-00001'],
            [$expense->payee_name, $expense->status->value, $expense->payment_status, (string) $expense->grand_total, $expense->currency, $expense->location_id, $expense->reference]);
        $this->assertSame([$cleaning->id, 'The shop windows'], [$expense->lines->sole()->expense_category_id, $expense->lines->sole()->description]);
        $this->assertSame(['12.50', $this->till->id], [(string) ExpensePayment::sole()->amount, ExpensePayment::sole()->payment_account_id]);
        $this->assertSame(['2.50', ['0.00', '12.50', '52.50']], [$this->balanceOf($this->till), $this->totals($this->session)]);
        $this->assertSame(1, ActivityLog::where('description', 'Recorded the paid out CM-00001 of USD 12.50, to Shine Cleaners from Shop Till in the POS session S-00001: The shop windows')->count());

        // Above the limit it waits - its expense made once approved.
        $this->putJson('/api/v1/business-settings/pos/cash-count', ['variance_limit' => null, 'counts_denominations' => false, 'cash_movement_limit' => '10'])->assertOk();
        $clerk = $this->member(['pos.cash.move']);
        $this->backCounter($clerk, '40');
        $waits = $paidOut(['payee' => 'Rahim Couriers', 'expense_category_id' => $cleaning->id])->assertCreated()
            ->assertJsonPath('data.status', 'pending')->assertJsonPath('data.expense', null)->json('data.id');
        $this->assertSame(1, Expense::count());
        Sanctum::actingAs($this->user);
        $this->postJson(self::MOVES."/{$waits}/approve")->assertOk()->assertJsonPath('data.expense', Expense::latest('id')->firstOrFail()->number);
        $this->assertSame([2, 'Rahim Couriers'], [Expense::count(), Expense::latest('id')->firstOrFail()->payee_name]);

        // A drawer whose account pays no expenses pays none out.
        $this->till->update(['allow_supplier_payments' => false]);
        $this->assertNull($this->getJson(self::MOVES.'/options')->assertOk()->json('data.categories'));
        $paidOut(['payee' => 'Shine Cleaners', 'expense_category_id' => $cleaning->id])->assertConflict()
            ->assertJsonPath('message', 'Shop Till does not pay expenses: allow it in Payment Accounts to pay one out of the drawer.');
    }

    public function test_cash_movements_need_their_permissions_and_an_open_session(): void
    {
        Sanctum::actingAs($this->member(['pos.selling.sell']));
        $this->getJson(self::MOVES.'/options')->assertForbidden();
        $this->move('cash_in', $this->safe, '5')->assertForbidden();

        Sanctum::actingAs($this->member(['pos.cash.move']));
        $this->getJson(self::MOVES.'/options')->assertConflict()->assertJsonPath('message', 'Open your session first: you sell in a session of your own, at a register.');

        Sanctum::actingAs($this->user);
        $this->postJson(self::MOVES, ['type' => 'tip', 'account_id' => $this->safe->id, 'amount' => '0', 'reason' => ''])->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'amount', 'reason']);

        // An account in another currency is not one cash moves to.
        $euros = $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Euro Box', 'currency' => 'EUR']);
        $this->move('cash_in', $euros, '5')->assertUnprocessable()->assertJsonValidationErrors('account_id');
        $this->assertSame(0, AccountTransfer::count());
    }
}
