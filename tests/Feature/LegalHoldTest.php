<?php

namespace Tests\Feature;

use App\Models\Admin\AdminAuditLog;
use App\Models\Admin\LegalHold;
use App\Models\Admin\Role;
use App\Models\User\Contact;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Services\Admin\Privacy\LegalHoldService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 15 Phase G2 (section 15.24): "legal holds prevent affected records
 * from being permanently deleted". While a hold is in force on a business,
 * its owner cannot delete it and nobody in it can delete its records;
 * releasing it takes the admin's password and a reason. Saturday 14
 * November 2026.
 */
class LegalHoldTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $admin;

    private User $owner;

    private Workspace $business;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded first, so super-admin holds id 1 and the owner is not taken for an admin.
        $this->seed(RoleSeeder::class);
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'UTC'));

        $this->admin    = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID, 'first_name' => 'Rafi']);
        $this->owner    = User::factory()->create();
        $this->business = $this->businessOf($this->owner);
    }

    private function contact(string $name): Contact
    {
        return $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => $name]);
    }

    private function place(array $fields = [])
    {
        return $this->actingAs($this->admin)->postJson(route('admin.legal-holds.store'), ['business' => $this->business->businessId(), 'reference' => 'Case 2026/118', 'reason' => 'A court asked for the 2026 invoices.', ...$fields]);
    }

    public function test_a_hold_keeps_the_business_and_its_records_from_being_deleted_until_released(): void
    {
        $kept  = $this->contact('Nadia Rahman');
        $spare = $this->contact('Karim Uddin');

        $this->place()->assertOk();
        $hold = LegalHold::sole();
        $this->assertSame(['Main Business', 'Case 2026/118', true], [$hold->business_name, $hold->reference, $hold->isActive()]);

        // In the app: deleting a record, or the business, is refused; reading and the rest go on.
        Sanctum::actingAs($this->owner);
        $this->deleteJson("/api/v1/contacts/{$kept->id}")->assertForbidden()->assertJsonPath('message', LegalHoldService::REFUSAL)->assertJsonPath('legal_hold', true);
        $this->deleteJson("/api/v1/workspaces/{$this->business->id}")->assertForbidden()->assertJsonPath('message', LegalHoldService::REFUSAL);
        $this->getJson('/api/v1/contacts')->assertOk();
        $this->assertSame(2, Contact::count());
        $this->assertNotNull($this->business->fresh());

        // Releasing it is a critical action: the password and a reason.
        $this->actingAs($this->admin)->postJson(route('admin.legal-holds.release', $hold->id), [])->assertJsonValidationErrors(['confirm_password', 'reason']);
        $this->postJson(route('admin.legal-holds.release', $hold->id), ['confirm_password' => 'password', 'reason' => 'The court released the records.'])->assertOk();
        $this->assertFalse($hold->fresh()->isActive());
        $this->postJson(route('admin.legal-holds.release', $hold->id), ['confirm_password' => 'password', 'reason' => 'Again.'])->assertJsonValidationErrors(['hold']);

        Sanctum::actingAs($this->owner);
        $this->deleteJson("/api/v1/contacts/{$spare->id}")->assertOk();

        $this->assertSame(1, AdminAuditLog::where('description', 'like', 'Placed a legal hold on '.$this->business->businessId().'%')->count());
        $this->assertSame(1, AdminAuditLog::where('description', 'Released the legal hold on "Main Business" (Case 2026/118)')->count());
    }

    public function test_the_list_the_business_page_and_who_may_do_what(): void
    {
        $this->place(['business' => 'BW-999999'])->assertJsonValidationErrors(['business' => 'No business has this ID.']);
        $this->place(['reason' => ''])->assertJsonValidationErrors(['reason']);
        $this->place()->assertOk();

        $this->get(route('admin.legal-holds.index'))->assertOk()->assertSee('Main Business')->assertSee('In force')->assertSee('Case 2026/118');
        $this->get(route('admin.legal-holds.index', ['state' => 'released']))->assertDontSee('Case 2026/118');
        $this->get(route('admin.businesses.show', $this->business->id))->assertSee('Legal hold since Nov 14, 2026 (Case 2026/118): nothing of it can be deleted.');

        $compliance = User::factory()->create(['role_id' => Role::where('slug', 'compliance-and-security-administrator')->value('id')]);
        $readOnly   = User::factory()->create(['role_id' => Role::where('slug', 'read-only-administrator')->value('id')]);
        $support    = User::factory()->create(['role_id' => Role::where('slug', 'support-administrator')->value('id')]);

        $this->actingAs($compliance)->get(route('admin.legal-holds.show', LegalHold::sole()->id))->assertOk()->assertSee('Release the Hold');
        $this->actingAs($readOnly)->get(route('admin.legal-holds.index'))->assertOk();
        $this->actingAs($readOnly)->get(route('admin.legal-holds.show', LegalHold::sole()->id))->assertForbidden();
        $this->actingAs($support)->get(route('admin.legal-holds.index'))->assertForbidden();
    }
}
