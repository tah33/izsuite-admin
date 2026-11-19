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
 * Doc 18 Phase B2a: the supervisor's side of POS sessions (sections 18.6 and
 * 18.27) - a variance beyond Settings → Point of Sale's limit waiting for
 * approval, a supervisor closing someone else's session with a reason, and
 * a counted session reopened with a reason, its Z report marked and kept.
 */
class PosSessionSupervisionTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const SESSIONS = '/api/v1/pos/sessions';

    private const REGISTERS = '/api/v1/pos/registers';

    private User $user;

    private User $ana;

    private User $sam;

    private User $rita;

    private Workspace $business;

    private Location $dhaka;

    /** The registers by code: R1 and R2 at Dhaka, Ana a cashier of both. */
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
        $this->business->forceFill(['base_currency' => 'USD'])->save();
        $this->dhaka = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka', 'code' => 'DHK']);
        $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Shop Till', 'currency' => 'USD']);

        $this->ana  = $this->member('Ana', 'Ahmed', ['pos.sessions.view', 'pos.sessions.open']);
        $this->sam  = $this->member('Sam', 'Sarkar', ['pos.sessions.view', 'pos.sessions.approve']);
        $this->rita = $this->member('Rita', 'Roy', ['pos.sessions.view', 'pos.sessions.reopen']);

        $this->postJson('/api/v1/apps/pos/activate')->assertOk();
        $this->cash = (int) $this->getJson(self::REGISTERS.'/options')->assertOk()->json('data.payment_methods.0.id');

        foreach (['R1' => 'Front Counter', 'R2' => 'Back Counter'] as $code => $name) {
            $this->registers[$code] = $this->postJson(self::REGISTERS, [
                'name' => $name, 'code' => $code, 'location_id' => $this->dhaka->id, 'currency' => 'USD', 'payment_method_ids' => [$this->cash], 'cashier_ids' => [$this->user->id, $this->ana->id],
            ])->assertCreated()->json('data');
        }
    }

    private function member(string $first, string $last, array $permissions): User
    {
        $user = User::factory()->create(['first_name' => $first, 'last_name' => $last]);
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    /** As $user: a session opened on $code with $float. Its id. */
    private function opened(User $user, string $code, string $float = '100'): int
    {
        Sanctum::actingAs($user);

        return $this->postJson(self::SESSIONS, ['pos_register_id' => $this->registers[$code]['id'], 'opening_float' => $float])->assertCreated()->json('data.id');
    }

    private function close(int $id, string $counted, array $fields = []): TestResponse
    {
        return $this->postJson(self::SESSIONS."/{$id}/close", ['counted_cash' => $counted, 'variance_reason' => 'Miscounted change', ...$fields]);
    }

    private function switchRegister(string $code, string $status): void
    {
        Sanctum::actingAs($this->user);
        $register = $this->registers[$code];
        $this->putJson(self::REGISTERS."/{$register['id']}", [
            'name' => $register['name'], 'code' => $code, 'location_id' => $this->dhaka->id, 'currency' => 'USD',
            'payment_method_ids' => [$this->cash], 'cashier_ids' => [$this->user->id, $this->ana->id], 'status' => $status,
        ])->assertOk();
    }

    public function test_a_variance_beyond_the_limit_waits_for_its_approval(): void
    {
        $this->putJson('/api/v1/business-settings/pos/cash-count', ['variance_limit' => '5', 'counts_denominations' => false])->assertOk()->assertJsonPath('data.variance_limit', '5.00');

        $first = $this->opened($this->ana, 'R1');
        $this->close($first, '90')->assertOk()
            ->assertJsonPath('message', 'Session S-00001 closed. Its variance waits for approval.')
            ->assertJsonPath('data.status', 'closing')
            ->assertJsonPath('data.z_report.needs_approval', true)
            ->assertJsonPath('data.z_report.approved_by', null)
            ->assertJsonPath('data.approves', false);
        $this->assertTrue(ActivityLog::query()->where('description', 'Closed the POS session S-00001 with a variance of USD -10.00, waiting for its approval')->exists());

        // Counted, it holds neither the register nor its cashier; Ana cannot approve it.
        $this->assertNull(collect($this->getJson(self::SESSIONS.'/options')->json('data.registers'))->firstWhere('code', 'R1')['refusal']);
        $this->postJson(self::SESSIONS."/{$first}/approve")->assertForbidden();
        $within = $this->opened($this->ana, 'R1');
        $this->close($within, '97')->assertOk()->assertJsonPath('data.status', 'closed')->assertJsonPath('data.z_report.needs_approval', false);

        Sanctum::actingAs($this->sam);
        $this->assertSame(['S-00001'], array_column($this->getJson(self::SESSIONS.'?status=closing')->json('data'), 'number'));
        $this->getJson(self::SESSIONS."/{$first}")->assertOk()->assertJsonPath('data.approves', true)->assertJsonPath('data.reopens', false);
        $this->postJson(self::SESSIONS."/{$first}/approve")->assertOk()
            ->assertJsonPath('message', 'Variance approved: session S-00001 is closed.')
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.z_report.approved_by.name', 'Sam Sarkar')
            ->assertJsonPath('data.approves', false);
        $this->postJson(self::SESSIONS."/{$first}/approve")->assertStatus(409)->assertJsonPath('message', 'Session S-00001 has no variance waiting for approval.');
        $this->assertTrue(ActivityLog::query()->where('description', 'Approved the variance of USD -10.00 of the POS session S-00001')->exists());

        // One who approves variances closes beyond the limit at once.
        $own = $this->opened($this->user, 'R2');
        $this->close($own, '80')->assertOk()->assertJsonPath('data.status', 'closed')->assertJsonPath('data.z_report.approved_by.name', $this->user->name);

        // No limit: none waits.
        $this->putJson('/api/v1/business-settings/pos/cash-count', ['variance_limit' => null, 'counts_denominations' => false])->assertOk()->assertJsonPath('data.variance_limit', null);
        $this->close($this->opened($this->ana, 'R1'), '1')->assertOk()->assertJsonPath('data.status', 'closed');
    }

    public function test_a_supervisor_closes_someone_elses_session_saying_why(): void
    {
        $session = $this->opened($this->ana, 'R1');

        // Another cashier may not.
        Sanctum::actingAs($this->member('Bob', 'Biswas', ['pos.sessions.view', 'pos.sessions.open']));
        $this->getJson(self::SESSIONS."/{$session}")->assertOk()->assertJsonPath('data.closes', false);
        $this->close($session, '100')->assertStatus(409)->assertJsonPath('message', 'Only its cashier, or someone who approves variances, closes a session.');

        Sanctum::actingAs($this->sam);
        $this->getJson(self::SESSIONS."/{$session}")->assertOk()->assertJsonPath('data.closes', true)->assertJsonPath('data.own', false);
        $this->close($session, '100')->assertUnprocessable()->assertJsonValidationErrors(['closer_reason' => "Say why you close Ana Ahmed's session."]);
        $this->close($session, '100', ['closer_reason' => 'Ana went home ill'])->assertOk()
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.closed_by.name', 'Sam Sarkar')
            ->assertJsonPath('data.z_report.closer_reason', 'Ana went home ill')
            ->assertJsonPath('data.z_report.variance_reason', null);
        $this->assertTrue(ActivityLog::query()->where('description', 'Closed the POS session S-00001 of Ana Ahmed: Ana went home ill')->exists());
    }

    public function test_a_counted_session_is_reopened_with_a_reason_its_z_report_kept(): void
    {
        $first = $this->opened($this->ana, 'R1');
        $this->close($first, '100')->assertOk();

        Sanctum::actingAs($this->rita);
        $this->getJson(self::SESSIONS."/{$first}")->assertOk()->assertJsonPath('data.reopens', true)->assertJsonPath('data.closes', false);
        $this->postJson(self::SESSIONS."/{$first}/reopen", [])->assertUnprocessable()->assertJsonValidationErrors(['reason' => 'Say why the session opens again.']);

        // Only while its register is active and free, and its cashier too.
        $second = $this->opened($this->ana, 'R1');
        Sanctum::actingAs($this->rita);
        $this->postJson(self::SESSIONS."/{$first}/reopen", ['reason' => 'Recount'])->assertStatus(409)->assertJsonPath('message', 'Session S-00002 is open, at Front Counter: it is closed first.');
        Sanctum::actingAs($this->ana);
        $this->close($second, '100')->assertOk();
        $third = $this->opened($this->ana, 'R2');
        Sanctum::actingAs($this->rita);
        $this->postJson(self::SESSIONS."/{$first}/reopen", ['reason' => 'Recount'])->assertStatus(409)->assertJsonPath('message', 'Session S-00003 is open, at Back Counter: it is closed first.');
        Sanctum::actingAs($this->ana);
        $this->close($third, '100')->assertOk();
        $this->switchRegister('R1', 'maintenance');
        Sanctum::actingAs($this->rita);
        $this->postJson(self::SESSIONS."/{$first}/reopen", ['reason' => 'Recount'])->assertStatus(409)->assertJsonPath('message', 'The register Front Counter is not active: switch it on to reopen its session.');
        $this->switchRegister('R1', 'active');

        Sanctum::actingAs($this->rita);
        $this->postJson(self::SESSIONS."/{$first}/reopen", ['reason' => 'Counted before the last sale'])->assertOk()
            ->assertJsonPath('message', 'Session S-00001 reopened.')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.closed_at', null)
            ->assertJsonPath('data.z_report', null)
            ->assertJsonPath('data.totals.expected_cash', '100.00')
            ->assertJsonPath('data.earlier.0.reopen_reason', 'Counted before the last sale')
            ->assertJsonPath('data.earlier.0.reopened_by.name', 'Rita Roy')
            ->assertJsonPath('data.earlier.0.counted_cash', '100.00');
        $this->postJson(self::SESSIONS."/{$first}/reopen", ['reason' => 'Again'])->assertStatus(409)->assertJsonPath('message', 'Session S-00001 is open already.');
        $this->assertTrue(ActivityLog::query()->where('description', 'Reopened the POS session S-00001: Counted before the last sale')->exists());
        $this->assertNull(collect($this->getJson(self::SESSIONS)->json('data'))->firstWhere('number', 'S-00001')['closing']);

        // Its cashier closes it again: a new Z report, the earlier one kept as it was.
        Sanctum::actingAs($this->ana);
        $this->close($first, '95')->assertOk()
            ->assertJsonPath('data.z_report.counted_cash', '95.00')
            ->assertJsonPath('data.earlier.0.counted_cash', '100.00');
        $this->postJson(self::SESSIONS."/{$first}/reopen", ['reason' => 'Mine'])->assertForbidden();

        $this->expectException(LogicException::class);
        PosSessionClosing::query()->whereNotNull('reopened_at')->firstOrFail()->forceFill(['reopen_reason' => 'Changed'])->save();
    }
}
