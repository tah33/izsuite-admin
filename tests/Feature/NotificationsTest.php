<?php

namespace Tests\Feature;

use App\Events\AppActivated;
use App\Events\AppDeactivated;
use App\Mail\NotificationMail;
use App\Mail\WorkspaceInvitation;
use App\Models\Admin\Role;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\NotificationDelivery;
use App\Models\Shared\UserNotification;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Services\Shared\Notifications\Notice;
use App\Services\Shared\Notifications\Notifier;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Notifications (Doc 14 section 15) - Doc 14 Phase B1: one per user and
 * event, in the app and - where the trigger says - by e-mail with its
 * delivery kept; reaching only the users allowed to see what happened, not
 * the one who did it; and the bell's list, count and reading.
 */
class NotificationsTest extends TestCase
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
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])]);
        $this->offerSalesLite();
        $this->owner    = $this->signIn(User::factory()->create(['first_name' => 'Nadia', 'last_name' => 'Karim']));
        $this->business = $this->businessOf($this->owner);
        $this->business->update(['name' => 'Karim Traders']);
        $this->business->package()->associate(Package::where('key', 'standard')->sole())->save();
        app(SetUpNewWorkspace::class)->handle($this->business, $this->owner, 'api');
    }

    /** $email invited by the owner with role $template, and joined: their account. */
    private function member(string $email, string $template, string $first): User
    {
        $this->postJson('/api/v1/members', [
            'name' => "{$first} Uddin", 'email' => $email, 'role_id' => $this->business->roles()->where('template', $template)->value('id'), 'location_access' => 'all',
        ])->assertCreated();

        $link   = Mail::sent(WorkspaceInvitation::class, fn (WorkspaceInvitation $mail) => $mail->hasTo($email))->last()->link;
        $user   = User::factory()->create(['email' => $email, 'first_name' => $first, 'last_name' => 'Uddin']);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/invitations/'.substr($link, strrpos($link, '/') + 1).'/accept')->assertOk();
        Sanctum::actingAs($this->owner);

        return $user;
    }

    private function of(User $user): array
    {
        return UserNotification::where('user_id', $user->id)->orderBy('id')->pluck('trigger')->all();
    }

    public function test_an_accepted_invitation_tells_who_sent_it_in_the_app_and_by_email(): void
    {
        $rahim        = $this->member('rahim@example.com', 'sales_user', 'Rahim');
        $notification = UserNotification::where('user_id', $this->owner->id)->sole();

        $this->assertSame(
            ['members.joined', 'Rahim Uddin joined Karim Traders', 'They accepted your invitation as Sales User.', '/settings?tab=users'],
            [$notification->trigger, $notification->title, $notification->body, $notification->link],
        );
        $this->assertSame(['in_app' => 'sent', 'email' => 'sent'], $notification->deliveries()->pluck('status', 'channel')->all());
        Mail::assertSent(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo($this->owner->email) && $mail->notification->is($notification));
        $this->assertSame([], $this->of($rahim));
    }

    public function test_subscription_and_app_notices_reach_only_those_who_manage_them(): void
    {
        $rahim = $this->member('rahim@example.com', 'sales_user', 'Rahim');
        $salma = $this->member('salma@example.com', 'administrator', 'Salma');

        Package::where('key', 'premium')->update(['price_monthly' => '49.00']);
        $this->putJson('/api/v1/subscription/billing-details', ['name' => 'Karim Traders', 'email' => 'accounts@karim.example', 'address' => 'Dhaka', 'country' => 'BD'])->assertOk();
        $id = $this->postJson('/api/v1/subscription', ['package' => 'premium', 'cycle' => 'monthly'])->assertCreated()->json('data.open_invoice.id');

        // The owner chose it: Salma is told, not the owner, not Rahim.
        $this->actingAs(User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]))->post(route('admin.subscription-invoices.confirm', $id))->assertRedirect();
        Sanctum::actingAs($this->owner);

        $this->assertSame(['members.joined', 'members.joined', 'subscription.paid'], $this->of($this->owner));
        $this->assertSame(['subscription.invoice', 'subscription.paid'], $this->of($salma));
        $this->assertSame([], $this->of($rahim));

        // An app turned on, then off - by the owner: Salma hears of both, once each.
        $app = Application::firstOrFail();
        AppActivated::dispatch($this->business, $app);
        AppActivated::dispatch($this->business, $app);
        AppDeactivated::dispatch($this->business, $app);

        $this->assertSame(['subscription.invoice', 'subscription.paid', 'apps.activated', 'apps.deactivated'], $this->of($salma));
        $this->assertSame("{$app->name} is turned off", UserNotification::where('user_id', $salma->id)->latest('id')->value('title'));
        $this->assertSame([], $this->of($rahim));
    }

    public function test_the_bell_lists_counts_and_reads_only_the_users_own(): void
    {
        $salma    = $this->member('salma@example.com', 'administrator', 'Salma');
        $notifier = app(Notifier::class);

        foreach (['one', 'two', 'three'] as $key) {
            $notifier->send($this->business, [$this->owner, $salma], new Notice('test.'.$key, "test:{$key}", ucfirst($key).' happened', null, '/dashboard'));
        }

        $this->getJson('/api/v1/notifications/unread')->assertOk()->assertJsonPath('data', ['unread' => 4]);
        $page  = $this->getJson('/api/v1/notifications?per_page=2')->assertOk()->assertJsonPath('pagination.total', 4)->json('data');

        $this->assertSame(['Three happened', 'Two happened'], array_column($page, 'title'));
        $this->assertSame(['id', 'trigger', 'title', 'body', 'link', 'read', 'created_at'], array_keys($page[0]));

        $this->postJson("/api/v1/notifications/{$page[0]['id']}/read")->assertOk()->assertJsonPath('data.read', true);
        $this->getJson('/api/v1/notifications/unread')->assertJsonPath('data.unread', 3);

        $theirs = UserNotification::where('user_id', $salma->id)->value('id');
        $this->postJson("/api/v1/notifications/{$theirs}/read")->assertNotFound();

        $this->postJson('/api/v1/notifications/read')->assertOk()->assertJsonPath('data.unread', 0);
        $this->assertSame(3, UserNotification::where('user_id', $salma->id)->whereNull('read_at')->count());
    }

    public function test_an_event_reaches_a_user_once_and_a_failed_email_is_kept_with_why(): void
    {
        $notifier = app(Notifier::class);
        $notice   = new Notice('test.once', 'test:once', 'Once only', null, null, null, true);

        $this->assertSame(1, $notifier->send($this->business, [$this->owner, $this->owner], $notice));
        $this->assertSame(0, $notifier->send($this->business, [$this->owner], $notice));

        $email = NotificationDelivery::where('channel', 'email')->sole();
        $notifier->emailFailed($email->id, new RuntimeException('Connection refused by the mail server.'));

        $this->assertSame(['failed', 'Connection refused by the mail server.'], [$email->fresh()->status, $email->fresh()->failure]);
    }
}
