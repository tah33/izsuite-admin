<?php

namespace Tests\Feature;

use App\Mail\WorkspaceInvitation;
use App\Models\Billing\Package;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Inviting users (Doc 12 sections 4 and 6) - Doc 12 Phase B: an invitation
 * goes to an e-mail once per business, as a secure link that expires; it is
 * accepted with that e-mail's account, or by making one; until then the
 * invitee has no access. Sent again, only the newest link works; cancelled,
 * none does.
 */
class InvitationsTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $owner;

    private Workspace $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Mail::fake();

        $this->offerSalesLite();
        $this->owner    = $this->signIn(User::factory()->create(['first_name' => 'Nadia', 'last_name' => 'Karim']));
        $this->business = $this->businessOf($this->owner);
        $this->business->update(['name' => 'Karim Traders']);
        $this->business->package()->associate(Package::where('key', 'standard')->sole())->save();

        app(SetUpNewWorkspace::class)->handle($this->business, $this->owner, 'api');
    }

    private function role(string $template): int
    {
        return $this->business->roles()->where('template', $template)->value('id');
    }

    /** Invites $email as a Sales User, and the secret its e-mail carried. */
    private function invite(string $email, array $fields = []): string
    {
        $this->postJson('/api/v1/members', [
            'name' => 'Rahim Uddin', 'email' => $email, 'role_id' => $this->role('sales_user'), 'location_access' => 'all', ...$fields,
        ])->assertCreated();

        return $this->lastSecret($email);
    }

    private function lastSecret(string $email): string
    {
        $mail = Mail::sent(WorkspaceInvitation::class, fn (WorkspaceInvitation $mail) => $mail->hasTo($email))->last();

        return substr($mail->link, strrpos($mail->link, '/') + 1);
    }

    public function test_an_invitation_goes_to_an_email_once_with_a_secure_link(): void
    {
        $response = $this->postJson('/api/v1/members', [
            'name' => 'Rahim Uddin', 'email' => 'Rahim@Example.com', 'role_id' => $this->role('sales_user'), 'location_access' => 'all',
        ])->assertCreated();

        $response->assertJsonPath('message', 'Invitation sent to rahim@example.com.')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.invitation', 'live')
            ->assertJsonPath('data.role', 'Sales User');

        $secret = $this->lastSecret('rahim@example.com');
        $member = WorkspaceMember::where('email', 'rahim@example.com')->sole();

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{48}$/', $secret);
        $this->assertSame(hash('sha256', $secret), $member->invitation_token);
        $this->assertTrue($member->invitation_expires_at->between(now()->addDays(7)->subMinute(), now()->addDays(7)->addMinute()));

        Mail::assertSent(WorkspaceInvitation::class, fn (WorkspaceInvitation $mail) => $mail->inviterName === 'Nadia Karim' && $mail->businessName === 'Karim Traders' && $mail->roleName === 'Sales User');

        $this->postJson('/api/v1/members', ['name' => 'Again', 'email' => 'rahim@example.com', 'role_id' => $this->role('sales_user'), 'location_access' => 'all'])
            ->assertJsonValidationErrors(['email' => 'This e-mail is invited already. Send the invitation again from the list instead.']);
        $this->postJson('/api/v1/members', ['name' => 'Me', 'email' => $this->owner->email, 'role_id' => $this->role('sales_user'), 'location_access' => 'all'])
            ->assertJsonValidationErrors(['email' => 'This person is a user of the business already.']);
        $this->postJson('/api/v1/members', ['name' => 'Boss', 'email' => 'boss@example.com', 'role_id' => $this->role('owner'), 'location_access' => 'all'])
            ->assertJsonValidationErrors(['role_id']);
        $this->postJson('/api/v1/members', ['name' => 'Branch', 'email' => 'branch@example.com', 'role_id' => $this->role('sales_user'), 'location_access' => 'selected'])
            ->assertJsonValidationErrors(['location_ids' => 'Choose the locations this user may use.']);
    }

    public function test_the_link_is_accepted_with_the_account_of_its_email_only(): void
    {
        $rahim  = User::factory()->create(['email' => 'rahim@example.com']);
        $secret = $this->invite('rahim@example.com');

        // Shown to whoever holds the link, signed in or not.
        $this->getJson("/api/v1/invitations/{$secret}")->assertOk()->assertJsonPath('data', [
            'business' => 'Karim Traders', 'invited_by' => 'Nadia Karim', 'role' => 'Sales User', 'name' => 'Rahim Uddin',
            'email' => 'rahim@example.com', 'account_exists' => true, 'expires_at' => WorkspaceMember::where('email', 'rahim@example.com')->sole()->invitation_expires_at->toIso8601String(),
        ]);

        // Not yet a user: the business does not open for him.
        Sanctum::actingAs($rahim);
        $this->getJson('/api/v1/sales/invoices', $this->pick($this->business))->assertNotFound();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v1/invitations/{$secret}/accept")->assertForbidden()
            ->assertJsonPath('message', 'This invitation is for rahim@example.com. Sign in with that account to accept it.');

        Sanctum::actingAs($rahim);
        $this->postJson("/api/v1/invitations/{$secret}/accept")->assertOk()
            ->assertJsonPath('message', 'Welcome to Karim Traders.')
            ->assertJsonPath('data', ['workspace_id' => $this->business->id, 'name' => 'Karim Traders']);

        $member = WorkspaceMember::where('email', 'rahim@example.com')->sole();
        $this->assertSame([WorkspaceMember::ACTIVE, $rahim->id, null], [$member->status, $member->user_id, $member->invitation_token]);

        // In, as a Sales User - and the link is spent.
        $this->getJson('/api/v1/sales/invoices', $this->pick($this->business))->assertOk();
        $this->getJson('/api/v1/roles', $this->pick($this->business))->assertForbidden();
        $this->getJson("/api/v1/invitations/{$secret}")->assertNotFound();
    }

    public function test_someone_new_makes_an_account_from_the_link(): void
    {
        $secret = $this->invite('karim@example.com', ['timezone' => 'Asia/Dhaka']);

        $this->getJson("/api/v1/invitations/{$secret}")->assertJsonPath('data.account_exists', false);

        $this->postJson("/api/v1/invitations/{$secret}/register", ['first_name' => 'Karim'])
            ->assertJsonValidationErrors(['password', 'accept_terms']);

        $response = $this->postJson("/api/v1/invitations/{$secret}/register", [
            'first_name' => 'Karim', 'last_name' => 'Ali', 'password' => 'Secret!234', 'password_confirmation' => 'Secret!234', 'accept_terms' => true,
        ])->assertCreated();

        $response->assertJsonPath('token_type', 'Bearer')->assertJsonPath('data.workspace_id', $this->business->id);
        $this->assertNotEmpty($response->json('token'));

        $karim = User::where('email', 'karim@example.com')->sole();
        $this->assertNotNull($karim->email_verified_at);
        $this->assertSame(['karim', 'Asia/Dhaka'], [$karim->username, $karim->timezone]);
        $this->assertSame(0, $karim->workspaces()->count());
        $this->assertSame(WorkspaceMember::ACTIVE, WorkspaceMember::where('user_id', $karim->id)->sole()->status);

        // Spent: neither link can make another account.
        $this->postJson("/api/v1/invitations/{$secret}/register", ['first_name' => 'X', 'password' => 'Secret!234', 'password_confirmation' => 'Secret!234', 'accept_terms' => true])
            ->assertNotFound();

        // Invited without a time zone, the account takes the default.
        $other = $this->invite('salma@example.com');
        $this->postJson("/api/v1/invitations/{$other}/register", ['first_name' => 'Salma', 'password' => 'Secret!234', 'password_confirmation' => 'Secret!234', 'accept_terms' => true])
            ->assertCreated();
        $this->assertSame('UTC', User::where('email', 'salma@example.com')->sole()->timezone);
    }

    public function test_an_expired_link_is_refused_and_sending_again_makes_a_new_one(): void
    {
        $first = $this->invite('rahim@example.com');
        $id    = WorkspaceMember::where('email', 'rahim@example.com')->value('id');

        $this->travel(8)->days();

        $this->getJson("/api/v1/invitations/{$first}")->assertStatus(410)
            ->assertJsonPath('message', 'This invitation has expired. Ask the business to send it again.');
        $this->getJson('/api/v1/members')->assertJsonPath('data.1.invitation', 'expired');

        $this->postJson("/api/v1/members/{$id}/resend")->assertOk()->assertJsonPath('message', 'Invitation sent to rahim@example.com.');
        $second = $this->lastSecret('rahim@example.com');

        $this->assertNotSame($first, $second);
        $this->getJson("/api/v1/invitations/{$first}")->assertNotFound();
        $this->getJson("/api/v1/invitations/{$second}")->assertOk();
    }

    public function test_a_cancelled_invitation_stops_working(): void
    {
        $secret = $this->invite('rahim@example.com');
        $id     = WorkspaceMember::where('email', 'rahim@example.com')->value('id');

        $this->deleteJson("/api/v1/members/{$id}")->assertOk()->assertJsonPath('message', 'Invitation cancelled.');

        $this->assertNull(WorkspaceMember::find($id));
        $this->getJson("/api/v1/invitations/{$secret}")->assertNotFound();

        // An active user is removed, not cancelled.
        $owner = WorkspaceMember::where('user_id', $this->owner->id)->value('id');
        $this->deleteJson("/api/v1/members/{$owner}")->assertStatus(409);
    }
}
