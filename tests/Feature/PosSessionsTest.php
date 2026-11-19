<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\Location;
use App\Models\User\PosSessionClosing;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 18 Phase B1: POS sessions (sections 18.6, 18.27 and 18.28) - opened
 * by a register's cashier with the opening float, one at a time per
 * register and per cashier, closed by the cashier with the cash counted,
 * kept as a Z report that never changes, and seen where the register's
 * location may be used. A register with sessions is kept, and stays as it
 * is while one is open.
 */
class PosSessionsTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const SESSIONS = '/api/v1/pos/sessions';

    private const REGISTERS = '/api/v1/pos/registers';

    private User $user;

    private User $ana;

    private User $bob;

    private Workspace $business;

    private Location $dhaka;

    private Location $chattogram;

    /** The registers by code: R1 and R2 at Dhaka, CTG1 at Chattogram. */
    private array $registers = [];

    private int $cash;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $pos = Application::create(['app_key' => 'pos', 'name' => 'Point of Sale', 'family_key' => 'pos', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 30, 'is_active' => true, 'status' => 'available']);
        $pos->packages()->attach(Package::pluck('id'));

        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->business->forceFill(['base_currency' => 'USD', 'additional_currencies' => ['EUR']])->save();
        $this->dhaka      = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka', 'code' => 'DHK']);
        $this->chattogram = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Chattogram', 'code' => 'CTG']);
        $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Shop Till', 'currency' => 'USD']);

        $this->ana = $this->member('Ana', 'Ahmed', ['pos.sessions.view', 'pos.sessions.open']);
        $this->bob = $this->member('Bob', 'Biswas', ['pos.sessions.view', 'pos.sessions.open'], [$this->chattogram->id]);

        $this->postJson('/api/v1/apps/pos/activate')->assertOk();
        $this->cash = (int) $this->getJson(self::REGISTERS.'/options')->assertOk()->json('data.payment_methods.0.id');

        $this->addRegister('Front Counter', 'R1', $this->dhaka, [$this->user->id, $this->ana->id]);
        $this->addRegister('Back Counter', 'R2', $this->dhaka, [$this->user->id]);
        $this->addRegister('Port Counter', 'CTG1', $this->chattogram, [$this->bob->id]);
    }

    /** A member called $first $last whose role holds $permissions - at $locations only, when given. */
    private function member(string $first, string $last, array $permissions, ?array $locations = null): User
    {
        $user   = User::factory()->create(['first_name' => $first, 'last_name' => $last]);
        $role   = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $member = $this->business->members()->create([
            'user_id'         => $user->id,
            'status'          => 'active',
            'role_id'         => $role->id,
            'location_access' => $locations === null ? WorkspaceMember::ALL_LOCATIONS : WorkspaceMember::SELECTED_LOCATIONS,
        ]);
        $member->locations()->sync($locations ?? []);

        return $user;
    }

    private function addRegister(string $name, string $code, Location $location, array $cashierIds): void
    {
        $this->registers[$code] = $this->postJson(self::REGISTERS, [
            'name' => $name, 'code' => $code, 'location_id' => $location->id, 'currency' => 'USD', 'payment_method_ids' => [$this->cash], 'cashier_ids' => $cashierIds,
        ])->assertCreated()->json('data');
    }

    /** The register's form as it was saved, changed as $fields say. */
    private function saveRegister(string $code, array $fields): TestResponse
    {
        $register = $this->registers[$code];

        return $this->putJson(self::REGISTERS."/{$register['id']}", [
            'name' => $register['name'], 'code' => $register['code'], 'location_id' => $register['location']['id'], 'currency' => $register['currency'],
            'payment_method_ids' => [$this->cash], 'cashier_ids' => array_column($register['cashiers'], 'id'), 'status' => $register['status'], ...$fields,
        ]);
    }

    private function open(string $code, string $float = '100.50', array $fields = []): TestResponse
    {
        return $this->postJson(self::SESSIONS, ['pos_register_id' => $this->registers[$code]['id'], 'opening_float' => $float, ...$fields]);
    }

    public function test_a_cashier_opens_a_session_and_closes_it_with_its_z_report(): void
    {
        $refusals = collect($this->getJson(self::SESSIONS.'/options')->assertOk()->json('data.registers'))->pluck('refusal', 'code')->all();
        $this->assertSame(['R2' => null, 'R1' => null, 'CTG1' => "You are not one of this register's cashiers."], $refusals);

        $session = $this->open('R1', '100.50', ['opening_notes' => 'Float from the safe'])->assertCreated()
            ->assertJsonPath('message', 'Session S-00001 opened.')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.register.location.name', 'Dhaka')
            ->assertJsonPath('data.cashier.name', $this->user->name)
            ->assertJsonPath('data.opening_float', '100.50')
            ->assertJsonPath('data.opening_notes', 'Float from the safe')
            ->assertJsonPath('data.totals.expected_cash', '100.50')
            ->assertJsonPath('data.totals.sales_count', 0)
            ->assertJsonPath('data.z_report', null)
            ->assertJsonPath('data.closes', true)
            ->json('data');
        $this->assertTrue(ActivityLog::query()->where('description', 'Opened the POS session S-00001 at the register "Front Counter" (R1)')->exists());

        $options = $this->getJson(self::SESSIONS.'/options')->json('data');
        $this->assertSame(['id' => $session['id'], 'number' => 'S-00001', 'register' => ['name' => 'Front Counter', 'code' => 'R1']], $options['mine']);
        $this->assertSame('This register has a session open.', collect($options['registers'])->firstWhere('code', 'R1')['refusal']);

        // One at a time per register, and per cashier; only its cashier closes it.
        $this->open('R2')->assertStatus(409)->assertJsonPath('message', 'Your session S-00001 at Front Counter is still open: close it first.');
        Sanctum::actingAs($this->ana);
        $this->open('R1')->assertUnprocessable()->assertJsonValidationErrors(['pos_register_id' => 'This register has a session open.']);
        $this->getJson(self::SESSIONS."/{$session['id']}")->assertOk()->assertJsonPath('data.closes', false);
        $this->postJson(self::SESSIONS."/{$session['id']}/close", ['counted_cash' => '100.50'])->assertStatus(409)->assertJsonPath('message', 'Only its cashier closes a session.');
        Sanctum::actingAs($this->user);

        $this->postJson(self::SESSIONS."/{$session['id']}/close", ['counted_cash' => '100.5', 'notes' => 'All square'])->assertOk()
            ->assertJsonPath('message', 'Session S-00001 closed.')
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.totals', null)
            ->assertJsonPath('data.closes', false)
            ->assertJsonPath('data.closed_by.name', $this->user->name)
            ->assertJsonPath('data.z_report.opening_float', '100.50')
            ->assertJsonPath('data.z_report.expected_cash', '100.50')
            ->assertJsonPath('data.z_report.counted_cash', '100.50')
            ->assertJsonPath('data.z_report.variance', '0.00')
            ->assertJsonPath('data.z_report.variance_reason', null)
            ->assertJsonPath('data.z_report.notes', 'All square')
            ->assertJsonPath('data.z_report.totals.sales_total', '0.00')
            ->assertJsonPath('data.z_report.totals.payments', []);
        $this->postJson(self::SESSIONS."/{$session['id']}/close", ['counted_cash' => '100.50'])->assertStatus(409)->assertJsonPath('message', 'Session S-00001 is closed already.');

        // The register is free again. A variance needs its reason.
        $next = $this->open('R1', '50')->assertCreated()->assertJsonPath('data.number', 'S-00002')->json('data.id');
        $this->postJson(self::SESSIONS."/{$next}/close", ['counted_cash' => '40'])->assertUnprocessable()
            ->assertJsonValidationErrors(['variance_reason' => 'Say why the cash counted is not the cash expected.']);
        $this->postJson(self::SESSIONS."/{$next}/close", ['counted_cash' => '40', 'variance_reason' => 'A note was short'])->assertOk()
            ->assertJsonPath('data.z_report.variance', '-10.00')
            ->assertJsonPath('data.z_report.variance_reason', 'A note was short');
        $this->assertTrue(ActivityLog::query()->where('description', 'Closed the POS session S-00002 with a variance of USD -10.00')->exists());

        // A Z report stays as it was made.
        $this->expectException(LogicException::class);
        PosSessionClosing::query()->firstOrFail()->update(['counted_cash' => '1.00']);
    }

    public function test_only_an_active_register_of_the_cashier_at_a_location_they_may_use_opens_one(): void
    {
        $this->open('R1', '-1')->assertUnprocessable()->assertJsonValidationErrors('opening_float');
        $this->open('R1', '1.005')->assertUnprocessable()->assertJsonValidationErrors('opening_float');
        $this->open('CTG1')->assertUnprocessable()->assertJsonValidationErrors(['pos_register_id' => "You are not one of this register's cashiers."]);

        $this->saveRegister('R1', ['status' => 'maintenance'])->assertOk();
        $this->open('R1')->assertUnprocessable()->assertJsonValidationErrors(['pos_register_id' => 'This register is under maintenance: it opens no sessions.']);
        $this->saveRegister('R1', ['status' => 'inactive'])->assertOk();
        $this->open('R1')->assertUnprocessable()->assertJsonValidationErrors(['pos_register_id' => 'This register is switched off: it opens no sessions.']);

        // Bob, moved to Dhaka, no longer sees Chattogram's register, nor opens a session there.
        $this->business->members()->where('user_id', $this->bob->id)->firstOrFail()->locations()->sync([$this->dhaka->id]);
        Sanctum::actingAs($this->bob);
        $this->assertSame(['R2', 'R1'], array_column($this->getJson(self::SESSIONS.'/options')->json('data.registers'), 'code'));
        $this->open('CTG1')->assertUnprocessable()->assertJsonValidationErrors(['pos_register_id' => 'Choose one of the registers you sell at.']);
    }

    public function test_a_register_with_sessions_is_kept_and_stays_as_it_is_while_one_is_open(): void
    {
        $session = $this->open('R1')->assertCreated()->json('data.id');
        $refused = 'Session S-00001 is open on this register: it is closed first.';

        $this->saveRegister('R1', ['status' => 'inactive'])->assertUnprocessable()->assertJsonValidationErrors(['status' => $refused]);
        $this->saveRegister('R1', ['location_id' => $this->chattogram->id, 'cashier_ids' => []])->assertUnprocessable()->assertJsonValidationErrors(['location_id' => $refused]);
        $this->saveRegister('R1', ['currency' => 'EUR'])->assertUnprocessable()->assertJsonValidationErrors(['currency' => $refused]);
        $this->saveRegister('R1', ['name' => 'Main Counter'])->assertOk()->assertJsonPath('data.has_sessions', true);
        $this->deleteJson(self::REGISTERS."/{$this->registers['R1']['id']}")->assertStatus(409)
            ->assertJsonPath('message', 'Sessions were held at this register, so it is kept for them. Switch it off instead.');

        $this->postJson(self::SESSIONS."/{$session}/close", ['counted_cash' => '100.50'])->assertOk();
        $this->saveRegister('R1', ['status' => 'inactive'])->assertOk();
        $this->deleteJson(self::REGISTERS."/{$this->registers['R1']['id']}")->assertStatus(409);
        $this->assertSame([false, true, false], array_column($this->getJson(self::REGISTERS)->json('data'), 'has_sessions'));
        $this->deleteJson(self::REGISTERS."/{$this->registers['R2']['id']}")->assertOk();
    }

    public function test_sessions_are_seen_where_their_register_may_be_used(): void
    {
        $front = $this->open('R1')->assertCreated()->json('data.id');
        Sanctum::actingAs($this->bob);
        $port = $this->open('CTG1', '20')->assertCreated()->json('data.id');
        $this->postJson(self::SESSIONS."/{$port}/close", ['counted_cash' => '20'])->assertOk();

        // Bob, at Chattogram only, sees its session alone.
        $this->assertSame(['S-00002'], array_column($this->getJson(self::SESSIONS)->assertOk()->json('data'), 'number'));
        $this->getJson(self::SESSIONS."/{$front}")->assertNotFound();

        Sanctum::actingAs($this->user);
        $rows = $this->getJson(self::SESSIONS)->assertOk()->assertJsonPath('pagination.total', 2)->json('data');
        $this->assertSame(['S-00002', 'S-00001'], array_column($rows, 'number'));
        $this->assertSame(['expected_cash' => '20.00', 'counted_cash' => '20.00', 'variance' => '0.00'], $rows[0]['closing']);
        $this->assertSame(['S-00001'], array_column($this->getJson(self::SESSIONS.'?status=open')->json('data'), 'number'));
        $this->assertSame(['S-00002'], array_column($this->getJson(self::SESSIONS.'?register_id='.$this->registers['CTG1']['id'])->json('data'), 'number'));

        // View alone sees them and opens none; without it, nothing.
        Sanctum::actingAs($this->member('Cara', 'Chowdhury', ['pos.sessions.view']));
        $this->getJson(self::SESSIONS)->assertOk()->assertJsonCount(2, 'data');
        $this->getJson(self::SESSIONS.'/options')->assertOk();
        $this->open('R2')->assertForbidden();
        Sanctum::actingAs($this->member('Dina', 'Das', ['pos.registers.view']));
        $this->getJson(self::SESSIONS)->assertForbidden();

        // POS off: its sessions are read, not opened.
        Sanctum::actingAs($this->user);
        $this->postJson('/api/v1/apps/pos/deactivate')->assertOk();
        $this->getJson(self::SESSIONS."/{$front}")->assertOk();
        $this->open('R2')->assertForbidden();
    }
}
