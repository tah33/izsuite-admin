<?php

namespace Tests\Feature;

use App\Models\Admin\Announcement;
use App\Models\Admin\AdminAuditLog;
use App\Models\Admin\Role;
use App\Models\Frontend\AppCategory;
use App\Models\Frontend\Application;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceApp;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Doc 15 Phase F1 (section 15.21): platform announcements - banners for
 * all businesses, those on chosen plans, in chosen countries, with chosen
 * apps on or named, or for izSuite's admins only - between their days, the
 * most urgent first, each dismissed by a person for themselves unless it is
 * not dismissible.
 */
class AnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private Workspace $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'UTC'));

        $this->admin    = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID, 'first_name' => 'Rafi']);
        $this->owner    = User::factory()->create();
        $this->business = $this->owner->workspaces()->create(['name' => 'Karim Stores']);
        $this->business->businessSetting()->create([
            'user_id' => $this->owner->id, 'business_name' => 'Karim Stores', 'language' => 'en', 'country' => 'BD', 'state' => 'Dhaka',
            'city'    => 'Dhaka', 'zip_code' => '1207', 'address_line_1' => 'Near City Center', 'timezone' => 'UTC', 'stock_accounting_method' => 'fifo',
        ]);

        // The business has Inventory on.
        $category = AppCategory::create(['name' => 'Operations', 'sort_order' => 1]);
        foreach (['inventory', 'crm'] as $order => $family) {
            $app = Application::create(['app_key' => $family, 'name' => ucfirst($family), 'family_key' => $family, 'edition' => 'full', 'category_id' => $category->id, 'sort_order' => $order, 'is_active' => true, 'status' => Application::PUBLISHED]);

            if ($family === 'inventory') {
                WorkspaceApp::create(['workspace_id' => $this->business->id, 'family_key' => $family, 'app_id' => $app->id, 'status' => WorkspaceApp::STATUS_ACTIVE, 'activated_at' => now()]);
            }
        }
    }

    private function announce(string $title, array $fields = []): Announcement
    {
        return Announcement::create(['title' => $title, 'message' => "{$title}, in full.", 'audience' => 'all', 'priority' => 'info', 'dismissible' => true, ...$fields]);
    }

    /** @return list<string> the titles the owner sees, in order */
    private function seen(?User $user = null): array
    {
        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($user ?? $this->owner);

        return array_column($this->getJson('/api/v1/announcements')->assertOk()->json('data'), 'title');
    }

    public function test_an_admin_adds_changes_and_deletes_an_announcement(): void
    {
        $this->actingAs($this->admin)->get(route('admin.announcements.create'))->assertOk()->assertSee('Who Sees It')->assertSee('Inventory');

        $form = ['title' => 'New reports', 'message' => 'Profit and loss is here.', 'audience' => 'packages', 'priority' => 'important', 'dismissible' => 1, 'link_url' => '/reports'];

        // The audience's list, real businesses, a link the app can open, and an end after the start.
        $this->postJson(route('admin.announcements.store'), $form)->assertJsonValidationErrors(['packages' => 'Choose a plan at least.']);
        $this->postJson(route('admin.announcements.store'), [...$form, 'audience' => 'businesses', 'businesses' => 'BW-999999'])->assertJsonValidationErrors(['businesses' => 'No business has the ID BW-999999.']);
        $this->postJson(route('admin.announcements.store'), [...$form, 'packages' => ['standard'], 'link_url' => 'javascript:alert(1)', 'starts_at' => '2026-11-20 09:00', 'ends_at' => '2026-11-19 09:00'])
            ->assertJsonValidationErrors(['link_url', 'ends_at']);

        $this->postJson(route('admin.announcements.store'), [...$form, 'packages' => ['standard', 'standard']])->assertOk()->assertJsonPath('message', '"New reports" is added.');

        $announcement = Announcement::sole();
        $this->assertSame([['standard'], 'Learn more', $this->admin->id], [$announcement->targets, $announcement->link_label, $announcement->created_by]);
        $this->get(route('admin.announcements.index'))->assertOk()->assertSee('New reports')->assertSee('Live')->assertSee('Businesses on chosen plans')
            ->assertSee('New reports goes from everyone')->assertDontSee(':action=', false);

        // Named businesses by their BW- IDs, as written.
        $this->putJson(route('admin.announcements.update', $announcement->id), [...$form, 'audience' => 'businesses', 'businesses' => "{$this->business->businessId()} Karim Stores", 'dismissible' => 0])->assertOk();
        $this->assertSame([[$this->business->id], false], [$announcement->fresh()->targets, $announcement->fresh()->dismissible]);
        $this->get(route('admin.announcements.edit', $announcement->id))->assertOk()->assertSee($this->business->businessId().' Karim Stores');

        $this->deleteJson(route('admin.announcements.destroy', $announcement->id))->assertOk();
        $this->assertSame(0, Announcement::count());
        $this->assertSame(['Added announcement "New reports"', 'Changed announcement "New reports"', 'Deleted announcement "New reports"'], AdminAuditLog::orderBy('id')->pluck('description')->all());
    }

    public function test_a_business_sees_those_whose_audience_reaches_it_the_most_urgent_first(): void
    {
        $other = User::factory()->create()->workspaces()->create(['name' => 'Elsewhere Ltd']);

        $this->announce('For everyone');
        $this->announce('On Billing Starter', ['audience' => 'packages', 'targets' => ['starter'], 'priority' => 'important']);
        $this->announce('On Premium', ['audience' => 'packages', 'targets' => ['premium']]);
        $this->announce('In Bangladesh', ['audience' => 'countries', 'targets' => ['BD']]);
        $this->announce('In India', ['audience' => 'countries', 'targets' => ['IN']]);
        $this->announce('With Inventory', ['audience' => 'apps', 'targets' => ['inventory']]);
        $this->announce('With CRM', ['audience' => 'apps', 'targets' => ['crm']]);
        $this->announce('Karim Stores only', ['audience' => 'businesses', 'targets' => [$this->business->id], 'priority' => 'emergency', 'dismissible' => false]);
        $this->announce('Another business', ['audience' => 'businesses', 'targets' => [$other->id]]);
        $this->announce('Admins only', ['audience' => 'admins']);
        $this->announce('Tomorrow', ['starts_at' => '2026-11-15 00:00:00']);
        $this->announce('Yesterday', ['ends_at' => '2026-11-14 09:59:00']);

        $this->assertSame(['Karim Stores only', 'On Billing Starter', 'With Inventory', 'In Bangladesh', 'For everyone'], $this->seen());

        $this->app['auth']->forgetGuards();
        Sanctum::actingAs($this->owner);
        $this->getJson('/api/v1/announcements')->assertJsonPath('data.0', [
            'id' => Announcement::where('title', 'Karim Stores only')->value('id'), 'title' => 'Karim Stores only', 'message' => 'Karim Stores only, in full.',
            'priority' => 'emergency', 'link_url' => null, 'link_label' => null, 'dismissible' => false,
        ]);

        // Tomorrow comes in; yesterday's stays gone.
        $this->travelTo(Carbon::parse('2026-11-15 08:00:00', 'UTC'));
        $this->assertContains('Tomorrow', $this->seen());
    }

    public function test_each_person_dismisses_one_for_themselves_unless_it_stays(): void
    {
        $news    = $this->announce('News');
        $legal   = $this->announce('New terms', ['priority' => 'important', 'dismissible' => false]);
        $private = $this->announce('Someone else', ['audience' => 'businesses', 'targets' => [999]]);

        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v1/announcements/{$news->id}/dismiss")->assertOk()->assertJsonPath('message', 'Dismissed.');
        $this->postJson("/api/v1/announcements/{$legal->id}/dismiss")->assertStatus(409)->assertJsonPath('message', 'This announcement stays until it ends.');
        $this->postJson("/api/v1/announcements/{$private->id}/dismiss")->assertNotFound();

        $this->assertSame(['New terms'], $this->seen());
        $this->assertFalse(\App\Models\Shared\ActivityLog::where('description', 'like', '%announcements%')->exists());

        // Another person still has it.
        $colleague = User::factory()->create();
        $colleague->workspaces()->create(['name' => 'Colleague Ltd']);
        $this->assertSame(['New terms', 'News'], $this->seen($colleague));
    }

    public function test_admins_see_their_own_above_every_admin_page_and_dismiss_them(): void
    {
        $note = $this->announce('Deploy tonight', ['audience' => 'admins', 'priority' => 'important']);

        $this->assertSame([], $this->seen());

        $this->actingAs($this->admin)->get(route('admin.overview'))->assertOk()->assertSee('Deploy tonight')->assertSee('Deploy tonight, in full.');

        // Any admin dismisses it for themselves; a read-only one sees the list, and only that.
        $reader = User::factory()->create(['role_id' => Role::where('slug', 'read-only-administrator')->value('id')]);
        $this->actingAs($reader)->postJson(route('admin.announcements.dismiss', $note->id))->assertOk();
        $this->actingAs($reader)->get(route('admin.overview'))->assertOk()->assertDontSee('Deploy tonight, in full.');
        $this->actingAs($reader)->get(route('admin.announcements.index'))->assertOk()->assertSee('Deploy tonight');
        $this->actingAs($reader)->get(route('admin.announcements.create'))->assertForbidden();

        $this->actingAs($this->admin)->get(route('admin.overview'))->assertSee('Deploy tonight, in full.');
    }
}
