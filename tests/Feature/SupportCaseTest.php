<?php

namespace Tests\Feature;

use App\Mail\AccountNoticeMail;
use App\Models\Admin\Role;
use App\Models\Shared\UserNotification;
use App\Models\User\Ticket;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Repositories\User\TicketRepository;
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
 * Doc 15 Phase B3: support cases (section 15.22). Anyone in a business opens
 * one from the app and writes on it, whatever the business's status; the
 * owner sees every case about it. Staff answer in Admin > Tickets, and their
 * words reach the app as text; the app's words reach the admin as text too.
 */
class SupportCaseTest extends TestCase
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

        $this->owner    = User::factory()->create(['first_name' => 'Nadia', 'last_name' => 'Karim']);
        $this->business = $this->businessOf($this->owner);
        $this->business->update(['name' => 'Karim Stores']);
        app(SetUpNewWorkspace::class)->addRoles($this->business, $this->owner, 'api');

        $this->admin = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID, 'first_name' => 'Rafi']);
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $this->business->members()->create([
            'user_id' => $user->id, 'role_id' => $this->business->roles()->where('name', 'Sales User')->value('id'),
            'name'    => $user->name, 'email' => $user->email, 'status' => WorkspaceMember::ACTIVE, 'location_access' => WorkspaceMember::ALL_LOCATIONS,
        ]);

        return $user;
    }

    /** The header that picks the business, for $user signed in to the app. */
    private function as(User $user): array
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user);

        return $this->pick($this->business);
    }

    private function open(User $user, string $message = 'It shows nothing.'): int
    {
        return $this->postJson('/api/v1/support-cases', ['subject' => 'Invoice PDF is blank', 'message' => $message], $this->as($user))
            ->assertCreated()->json('data.id');
    }

    public function test_anyone_in_the_business_opens_a_case_and_the_owner_sees_every_one(): void
    {
        $member = $this->member();

        $this->postJson('/api/v1/support-cases', [], $this->as($member))->assertUnprocessable()->assertJsonValidationErrors(['subject', 'message']);
        $id = $this->open($member, "It shows nothing.\nSince Monday.");
        $this->getJson("/api/v1/support-cases/{$id}", $this->as($member))->assertOk()
            ->assertJsonPath('data.messages.0.body', "It shows nothing.\nSince Monday.")->assertJsonPath('data.messages.0.mine', true);

        $other = $this->member();
        $this->getJson('/api/v1/support-cases', $this->as($other))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/support-cases/{$id}", $this->as($other))->assertNotFound();

        $this->getJson('/api/v1/support-cases', $this->as($this->owner))->assertOk()->assertJsonPath('data.0.opened_by', $member->name)->assertJsonPath('data.0.status', 'open');

        $this->actingAs($this->admin)->get(route('admin.tickets.index'))->assertOk()->assertSee('Karim Stores')->assertSee('Invoice PDF is blank');
        $this->get(route('admin.tickets.index', ['workspace' => $this->business->id]))->assertSee('Cases about Karim Stores.');
        $this->get(route('admin.businesses.show', $this->business->id))->assertOk()->assertSee('Support Cases')->assertSee('Invoice PDF is blank');
    }

    public function test_staff_replies_reach_whoever_opened_it_as_text(): void
    {
        $id = $this->open($this->owner);

        $this->actingAs($this->admin)->post(route('admin.tickets.reply', $id), [
            'message' => '<p>Hello <strong>Nadia</strong>,</p><p>See <a href="https://izsuite.io/help">the guide</a>.</p><script>alert(1)</script>',
        ])->assertSessionHasNoErrors();

        $this->getJson("/api/v1/support-cases/{$id}", $this->as($this->owner))->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.messages.1.from_staff', true)
            ->assertJsonPath('data.messages.1.author', 'Rafi')
            ->assertJsonPath('data.messages.1.body', "Hello Nadia,\n\nSee the guide (https://izsuite.io/help).");

        $this->assertSame('izsuite.case', UserNotification::where('user_id', $this->owner->id)->sole()->trigger);
        Mail::assertQueued(AccountNoticeMail::class, fn (AccountNoticeMail $mail) => $mail->hasTo($this->owner->email)
            && $mail->title === "izSuite support replied to case #{$id}" && str_ends_with((string) $mail->actionUrl, "/support/{$id}"));
    }

    public function test_the_apps_words_are_text_in_the_admin(): void
    {
        $id = $this->open($this->owner, '<script>alert("x")</script><b>bold</b>');

        $this->actingAs($this->admin)->get(route('admin.tickets.show', $id))->assertOk()
            ->assertDontSee('<script>alert("x")</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_a_resolved_case_opens_again_and_a_closed_one_takes_no_more(): void
    {
        $id     = $this->open($this->owner);
        $ticket = Ticket::findOrFail($id);

        $this->actingAs($this->admin)->put(route('admin.tickets.update', $id), ['status' => 'resolved'])->assertSessionHasNoErrors();
        Mail::assertQueued(AccountNoticeMail::class, fn (AccountNoticeMail $mail) => $mail->title === "Case #{$id} is resolved");

        $this->postJson("/api/v1/support-cases/{$id}/replies", ['message' => 'It is back again.'], $this->as($this->owner))->assertOk()->assertJsonPath('data.status', 'open');

        $this->actingAs($this->admin)->put(route('admin.tickets.update', $id), ['status' => 'closed']);
        $this->postJson("/api/v1/support-cases/{$id}/replies", ['message' => 'Still there?'], $this->as($this->owner))
            ->assertStatus(422)->assertJsonPath('message', 'This case is closed. Open a new one if you still need help.');
        // The first message and the one that opened it again; the closed case took no more.
        $this->assertSame(2, $ticket->messages()->count());
    }

    public function test_support_stays_open_while_the_business_is_shut_or_restricted(): void
    {
        $statuses = app(BusinessStatusService::class);

        $statuses->change($this->business->fresh(), BusinessStatus::SUSPENDED, ['reason' => 'Unusual payments are being checked'], $this->admin);
        $this->getJson('/api/v1/contacts', $this->as($this->owner))->assertForbidden();
        $id = $this->open($this->owner, 'Why is my business suspended?');

        $statuses->change($this->business->fresh(), BusinessStatus::RESTRICTED, ['reason' => 'Still checking'], $this->admin);
        $this->postJson("/api/v1/support-cases/{$id}/replies", ['message' => 'Here are the invoices.'], $this->as($this->owner))->assertOk();
    }

    public function test_staff_open_a_case_for_someone_in_the_business(): void
    {
        $this->actingAs($this->admin)->get(route('admin.tickets.create', ['workspace' => $this->business->id]))->assertOk()->assertSee('Karim Stores');

        $stranger = User::factory()->create();
        $form     = ['workspace_id' => $this->business->id, 'subject' => 'Your import failed', 'priority' => 'medium', 'message' => '<p>We saw the import fail.</p>'];
        $this->post(route('admin.tickets.store'), [...$form, 'user_id' => $stranger->id])->assertSessionHasErrors(['user_id' => 'Choose someone in this business.']);
        $this->post(route('admin.tickets.store'), [...$form, 'user_id' => $this->owner->id])->assertSessionHasNoErrors();

        $ticket = Ticket::latest('id')->firstOrFail();
        $this->assertSame([$this->business->id, $this->owner->id], [$ticket->workspace_id, $ticket->user_id]);
        $this->assertTrue($ticket->messages()->sole()->by_staff);
        $this->getJson("/api/v1/support-cases/{$ticket->id}", $this->as($this->owner))->assertJsonPath('data.messages.0.body', 'We saw the import fail.');

        // Staff are staff: no website account is offered for assigning.
        $staff = app(TicketRepository::class)->getStaff()->pluck('id');
        $this->assertTrue($staff->contains($this->admin->id));
        $this->assertFalse($staff->contains($this->owner->id));
    }
}
