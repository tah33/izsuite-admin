<?php

namespace Tests\Feature;

use App\Mail\BusinessNoticeMail;
use App\Models\Admin\AdminAuditLog;
use App\Models\Admin\Role;
use App\Models\Shared\UserNotification;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Services\User\Workspaces\BusinessStatusService;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use App\Support\BusinessStatus;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 15 Phase B1: a business's status (section 15.6), set in Admin >
 * Businesses (15.5). Suspended or closed, nothing of it opens but its
 * navigation, which says why to its owner alone; restricted or under
 * review, it is read but not changed; a scheduled closure closes on its day.
 */
class BusinessStatusTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $owner;

    private Workspace $business;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo(Carbon::parse('2026-11-13 10:00:00', 'UTC'));
        Mail::fake();

        $this->owner    = $this->signIn(User::factory()->create(['first_name' => 'Nadia', 'last_name' => 'Karim']));
        $this->business = $this->businessOf($this->owner);
        $this->business->update(['name' => 'Karim Stores']);
        app(SetUpNewWorkspace::class)->addRoles($this->business, $this->owner, 'api');

        $this->admin = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID, 'first_name' => 'Rafi']);
    }

    private function set(string $status, array $data = []): void
    {
        app(BusinessStatusService::class)->change($this->business->fresh(), $status, ['reason' => 'Unusual payments are being checked', ...$data], $this->admin);
    }

    /** A member with the role named $role, signed in to the API: the header that picks the business. */
    private function member(string $role = 'Sales User'): array
    {
        $user = User::factory()->create();
        $this->business->members()->create([
            'user_id' => $user->id, 'role_id' => $this->business->roles()->where('name', $role)->value('id'),
            'name'    => $user->name, 'email' => $user->email, 'status' => WorkspaceMember::ACTIVE, 'location_access' => WorkspaceMember::ALL_LOCATIONS,
        ]);
        Sanctum::actingAs($user);

        return $this->pick($this->business);
    }

    public function test_a_suspended_business_opens_nothing_but_its_navigation_which_tells_the_owner_why(): void
    {
        $this->getJson('/api/v1/contacts')->assertOk();

        $this->set(BusinessStatus::SUSPENDED, ['reactivation' => 'Confirm the payments with support']);

        $this->getJson('/api/v1/contacts')->assertForbidden()->assertJsonPath('business_status', 'suspended');
        $this->postJson('/api/v1/contacts', ['name' => 'Uddin Traders'])->assertForbidden();
        // The bell still works: it is where izSuite's notice of the suspension waits.
        $this->getJson('/api/v1/notifications/unread')->assertOk();
        $this->postJson('/api/v1/notifications/read')->assertOk();
        $this->getJson('/api/v1/navigation')->assertOk()
            ->assertJsonPath('data.workspace.status.key', 'suspended')
            ->assertJsonPath('data.workspace.status.shut', true)
            ->assertJsonPath('data.workspace.status.reason', 'Unusual payments are being checked')
            ->assertJsonPath('data.workspace.status.reactivation', 'Confirm the payments with support');

        // Anyone else in it hears that it is shut, not why.
        $header = $this->member();
        $this->getJson('/api/v1/navigation', $header)->assertOk()
            ->assertJsonPath('data.workspace.status.is_owner', false)
            ->assertJsonPath('data.workspace.status.reason', null);

        // Reactivated, everyone is back in.
        $this->set(BusinessStatus::ACTIVE, ['reason' => 'Payments confirmed']);
        $this->getJson('/api/v1/contacts', $header)->assertOk();
        $this->getJson('/api/v1/navigation', $header)->assertJsonPath('data.workspace.status', null);
    }

    public function test_a_restricted_business_is_read_but_not_changed(): void
    {
        $this->set(BusinessStatus::RESTRICTED);

        $this->getJson('/api/v1/contacts')->assertOk();
        $this->postJson('/api/v1/contacts', ['contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin Traders'])
            ->assertForbidden()->assertJsonPath('business_status', 'restricted');
        $this->postJson('/api/v1/notifications/read')->assertOk();
        $this->getJson('/api/v1/navigation')->assertJsonPath('data.workspace.status.read_only', true)->assertJsonPath('data.workspace.status.shut', false);
    }

    public function test_a_scheduled_closure_works_until_its_day_then_closes(): void
    {
        $this->set(BusinessStatus::CLOSURE_SCHEDULED, ['closes_on' => '2026-11-16', 'notify' => true]);

        $this->getJson('/api/v1/contacts')->assertOk();
        $this->getJson('/api/v1/navigation')->assertJsonPath('data.workspace.status.closes_on', '2026-11-16');

        // Not yet.
        $this->artisan('businesses:close-due')->assertSuccessful();
        $this->assertSame(BusinessStatus::CLOSURE_SCHEDULED, $this->business->fresh()->status);

        $this->travelTo(Carbon::parse('2026-11-16 01:00:00', 'UTC'));
        $this->artisan('businesses:close-due')->assertSuccessful();

        $this->assertSame(BusinessStatus::CLOSED, $this->business->fresh()->status);
        $this->getJson('/api/v1/contacts')->assertForbidden()->assertJsonPath('message', 'This business is closed. Its records are kept, but nobody can open it.');
        $this->assertSame([BusinessStatus::CLOSURE_SCHEDULED, BusinessStatus::CLOSED], $this->business->statusChanges()->orderBy('id')->pluck('status')->all());
        $this->assertNull($this->business->statusChanges()->latest('id')->first()->user_id);
        // Closed for the reason it was scheduled, which its owner still sees.
        $this->getJson('/api/v1/navigation')->assertJsonPath('data.workspace.status.reason', 'Unusual payments are being checked');
        Mail::assertQueued(BusinessNoticeMail::class, 2);
        $this->assertSame(['Karim Stores closes on 16 Nov 2026', 'Karim Stores is closed'], UserNotification::where('user_id', $this->owner->id)->orderBy('id')->pluck('title')->all());
    }

    public function test_the_owner_is_told_when_asked_and_every_change_is_kept(): void
    {
        $this->set(BusinessStatus::UNDER_REVIEW, ['notes' => 'Flagged by the payments team']);
        $this->assertSame(0, UserNotification::count());
        Mail::assertNothingQueued();

        $this->set(BusinessStatus::SUSPENDED, ['notify' => true]);

        $this->assertSame('izsuite.status', UserNotification::where('user_id', $this->owner->id)->sole()->trigger);
        Mail::assertQueued(BusinessNoticeMail::class, fn (BusinessNoticeMail $mail) => $mail->hasTo($this->owner->email) && $mail->title === 'Karim Stores is suspended');

        $changes = $this->business->statusChanges()->orderBy('id')->get();
        $this->assertSame([[BusinessStatus::UNDER_REVIEW, false, 'Flagged by the payments team'], [BusinessStatus::SUSPENDED, true, null]],
            $changes->map(fn ($change) => [$change->status, $change->owner_notified, $change->notes])->all());
        $this->assertSame(['Set business "Karim Stores" to Under Review', 'Set business "Karim Stores" to Suspended'], AdminAuditLog::orderBy('id')->pluck('description')->all());
        $this->assertSame('Unusual payments are being checked', AdminAuditLog::latest('id')->first()->reason);
    }

    /* ---------------------------------------------------------- Admin > Businesses */

    public function test_admin_businesses_lists_filters_and_shows_a_business(): void
    {
        $other = User::factory()->create(['first_name' => 'Salma', 'last_name' => 'Uddin', 'email' => 'salma@uddin.test']);
        $quiet = $this->businessOf($other);
        $quiet->update(['name' => 'Uddin Traders']);
        $this->set(BusinessStatus::SUSPENDED);

        $this->actingAs($this->admin);

        $this->get(route('admin.businesses.index'))->assertOk()->assertSee('Karim Stores')->assertSee('Uddin Traders');
        $this->get(route('admin.businesses.index', ['status' => 'suspended']))->assertOk()->assertSee('Karim Stores')->assertDontSee('Uddin Traders');
        $this->get(route('admin.businesses.index', ['search' => 'salma@uddin']))->assertOk()->assertSee('Uddin Traders')->assertDontSee('Karim Stores');
        $this->get(route('admin.businesses.index', ['search' => $quiet->businessId()]))->assertOk()->assertSee('Uddin Traders')->assertDontSee('Karim Stores');
        $this->get(route('admin.businesses.index', ['limits' => 'reached']))->assertOk()->assertSee('No businesses match.');

        $this->get(route('admin.businesses.show', $this->business->id))->assertOk()
            ->assertSee('Karim Stores')->assertSee($this->owner->email)->assertSee('Unusual payments are being checked')->assertSee('Set business');
    }

    public function test_only_roles_allowed_change_a_status_or_send_a_message(): void
    {
        $support = User::factory()->create(['role_id' => Role::where('slug', 'support-administrator')->value('id')]);
        $form    = ['status' => 'restricted', 'reason' => 'Checking the payments', 'notify' => '0'];

        $this->actingAs($support)->postJson(route('admin.businesses.status', $this->business->id), $form)->assertForbidden();
        $this->postJson(route('admin.businesses.notify', $this->business->id), ['audience' => 'everyone', 'title' => 'Planned maintenance', 'message' => 'Saturday from 02:00 UTC.'])->assertOk();
        Mail::assertQueued(BusinessNoticeMail::class, fn (BusinessNoticeMail $mail) => $mail->hasTo($this->owner->email));

        $compliance = User::factory()->create(['role_id' => Role::where('slug', 'compliance-and-security-administrator')->value('id')]);
        $this->actingAs($compliance)->postJson(route('admin.businesses.status', $this->business->id), ['status' => 'closure_scheduled', 'reason' => 'Asked to close'])
            ->assertUnprocessable()->assertJsonValidationErrors(['closes_on' => 'Choose the day the business closes.']);
        $this->postJson(route('admin.businesses.status', $this->business->id), $form)->assertOk();
        $this->assertSame(BusinessStatus::RESTRICTED, $this->business->fresh()->status);
    }
}
