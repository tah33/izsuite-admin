<?php

namespace Tests\Feature;

use App\Models\Frontend\MenuItem;
use App\Models\User\Contact;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceFile;
use App\Models\User\WorkspaceRole;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use App\Support\Permissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersPurchasesLite;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Access by role (Doc 12 sections 2, 4 and 13) - Doc 12 Phase A: a member
 * opens the business while their membership and role are active, and every
 * route asks for the permission it needs - the kind of sales document it is
 * about, what a file is attached to; the sidebar follows; the owner and a
 * role with full access pass everywhere.
 */
class WorkspaceAccessTest extends TestCase
{
    use OffersPurchasesLite;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $owner;

    private Workspace $business;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $this->offerPurchasesLite();
        $this->owner    = $this->signIn();
        $this->business = $this->businessOf($this->owner);

        app(SetUpNewWorkspace::class)->addRoles($this->business, $this->owner, 'api');

        $this->member = User::factory()->create();
    }

    /** Signs the member in, holding $role - a template's name, or a role made with $permissions. */
    private function join(string $role, array $permissions = [], string $status = 'active'): WorkspaceRole
    {
        $held = $this->business->roles()->where('name', $role)->first()
            ?? $this->business->roles()->create(['name' => $role, 'permissions' => Permissions::expand($permissions)]);

        $this->business->members()->create(['user_id' => $this->member->id, 'role_id' => $held->id, 'status' => $status]);
        Sanctum::actingAs($this->member);

        return $held;
    }

    public function test_a_member_does_what_the_role_allows_and_nothing_else(): void
    {
        $this->join('Sales User');
        $pick = $this->pick($this->business);

        $this->getJson('/api/v1/sales/invoices', $pick)->assertOk();
        $this->getJson('/api/v1/sales/quotations', $pick)->assertOk();
        $this->getJson('/api/v1/tax-rates', $pick)->assertOk();

        // Allowed, so the record is looked for; refused before it is.
        $this->postJson('/api/v1/sales/invoices/999/issue', [], $pick)->assertNotFound();
        $this->postJson('/api/v1/sales/invoices/999/void', [], $pick)->assertForbidden()->assertJsonPath('message', 'Your role does not allow this.');

        $this->getJson('/api/v1/purchases', $pick)->assertForbidden();
        $this->getJson('/api/v1/roles', $pick)->assertForbidden();
        $this->putJson('/api/v1/business-settings/regional', [], $pick)->assertForbidden();

        // Owning no business, the member answers for the one they joined.
        $this->getJson('/api/v1/sales/invoices')->assertOk();
    }

    public function test_the_sidebar_follows_the_role(): void
    {
        MenuItem::create(['section' => 'core', 'label' => 'Contacts', 'route' => '/contacts', 'permission_key' => 'contacts.view', 'sort_order' => 1]);
        MenuItem::create(['section' => 'core', 'label' => 'Reports', 'route' => '/reports', 'permission_key' => 'reports.view', 'sort_order' => 2]);
        MenuItem::create(['section' => 'bottom', 'label' => 'Workspaces', 'route' => '/workspace', 'sort_order' => 3]);
        MenuItem::create(['section' => 'bottom', 'label' => 'Settings', 'route' => '/settings', 'permission_key' => 'settings.view', 'sort_order' => 4]);
        MenuItem::create(['section' => 'apps', 'family_key' => 'sales', 'min_edition' => 'lite', 'label' => 'Invoices', 'route' => '/sales/invoices', 'permission_key' => 'sales.invoices.view', 'sort_order' => 5]);
        MenuItem::create(['section' => 'apps', 'family_key' => 'purchases', 'min_edition' => 'lite', 'label' => 'Purchases', 'route' => '/purchases', 'permission_key' => 'purchases.purchases.view', 'sort_order' => 6]);

        $owner = $this->getJson('/api/v1/navigation')->assertOk()->json('data');

        $this->assertSame([Permissions::ALL], $owner['permissions']);
        $this->assertSame(['sales', 'purchases'], array_column($owner['menu']['apps'], 'family'));

        $role = $this->join('Sales User');
        $nav  = $this->getJson('/api/v1/navigation', $this->pick($this->business))->assertOk()->json('data');

        $this->assertSame($role->permissions, $nav['permissions']);
        $this->assertSame(['Contacts'], array_column($nav['menu']['core'], 'label'));
        $this->assertSame(['Workspaces'], array_column($nav['menu']['bottom'], 'label'));

        // An app the role opens nothing of has no entry at all.
        $this->assertSame(['sales'], array_column($nav['menu']['apps'], 'family'));
    }

    public function test_a_suspended_member_or_a_switched_off_role_loses_access(): void
    {
        $role = $this->join('Sales User', status: 'suspended');

        $this->getJson('/api/v1/sales/invoices', $this->pick($this->business))->assertNotFound();

        $this->business->members()->where('user_id', $this->member->id)->update(['status' => 'active']);
        $role->update(['is_active' => false]);

        $this->getJson('/api/v1/sales/invoices', $this->pick($this->business))->assertForbidden();
        $this->getJson('/api/v1/navigation', $this->pick($this->business))->assertOk()->assertJsonPath('data.permissions', []);
    }

    public function test_a_sales_document_is_asked_for_by_its_kind_and_a_conversion_by_what_it_makes(): void
    {
        $role = $this->join('Quotes Only', ['sales.quotations.create', 'sales.quotations.send']);
        $pick = $this->pick($this->business);

        $this->getJson('/api/v1/sales/quotations', $pick)->assertOk();
        $this->getJson('/api/v1/sales/invoices', $pick)->assertForbidden();
        $this->postJson('/api/v1/sales/quotations/999/convert', ['to' => 'invoice'], $pick)->assertForbidden();

        $role->update(['permissions' => Permissions::expand([...$role->permissions, 'sales.invoices.create'])]);

        $this->postJson('/api/v1/sales/quotations/999/convert', ['to' => 'invoice'], $pick)->assertNotFound();
    }

    public function test_full_access_passes_everywhere(): void
    {
        $this->join('Administrator');

        $this->getJson('/api/v1/roles', $this->pick($this->business))->assertOk();
        $this->getJson('/api/v1/purchases', $this->pick($this->business))->assertOk();
        $this->getJson('/api/v1/activity-log', $this->pick($this->business))->assertOk();
    }

    public function test_a_file_needs_what_its_record_needs_and_an_import_its_kind(): void
    {
        $file = WorkspaceFile::create([
            'workspace_id' => $this->business->id, 'attachable_type' => Contact::class, 'attachable_id' => 1, 'collection' => 'attachments',
            'disk' => 'local', 'path' => 'files/a.pdf', 'visibility' => 'private', 'name' => 'a.pdf', 'mime_type' => 'application/pdf', 'size' => 10,
        ]);

        $this->getJson("/api/v1/files/{$file->id}/link")->assertOk();

        $this->join('Stock Keeper', ['items.view', 'items.import']);
        $pick = $this->pick($this->business);

        $this->getJson("/api/v1/files/{$file->id}/link", $pick)->assertForbidden();
        $this->get('/api/v1/imports/items/template', $pick)->assertOk();
        $this->get('/api/v1/imports/contacts/template', $pick)->assertForbidden();
        $this->postJson('/api/v1/imports', ['kind' => 'contacts'], $pick)->assertForbidden();
        $this->postJson('/api/v1/imports', ['kind' => 'items'], $pick)->assertUnprocessable();
    }
}
