<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\UserNotification;
use App\Models\User\Contact;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 17 Phase E2: whom an activity is given to hears of it, and its
 * assignee is reminded before it is due - in the bell and by e-mail, under
 * Activities, in the business's time (Dhaka, six hours ahead of UTC).
 * Monday 16 November 2026, 10:00 UTC - 16:00 in Dhaka.
 */
class CrmActivityRemindersTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private Contact $contact;

    /** @var array<string, int> the activity types by key */
    private array $types;

    private int $opportunity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $crm = Application::create(['app_key' => 'crm', 'name' => 'CRM', 'family_key' => 'crm', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 20, 'is_active' => true, 'status' => 'available']);
        $crm->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-11-16 10:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->business->forceFill(['base_currency' => 'USD'])->save();
        $this->business->businessSetting()->create([
            'user_id' => $this->user->id, 'business_name' => 'Main Business', 'language' => 'en', 'country' => 'BD', 'state' => 'Dhaka',
            'city'    => 'Dhaka', 'zip_code' => '1207', 'address_line_1' => 'Road 1', 'timezone' => 'Asia/Dhaka', 'stock_accounting_method' => 'fifo',
        ]);
        $this->postJson('/api/v1/apps/crm/activate')->assertOk();

        $this->contact     = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin Traders']);
        $this->types       = collect($this->getJson('/api/v1/crm/activities/options')->json('data.types'))->pluck('id', 'key')->all();
        $stage             = $this->getJson('/api/v1/business-settings/crm')->json('data.pipelines.0.stages.0.id');
        $this->opportunity = $this->postJson('/api/v1/crm/opportunities', [
            'name' => 'Uddin Traders - racking', 'contact_id' => $this->contact->id, 'stage_id' => $stage, 'expected_value' => '2000', 'currency' => 'USD', 'expected_close_date' => '2026-12-20',
        ])->assertCreated()->json('data.id');
    }

    /** A call for the opportunity, due today at 17:00 in Dhaka - 11:00 UTC - unless $fields say otherwise. */
    private function plan(array $fields = []): TestResponse
    {
        return $this->postJson('/api/v1/crm/activities', [
            'opportunity_id' => $this->opportunity, 'type_id' => $this->types['call'], 'subject' => 'Call about the racking', 'due_on' => '2026-11-16', 'due_time' => '17:00', ...$fields,
        ])->assertCreated();
    }

    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    /** $user's notices of $trigger: [title, body, link]. */
    private function notices(User $user, string $trigger): array
    {
        return UserNotification::query()->where('user_id', $user->id)->where('trigger', $trigger)->orderBy('id')->get()
            ->map(fn (UserNotification $notice) => [$notice->title, $notice->body, $notice->link])->all();
    }

    public function test_whom_an_activity_is_given_to_hears_of_it_unless_they_gave_it_themselves(): void
    {
        $ana = $this->member(['crm.activities.view', 'crm.activities.manage']);
        $bob = $this->member(['crm.activities.view', 'crm.activities.manage', 'crm.opportunities.view', 'crm.records.all']);

        $this->plan(['subject' => 'My own call']);
        $call = $this->plan(['assigned_to' => $ana->id])->json('data.id');

        // Ana does not see the opportunity: hers leads to the Activities page.
        $this->assertSame([], $this->notices($this->user, 'activities.assigned'));
        $this->assertSame([[
            $this->user->name.' gave you an activity: Call "Call about the racking"', 'Due 16 Nov 2026, 17:00, for Uddin Traders - racking', '/crm/activities',
        ]], $this->notices($ana, 'activities.assigned'));

        // Given to Bob, who sees every record: his leads to the opportunity. Saving it unchanged tells no one.
        $fields = ['type_id' => $this->types['call'], 'subject' => 'Call about the racking', 'due_on' => '2026-11-16', 'due_time' => '17:00'];
        $this->putJson("/api/v1/crm/activities/{$call}", [...$fields, 'assigned_to' => $bob->id])->assertOk();
        $this->putJson("/api/v1/crm/activities/{$call}", [...$fields, 'assigned_to' => $bob->id])->assertOk();
        $this->assertSame([[
            $this->user->name.' gave you an activity: Call "Call about the racking"', 'Due 16 Nov 2026, 17:00, for Uddin Traders - racking', "/crm/opportunities/{$this->opportunity}",
        ]], $this->notices($bob, 'activities.assigned'));

        // A follow-up given to Ana.
        $this->postJson("/api/v1/crm/activities/{$call}/complete", ['outcome' => 'Spoke to Rahim.', 'follow_up' => [
            'type_id' => $this->types['task'], 'subject' => 'Send the quotation', 'due_on' => '2026-11-17', 'due_time' => '10:00', 'assigned_to' => $ana->id,
        ]])->assertOk();
        $this->assertSame([$this->user->name.' gave you an activity: Task "Send the quotation"', 'Due 17 Nov 2026, 10:00, for Uddin Traders - racking'], array_slice($this->notices($ana, 'activities.assigned')[1], 0, 2));
    }

    public function test_a_reminder_goes_once_to_its_assignee_when_its_moment_comes(): void
    {
        // Left out, 15 minutes before: 10:45 UTC.
        $call = $this->plan()->assertJsonPath('data.remind_minutes', 15)->json('data.id');
        $this->plan(['subject' => 'No reminder', 'remind_minutes' => null]);
        $this->plan(['subject' => 'Check the stock', 'type_id' => $this->types['task']]);
        $this->postJson('/api/v1/crm/activities/'.$this->plan(['subject' => 'Done', 'type_id' => $this->types['task']])->json('data.id').'/complete')->assertOk();
        // Logged after its time: no reminder.
        $this->plan(['subject' => 'Made this morning', 'due_time' => '09:00'])->assertJsonPath('data.remind_minutes', 15);

        $this->artisan('crm:remind-activities')->expectsOutput('Sent 0 reminders.')->assertSuccessful();

        $this->travelTo(Carbon::parse('2026-11-16 10:46:00', 'UTC'));
        $this->artisan('crm:remind-activities')->expectsOutput('Sent 2 reminders.')->assertSuccessful();
        $this->assertSame([
            ['Reminder: Call "Call about the racking", due 16 Nov 2026, 17:00', 'For Uddin Traders - racking', "/crm/opportunities/{$this->opportunity}"],
            ['Reminder: Task "Check the stock", due 16 Nov 2026, 17:00', 'For Uddin Traders - racking', "/crm/opportunities/{$this->opportunity}"],
        ], $this->notices($this->user, 'activities.reminder'));

        // Once only - until it is moved to another time.
        $this->artisan('crm:remind-activities')->expectsOutput('Sent 0 reminders.')->assertSuccessful();
        $this->putJson("/api/v1/crm/activities/{$call}", ['type_id' => $this->types['call'], 'subject' => 'Call about the racking', 'due_on' => '2026-11-16', 'due_time' => '18:00'])->assertOk()
            ->assertJsonPath('data.remind_minutes', 15);
        $this->artisan('crm:remind-activities')->expectsOutput('Sent 0 reminders.')->assertSuccessful();

        $this->travelTo(Carbon::parse('2026-11-16 11:46:00', 'UTC'));
        $this->artisan('crm:remind-activities')->expectsOutput('Sent 1 reminder.')->assertSuccessful();
        $this->assertSame('Reminder: Call "Call about the racking", due 16 Nov 2026, 18:00', $this->notices($this->user, 'activities.reminder')[2][0]);
    }

    public function test_no_reminder_reaches_someone_no_longer_given_activities_or_a_business_without_crm(): void
    {
        $ana = $this->member(['crm.activities.view', 'crm.activities.manage']);
        $this->plan(['assigned_to' => $ana->id, 'due_time' => '16:20', 'remind_minutes' => 0]);

        // Ana's role no longer views activities; and CRM is turned off for the owner's.
        $this->business->roles()->where('name', 'Role '.$ana->id)->update(['permissions' => ['contacts.view']]);
        $this->plan(['subject' => 'Mine', 'due_time' => '16:30', 'remind_minutes' => 5]);
        $this->postJson('/api/v1/apps/crm/deactivate')->assertOk();

        $this->travelTo(Carbon::parse('2026-11-16 10:40:00', 'UTC'));
        $this->artisan('crm:remind-activities')->expectsOutput('Sent 0 reminders.')->assertSuccessful();
        $this->assertSame([[], []], [$this->notices($ana, 'activities.reminder'), $this->notices($this->user, 'activities.reminder')]);
    }
}
