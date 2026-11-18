<?php

namespace Tests\Feature;

use App\Mail\AccountNoticeMail;
use App\Models\Admin\AdminAuditLog;
use App\Models\Admin\Role;
use App\Models\Shared\ActivityLog;
use App\Models\Shared\UserNotification;
use App\Models\User\SupportSession;
use App\Models\User\Ticket;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Services\Shared\SupportSessionService;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 15 Phase B4: support sessions (section 15.22), in place of Login as.
 * Opened for one of a business's open cases with a reason - read only, or
 * with edit access, its own permission and the customer's agreement - a
 * session sees that business only, changes what its access allows, never
 * sees payment details in full, ends by itself, and is logged as izSuite
 * support's in the business's Activity Log.
 */
class SupportSessionTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $owner;

    private Workspace $business;

    private User $admin;

    private Ticket $case;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo(Carbon::parse('2026-11-13 10:00:00', 'UTC'));
        Mail::fake();

        $this->owner    = User::factory()->create(['first_name' => 'Nadia', 'last_name' => 'Karim']);
        $this->business = $this->businessOf($this->owner);
        $this->business->update(['name' => 'Karim Stores']);
        app(SetUpNewWorkspace::class)->handle($this->business, $this->owner, 'api');

        $this->admin = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID, 'first_name' => 'Rafi', 'last_name' => 'Ahmed']);
        $this->case  = Ticket::create(['workspace_id' => $this->business->id, 'user_id' => $this->owner->id, 'subject' => 'Invoice PDF is blank', 'status' => Ticket::OPEN]);
    }

    private function open(bool $edit = false, array $form = [], ?User $admin = null)
    {
        return $this->actingAs($admin ?? $this->admin)->postJson(
            route($edit ? 'admin.businesses.support-session-edit' : 'admin.businesses.support-session', $this->business->id),
            ['ticket_id' => $this->case->id, 'reason' => 'Looking at the blank invoice PDF', 'hours' => 1, ...$form],
        );
    }

    /** The app's token for the newest session, entered through its link as its admin. */
    private function enter(): string
    {
        $link = app(SupportSessionService::class)->enter(SupportSession::latest('id')->value('id'), $this->admin);
        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);

        return $this->app_()->postJson('/api/v1/auth/support-session', ['code' => $query['code']])->assertOk()->json('token');
    }

    /** A request from the app, with nobody signed in to the admin panel. */
    private function app_(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }

    public function test_a_read_only_session_looks_at_one_business_and_changes_nothing(): void
    {
        $other = $this->owner->workspaces()->create(['name' => 'Second Business']);
        Sanctum::actingAs($this->owner);
        $account = $this->postJson('/api/v1/payment-accounts', [
            'type' => 'bank', 'name' => 'City Bank Current', 'currency' => 'USD', 'bank_name' => 'City Bank', 'holder_name' => 'Karim Stores',
            'account_number' => '0012345678', 'swift_code' => 'CIBLBDDH', 'bank_country' => 'bd',
        ], $this->pick($this->business))->assertCreated()->json('data.id');

        $this->open()->assertOk();
        $this->assertSame('izsuite.support', UserNotification::where('user_id', $this->owner->id)->sole()->trigger);
        Mail::assertQueued(AccountNoticeMail::class, fn (AccountNoticeMail $mail) => $mail->hasTo($this->owner->email) && $mail->title === 'izSuite support is viewing Karim Stores');
        $this->assertSame('Looking at the blank invoice PDF', AdminAuditLog::where('action', 'support_started')->sole()->reason);

        $token = $this->enter();
        $this->app_()->withToken($token)->getJson('/api/v1/auth/me')->assertOk()
            ->assertJsonPath('support_session.business_id', $this->business->id)
            ->assertJsonPath('support_session.can_edit', false)
            ->assertJsonPath('support_session.case.id', $this->case->id);

        $this->withToken($token)->getJson('/api/v1/contacts')->assertOk();
        $this->withToken($token)->postJson('/api/v1/contacts', ['contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin Traders'])
            ->assertForbidden()->assertJsonPath('message', 'This support session is read only: nothing can be changed.');
        $this->withToken($token)->getJson('/api/v1/profile')->assertForbidden()->assertJsonPath('message', 'This is not open to izSuite support.');
        $this->withToken($token)->getJson('/api/v1/notifications/unread')->assertForbidden();

        // This business only.
        $this->withToken($token)->getJson('/api/v1/contacts', $this->pick($other))->assertNotFound();
        $this->withToken($token)->getJson('/api/v1/workspaces')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->business->id);

        // Payment details masked; the area's first opening logged once, as izSuite support's.
        $this->withToken($token)->getJson("/api/v1/payment-accounts/{$account}")->assertOk()->assertJsonPath('data.account_number', '•••• 5678');
        $this->withToken($token)->getJson('/api/v1/payment-accounts')->assertOk();
        $viewed = ActivityLog::where('action', 'support_viewed')->sole();
        $this->assertSame([null, $this->business->id, 'Rafi Ahmed'], [$viewed->user_id, $viewed->workspace_id, $viewed->properties['support']['admin']]);
        $this->assertSame(['Payment Accounts'], SupportSession::latest('id')->first()->areas);
        $this->withToken($token)->getJson('/api/v1/activity-log')->assertOk()->assertJsonFragment(['user' => 'izSuite support (Rafi Ahmed)']);
    }

    public function test_edit_access_needs_its_own_permission_and_the_customers_agreement(): void
    {
        $support = User::factory()->create(['role_id' => Role::where('slug', 'support-administrator')->value('id')]);
        $this->open(edit: true, admin: $support)->assertForbidden();

        $this->open(edit: true)->assertUnprocessable()->assertJsonValidationErrors(['customer_authorization' => 'Say how the customer agreed to changes.']);
        $this->open(edit: true, form: ['customer_authorization' => 'The owner asked on case #1'])->assertOk();
        $this->assertSame('The owner asked on case #1', SupportSession::latest('id')->value('customer_authorization'));

        $token = $this->enter();
        $this->app_()->withToken($token)->postJson('/api/v1/contacts', ['contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin Traders'])->assertCreated();
        $made = ActivityLog::where('workspace_id', $this->business->id)->where('description', 'like', '%Uddin Traders%')->latest('id')->firstOrFail();
        $this->assertNull($made->user_id);
        $this->assertSame('Rafi Ahmed', $made->properties['support']['admin']);

        $this->withToken($token)->putJson("/api/v1/workspaces/{$this->business->id}", ['name' => 'Renamed'])
            ->assertForbidden()->assertJsonPath('message', 'izSuite support cannot change this, even with edit access.');
    }

    public function test_a_session_ends_by_itself_from_the_admin_panel_or_in_the_app(): void
    {
        $this->open()->assertOk();
        $token = $this->enter();

        $this->travel(61)->minutes();
        $this->app_()->withToken($token)->getJson('/api/v1/contacts')->assertUnauthorized();
        $this->artisan('support-sessions:close-expired')->assertSuccessful();
        $this->assertSame(SupportSession::ENDED_EXPIRED, SupportSession::latest('id')->value('end_reason'));

        $this->open()->assertOk();
        $token = $this->enter();
        $this->actingAs($this->admin)->postJson(route('admin.support-sessions.end', SupportSession::latest('id')->value('id')))->assertOk();
        $this->app_()->withToken($token)->getJson('/api/v1/contacts')->assertUnauthorized();

        $this->open()->assertOk();
        $token = $this->enter();
        $this->app_()->withToken($token)->postJson('/api/v1/auth/logout')->assertOk()->assertJsonPath('message', 'The support session has ended.');
        $this->assertSame(SupportSession::ENDED_IN_APP, SupportSession::latest('id')->value('end_reason'));
        $this->assertFalse(ActivityLog::where('description', 'like', 'POST api/v1/auth/logout%')->exists());

        $this->assertSame(3, ActivityLog::where('workspace_id', $this->business->id)->where('action', 'support_ended')->count());
    }

    public function test_a_session_is_for_an_open_case_of_the_business_and_its_own_admin_enters_it(): void
    {
        $elsewhere = Ticket::create(['user_id' => $this->owner->id, 'subject' => 'Not about this business', 'status' => Ticket::OPEN]);
        $this->open(form: ['ticket_id' => $elsewhere->id])->assertUnprocessable()->assertJsonValidationErrors(['ticket_id' => 'Choose one of this business\'s open cases.']);

        $this->open()->assertOk();
        $another = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]);
        $this->actingAs($another)->get(route('admin.support-sessions.open', SupportSession::latest('id')->value('id')))->assertForbidden();

        $this->actingAs($this->admin)->get(route('admin.businesses.show', $this->business->id))->assertOk()->assertSee('Support Sessions')->assertSee('Enter in the App');
        $this->get(route('admin.support-sessions.index', ['state' => 'live']))->assertOk()->assertSee('Karim Stores')->assertSee('Looking at the blank invoice PDF');
        $this->get(route('admin.support-sessions.index', ['state' => 'ended']))->assertOk()->assertDontSee('Looking at the blank invoice PDF');
    }
}
