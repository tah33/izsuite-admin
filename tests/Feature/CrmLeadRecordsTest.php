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
 * Doc 17 Phase B2b: a lead's files, comments and activity timeline - Doc 14's,
 * for whoever sees the lead. A comment mentions only people who see the lead
 * too, and its files open only for them.
 */
class CrmLeadRecordsTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private int $lead;

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

        $source     = $this->getJson('/api/v1/crm/leads/options')->json('data.sources.0.id');
        $this->lead = $this->postJson('/api/v1/crm/leads', ['name' => 'Rahim Uddin', 'email' => 'rahim@example.com', 'source_id' => $source])->assertCreated()->json('data.id');
    }

    /** A member whose role holds $permissions. */
    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->business->members()->create(['user_id' => $user->id, 'name' => $user->name, 'status' => 'active', 'role_id' => $role->id]);

        return $user;
    }

    public function test_a_leads_files_are_kept_by_whoever_may_edit_it_and_open_for_whoever_sees_it(): void
    {
        $file = $this->post("/api/v1/crm/leads/{$this->lead}/attachments", ['files' => [UploadedFile::fake()->create('requirements.pdf', 120, 'application/pdf')]], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('message', 'Attachment added.')->json('data.0.id');

        $this->getJson("/api/v1/crm/leads/{$this->lead}/attachments")->assertOk()->assertJsonPath('data.0.name', 'requirements.pdf');
        $this->assertTrue(ActivityLog::query()->where('description', 'Attached "requirements.pdf" to lead "Rahim Uddin"')->exists());

        // Someone who sees every lead opens it, but may not change the files without Create and Edit.
        Sanctum::actingAs($this->member(['crm.leads.view', 'crm.records.all']));
        $this->getJson("/api/v1/files/{$file}/link")->assertOk();
        $this->post("/api/v1/crm/leads/{$this->lead}/attachments", ['files' => [UploadedFile::fake()->create('more.pdf', 10, 'application/pdf')]], ['Accept' => 'application/json'])->assertForbidden();
        $this->deleteJson("/api/v1/crm/leads/{$this->lead}/attachments/{$file}")->assertForbidden();

        // Someone who sees only their own leads does not reach this one's files.
        Sanctum::actingAs($this->member(['crm.leads.view', 'crm.leads.manage']));
        $this->getJson("/api/v1/files/{$file}/link")->assertNotFound();
        $this->getJson("/api/v1/crm/leads/{$this->lead}/attachments")->assertNotFound();

        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/v1/crm/leads/{$this->lead}/attachments/{$file}")->assertOk()->assertJsonPath('message', 'Attachment removed.');
        $this->getJson("/api/v1/crm/leads/{$this->lead}/attachments")->assertJsonPath('data', []);
    }

    public function test_a_comment_mentions_only_people_who_see_the_lead(): void
    {
        $all  = $this->member(['crm.leads.view', 'crm.records.all']);
        $own  = $this->member(['crm.leads.view', 'crm.leads.manage']);
        $none = $this->member(['contacts.view']);

        $this->assertSame([$all->id], array_column($this->getJson("/api/v1/crm/leads/{$this->lead}/comments/people")->assertOk()->json('data'), 'id'));

        $comment = $this->postJson("/api/v1/crm/leads/{$this->lead}/comments", ['body' => 'Asked for a quote by Friday.', 'mentions' => [$all->id, $own->id, $none->id]])
            ->assertCreated()->json('data');
        $this->assertSame([$all->id], array_column(RecordComment::findOrFail($comment['id'])->mentions, 'id'));

        $notice = UserNotification::query()->where('user_id', $all->id)->sole();
        $this->assertSame(['comments.mentioned', "/crm/leads/{$this->lead}#comments"], [$notice->trigger, $notice->link]);
        $this->assertSame("{$this->user->name} mentioned you on lead \"Rahim Uddin\"", $notice->title);

        // Whoever sees it reads and writes them; someone who does not, finds no lead.
        Sanctum::actingAs($all);
        $this->getJson("/api/v1/crm/leads/{$this->lead}/comments")->assertOk()->assertJsonCount(1, 'data');
        $this->postJson("/api/v1/crm/leads/{$this->lead}/comments", ['body' => 'Sending it now.'])->assertCreated();
        $this->deleteJson("/api/v1/crm/leads/{$this->lead}/comments/{$comment['id']}")->assertForbidden();

        Sanctum::actingAs($own);
        $this->getJson("/api/v1/crm/leads/{$this->lead}/comments")->assertNotFound();

        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/v1/crm/leads/{$this->lead}/comments/{$comment['id']}")->assertOk();
    }

    public function test_a_leads_timeline_reads_its_activity_newest_first(): void
    {
        $this->postJson("/api/v1/crm/leads/{$this->lead}/contact")->assertOk();
        $this->postJson("/api/v1/crm/leads/{$this->lead}/comments", ['body' => 'Called; a visit next week.'])->assertCreated();

        $this->assertSame(
            ['Commented on lead "Rahim Uddin"', 'Marked lead "Rahim Uddin" contacted', 'Added lead "Rahim Uddin"'],
            array_column($this->getJson("/api/v1/crm/leads/{$this->lead}/activity")->assertOk()->json('data'), 'description'),
        );

        // Not for someone who does not see the lead.
        Sanctum::actingAs($this->member(['crm.leads.view']));
        $this->getJson("/api/v1/crm/leads/{$this->lead}/activity")->assertNotFound();
    }
}
