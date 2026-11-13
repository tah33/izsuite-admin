<?php

namespace Tests\Feature;

use App\Events\AppActivated;
use App\Events\AppDeactivated;
use App\Mail\NotificationMail;
use App\Mail\WorkspaceInvitation;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\UserNotification;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceFile;
use App\Services\Shared\Notifications\Notice;
use App\Services\Shared\Notifications\Notifier;
use App\Services\Shared\Notifications\UsageAlerts;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use App\Support\Permissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Notification preferences and usage limits (Doc 11 and Doc 12 section 22,
 * Doc 13 section 19) - Doc 14 Phase B2: a notification goes in the app, or
 * by e-mail, only where the business sends it and the user receives it;
 * subscription notices always; by e-mail only, it stays out of the bell. The
 * plan's limits nearly reached, reached or passed are told once each.
 */
class NotificationPreferencesTest extends TestCase
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
        $this->business->package()->associate(Package::where('key', 'standard')->sole())->save();
        app(SetUpNewWorkspace::class)->handle($this->business, $this->owner, 'api');
    }

    /** $email invited by the owner with role $template, and joined: their account. */
    private function member(string $email, string $template, string $first): User
    {
        $this->postJson('/api/v1/members', [
            'name' => "{$first} Uddin", 'email' => $email, 'role_id' => $this->business->roles()->where('template', $template)->value('id'), 'location_access' => 'all',
        ])->assertCreated();

        $link = Mail::sent(WorkspaceInvitation::class, fn (WorkspaceInvitation $mail) => $mail->hasTo($email))->last()->link;
        $user = User::factory()->create(['email' => $email, 'first_name' => $first, 'last_name' => 'Uddin']);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/invitations/'.substr($link, strrpos($link, '/') + 1).'/accept')->assertOk();
        Sanctum::actingAs($this->owner);

        return $user;
    }

    /** Every kind as it is unless chosen, with $changes: kind => [in the app, by e-mail]. */
    private function choose(array $changes): array
    {
        $kinds = ['subscription' => [true, false], 'usage' => [true, true], 'apps' => [true, false], 'members' => [true, true], 'comments' => [true, true], 'izsuite' => [true, false], ...$changes];

        return array_map(fn (array $channels) => ['in_app' => $channels[0], 'email' => $channels[1]], $kinds);
    }

    private function of(User $user): array
    {
        return UserNotification::where('user_id', $user->id)->orderBy('id')->pluck('trigger')->all();
    }

    public function test_a_notification_goes_only_where_the_business_and_the_user_allow(): void
    {
        $salma = $this->member('salma@example.com', 'administrator', 'Salma');
        $app   = Application::firstOrFail();

        $kinds = $this->getJson('/api/v1/business-settings/notifications')->assertOk()->json('data.kinds');

        // Comments and mentions came with Doc 14 Phase D, by e-mail too unless turned off; messages from izSuite with Doc 15 Phase B1, always on.
        $this->assertSame(['subscription', 'usage', 'apps', 'members', 'comments', 'izsuite'], array_column($kinds, 'key'));
        $this->assertSame(['key', 'label', 'description', 'locked', 'in_app', 'email'], array_keys($kinds[0]));
        $this->assertSame([[true, true, false], [false, true, true], [false, true, false], [false, true, true], [false, true, true], [true, true, false]], array_map(fn (array $kind) => [$kind['locked'], $kind['in_app'], $kind['email']], $kinds));

        // The business sends app notices by e-mail only: out of Salma's bell, in her inbox.
        $this->putJson('/api/v1/business-settings/notifications', ['preferences' => $this->choose(['apps' => [false, true]])])
            ->assertOk()->assertJsonPath('message', 'Notification settings saved.')
            ->assertJsonPath('data.kinds.2.in_app', false)->assertJsonPath('data.kinds.2.email', true);
        $this->assertDatabaseHas('activity_logs', ['description' => 'Changed which notifications the business sends: Apps']);

        AppActivated::dispatch($this->business, $app);

        $notification = UserNotification::where('user_id', $salma->id)->sole();

        $this->assertSame(['apps.activated', false], [$notification->trigger, $notification->shown]);
        $this->assertSame(['email' => 'sent'], $notification->deliveries()->pluck('status', 'channel')->all());
        Mail::assertSent(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo('salma@example.com') && $mail->notification->is($notification));

        Sanctum::actingAs($salma);
        $pick = $this->pick($this->business);

        $this->getJson('/api/v1/notifications/unread', $pick)->assertOk()->assertJsonPath('data.unread', 0);
        $this->getJson('/api/v1/notifications', $pick)->assertOk()->assertJsonPath('pagination.total', 0);
        $this->postJson("/api/v1/notifications/{$notification->id}/read", [], $pick)->assertNotFound();

        // A user receives what the business sends until they say otherwise. Always-on kinds never e-mail through here.
        $this->assertSame(
            [[true, false], [true, true], [true, true], [true, true], [true, true], [true, false]],
            array_map(fn (array $kind) => [$kind['in_app'], $kind['email']], $this->getJson('/api/v1/profile/notifications')->assertOk()->json('data.kinds')),
        );

        // Salma wants no app e-mails: the next one reaches her by neither, so is not made.
        $this->putJson('/api/v1/profile/notifications', ['preferences' => $this->choose(['apps' => [true, false]])])
            ->assertOk()->assertJsonPath('message', 'Your notification preferences are saved.')->assertJsonPath('data.kinds.2.email', false);
        $this->getJson('/api/v1/profile/notifications')->assertOk()->assertJsonPath('data.kinds.2.in_app', true)->assertJsonPath('data.kinds.2.email', false);

        Sanctum::actingAs($this->owner);
        AppDeactivated::dispatch($this->business, $app);

        $this->assertSame(['apps.activated'], $this->of($salma));
    }

    public function test_subscription_notices_cannot_be_turned_off(): void
    {
        $this->putJson('/api/v1/business-settings/notifications', ['preferences' => $this->choose(['subscription' => [false, true]])])
            ->assertOk()->assertJsonPath('data.kinds.0.in_app', true)->assertJsonPath('data.kinds.0.email', false);
        $this->putJson('/api/v1/profile/notifications', ['preferences' => $this->choose(['subscription' => [false, false]])])
            ->assertOk()->assertJsonPath('data.kinds.0.in_app', true);

        // Nothing the business sends changed.
        $this->assertDatabaseMissing('activity_logs', ['description' => 'Changed which notifications the business sends: Subscription and billing']);

        app(Notifier::class)->send($this->business, [$this->owner], new Notice('subscription.renewal', 'subscription.renewal:test', 'Your renewal invoice is ready'));

        $this->assertTrue(UserNotification::where('trigger', 'subscription.renewal')->sole()->shown);
    }

    public function test_what_the_business_sends_is_changed_with_business_settings_and_each_users_by_them(): void
    {
        $viewer = User::factory()->create();
        $role   = $this->business->roles()->create(['name' => 'Settings Viewer', 'permissions' => Permissions::expand(['settings.view'])]);
        $this->business->members()->create(['user_id' => $viewer->id, 'role_id' => $role->id, 'status' => 'active']);

        Sanctum::actingAs($viewer);
        $pick = $this->pick($this->business);

        $this->getJson('/api/v1/business-settings/notifications', $pick)->assertOk();
        $this->putJson('/api/v1/business-settings/notifications', ['preferences' => $this->choose([])], $pick)->assertForbidden();

        // Their own are theirs to choose - every kind and channel, nothing else.
        $this->putJson('/api/v1/profile/notifications', ['preferences' => $this->choose(['members' => [false, false]])])
            ->assertOk()->assertJsonPath('data.kinds.3.in_app', false)->assertJsonPath('data.kinds.3.email', false);
        $this->putJson('/api/v1/profile/notifications', ['preferences' => ['apps' => ['in_app' => true, 'email' => true]]])
            ->assertJsonValidationErrors(['preferences.subscription', 'preferences.usage', 'preferences.members']);
        $this->putJson('/api/v1/profile/notifications', ['preferences' => [...$this->choose([]), 'security' => ['in_app' => true, 'email' => true]]])
            ->assertJsonValidationErrors('preferences');
        $this->putJson('/api/v1/profile/notifications', ['preferences' => $this->choose(['apps' => ['yes', true]])])
            ->assertJsonValidationErrors('preferences.apps.in_app');
    }

    public function test_the_plans_limits_are_told_once_for_each_limit_and_state(): void
    {
        $salma  = $this->member('salma@example.com', 'administrator', 'Salma');
        $rahim  = $this->member('rahim@example.com', 'sales_user', 'Rahim');
        $alerts = app(UsageAlerts::class);
        $plan   = Package::where('key', 'standard')->value('name');
        $file   = fn (int $size) => WorkspaceFile::create([
            'workspace_id' => $this->business->id, 'attachable_type' => Workspace::class, 'attachable_id' => $this->business->id, 'collection' => 'attachments',
            'disk' => 'local', 'path' => 'files/'.$size.'.pdf', 'visibility' => 'private', 'name' => 'a.pdf', 'mime_type' => 'application/pdf', 'size' => $size,
        ]);

        // Three users of three; a location of one, as every Billing Starter business has - not told; storage nearly full.
        Package::where('key', 'standard')->update(['user_limit' => 3, 'location_limit' => 1, 'storage_limit_mb' => 1]);
        $file(900 * 1024);

        $this->assertSame(4, $alerts->check($this->business->fresh()));
        $this->assertSame(0, $alerts->check($this->business->fresh()));

        $this->assertSame(['members.joined', 'members.joined', 'usage.storage.warning', 'usage.users.full'], $this->of($this->owner));
        $this->assertSame(['usage.storage.warning', 'usage.users.full'], $this->of($salma));
        $this->assertSame([], $this->of($rahim));

        $users = UserNotification::where('user_id', $this->owner->id)->where('trigger', 'usage.users.full')->sole();

        $this->assertSame(
            ['All the users the plan includes are in use', "All 3 users {$plan} includes are in use. Upgrade the plan to add another.", '/settings?tab=subscription', true],
            [$users->title, $users->body, $users->link, $users->shown],
        );
        $this->assertSame('/settings?tab=storage', UserNotification::where('trigger', 'usage.storage.warning')->value('link'));
        Mail::assertSent(NotificationMail::class, fn (NotificationMail $mail) => $mail->hasTo($this->owner->email) && $mail->notification->is($users));

        // Storage full, and a lower limit passed: each a state of its own.
        $file(200 * 1024);
        Package::where('key', 'standard')->update(['user_limit' => 2]);

        $this->assertSame(4, $alerts->check($this->business->fresh()));
        $this->assertSame(['usage.storage.warning', 'usage.users.full', 'usage.storage.full', 'usage.users.over'], $this->of($salma));
        $this->assertSame(
            "3 users where {$plan} includes 2: the Business Owner chooses which stay active.",
            UserNotification::where('user_id', $salma->id)->where('trigger', 'usage.users.over')->value('body'),
        );

        // The hourly command finds nothing new.
        $this->artisan('notifications:usage')->assertSuccessful();
        $this->assertSame(10, UserNotification::count());
    }
}
