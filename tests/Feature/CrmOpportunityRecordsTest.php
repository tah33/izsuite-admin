<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\Shared\RecordComment;
use App\Models\Shared\UserNotification;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 17 Phase D2b: an opportunity's files, comments and timeline - Doc
 * 14's, as a lead's (Phase B2b) - for whoever sees the opportunity; files
 * changed with Create and Edit while it is not archived, mentions only of
 * people who see it.
 */
class CrmOpportunityRecordsTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private int $opportunity;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $crm = Application::create(['app_key' => 'crm', 'name' => 'CRM', 'family_key' => 'crm', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 20, 'is_active' => true, 'status' => 'available']);
        $crm->packages()->attach(Package::pluck('id'));

        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->postJson('/api/v1/apps/crm/activate')->assertOk();

        $contact           = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin Traders']);
        $stage             = $this->getJson('/api/v1/crm/opportunities/options')->json('data.stages.0.id');
        $this->opportunity = $this->postJson('/api/v1/crm/opportunities', [
            'name' => 'Uddin Traders - shelving', 'contact_id' => $contact->id, 'stage_id' => $stage,
            'expected_value' => '2000', 'currency' => $this->business->base_currency, 'expected_close_date' => '2026-12-20',
        ])->assertCreated()->json('data.id');
    }

    /** A member whose role holds $permissions. */
    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id]);

        return $user;
    }

    private function attach(string $name): \Illuminate\Testing\TestResponse
    {
        return $this->post("/api/v1/crm/opportunities/{$this->opportunity}/attachments", ['files' => [UploadedFile::fake()->create($name, 120, 'application/pdf')]], ['Accept' => 'application/json']);
    }

    public function test_its_files_are_kept_by_whoever_may_edit_it_and_open_for_whoever_sees_it(): void
    {
        $file = $this->attach('proposal.pdf')->assertCreated()->assertJsonPath('message', 'Attachment added.')->json('data.0.id');

        $this->getJson("/api/v1/crm/opportunities/{$this->opportunity}/attachments")->assertOk()->assertJsonPath('data.0.name', 'proposal.pdf');
        $this->assertTrue(ActivityLog::query()->where('description', 'Attached "proposal.pdf" to opportunity "Uddin Traders - shelving"')->exists());

        // Someone who sees every opportunity opens it, but may not change the files without Create and Edit.
        Sanctum::actingAs($this->member(['crm.opportunities.view', 'crm.records.all']));
        $this->getJson("/api/v1/files/{$file}/link")->assertOk();
        $this->attach('more.pdf')->assertForbidden();
        $this->deleteJson("/api/v1/crm/opportunities/{$this->opportunity}/attachments/{$file}")->assertForbidden();

        // Someone who sees only their own does not reach this one's files.
        Sanctum::actingAs($this->member(['crm.opportunities.view', 'crm.opportunities.manage']));
        $this->getJson("/api/v1/files/{$file}/link")->assertNotFound();
        $this->getJson("/api/v1/crm/opportunities/{$this->opportunity}/attachments")->assertNotFound();

        // Archived, its files stay as they are until it is restored; won, they still change.
        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/crm/opportunities/{$this->opportunity}/archive")->assertOk();
        $this->attach('late.pdf')->assertStatus(409)->assertJsonPath('message', 'An archived opportunity is restored before it changes.');
        $this->deleteJson("/api/v1/crm/opportunities/{$this->opportunity}/attachments/{$file}")->assertStatus(409);
        $this->postJson("/api/v1/crm/opportunities/{$this->opportunity}/restore")->assertOk();
        $this->postJson("/api/v1/crm/opportunities/{$this->opportunity}/win", ['closed_on' => now()->toDateString()])->assertOk();
        $this->attach('signed-order.pdf')->assertCreated();

        $this->deleteJson("/api/v1/crm/opportunities/{$this->opportunity}/attachments/{$file}")->assertOk()->assertJsonPath('message', 'Attachment removed.');
        $this->assertSame(['signed-order.pdf'], array_column($this->getJson("/api/v1/crm/opportunities/{$this->opportunity}/attachments")->json('data'), 'name'));
    }

    public function test_a_comment_mentions_only_people_who_see_it(): void
    {
        $all  = $this->member(['crm.opportunities.view', 'crm.records.all']);
        $own  = $this->member(['crm.opportunities.view', 'crm.opportunities.manage']);
        $none = $this->member(['contacts.view']);

        $this->assertSame([$all->id], array_column($this->getJson("/api/v1/crm/opportunities/{$this->opportunity}/comments/people")->assertOk()->json('data'), 'id'));

        $comment = $this->postJson("/api/v1/crm/opportunities/{$this->opportunity}/comments", ['body' => 'They want it before Eid.', 'mentions' => [$all->id, $own->id, $none->id]])
            ->assertCreated()->json('data');
        $this->assertSame([$all->id], array_column(RecordComment::findOrFail($comment['id'])->mentions, 'id'));

        $notice = UserNotification::query()->where('user_id', $all->id)->sole();
        $this->assertSame(['comments.mentioned', "/crm/opportunities/{$this->opportunity}#comments"], [$notice->trigger, $notice->link]);
        $this->assertSame("{$this->user->name} mentioned you on opportunity \"Uddin Traders - shelving\"", $notice->title);

        Sanctum::actingAs($all);
        $this->getJson("/api/v1/crm/opportunities/{$this->opportunity}/comments")->assertOk()->assertJsonCount(1, 'data');
        $this->postJson("/api/v1/crm/opportunities/{$this->opportunity}/comments", ['body' => 'Noted.'])->assertCreated();

        Sanctum::actingAs($own);
        $this->getJson("/api/v1/crm/opportunities/{$this->opportunity}/comments")->assertNotFound();

        // Archived, it is still talked about.
        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/crm/opportunities/{$this->opportunity}/archive")->assertOk();
        $this->postJson("/api/v1/crm/opportunities/{$this->opportunity}/comments", ['body' => 'Archived: they went quiet.'])->assertCreated();
        $this->deleteJson("/api/v1/crm/opportunities/{$this->opportunity}/comments/{$comment['id']}")->assertOk();
    }

    public function test_its_timeline_reads_its_activity_newest_first(): void
    {
        $proposal = collect($this->getJson('/api/v1/crm/opportunities/options')->json('data.stages'))->firstWhere('name', 'Proposal')['id'];
        $this->postJson("/api/v1/crm/opportunities/{$this->opportunity}/stage", ['stage_id' => $proposal])->assertOk();
        $this->postJson("/api/v1/crm/opportunities/{$this->opportunity}/comments", ['body' => 'Sent the proposal.'])->assertCreated();

        $timeline = $this->getJson("/api/v1/crm/opportunities/{$this->opportunity}/activity")->assertOk()->json('data');
        $this->assertSame(
            ['Commented on opportunity "Uddin Traders - shelving"', 'Moved opportunity "Uddin Traders - shelving" from New to Proposal', 'Added opportunity "Uddin Traders - shelving" for contact "Uddin Traders"'],
            array_column($timeline, 'description'),
        );

        // A move shows the stages by name and the probability - not ids and times.
        $this->assertSame([['setting' => 'stage', 'from' => 'New', 'to' => 'Proposal'], ['setting' => 'probability', 'from' => '10', 'to' => '50']], $timeline[1]['changes']);

        // Winning and archiving say what changed the same way, or nothing beyond their words.
        $this->postJson("/api/v1/crm/opportunities/{$this->opportunity}/win", ['closed_on' => now()->toDateString(), 'value' => '1800'])->assertOk();
        $this->postJson("/api/v1/crm/opportunities/{$this->opportunity}/archive")->assertOk();
        $timeline = $this->getJson("/api/v1/crm/opportunities/{$this->opportunity}/activity")->json('data');
        $this->assertSame([[], ['status', 'stage', 'probability', 'value']], [$timeline[0]['changes'], array_column($timeline[1]['changes'], 'setting')]);

        // Not for someone who does not see it.
        Sanctum::actingAs($this->member(['crm.opportunities.view']));
        $this->getJson("/api/v1/crm/opportunities/{$this->opportunity}/activity")->assertNotFound();
    }
}
