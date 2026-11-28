<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\CrmLead;
use App\Models\User\EcommerceQuoteRequest;
use App\Models\User\SalesDocument;
use App\Models\User\User;
use App\Models\User\WorkspaceFile;
use App\Models\User\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsInStoreCustomers;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Files with a request for a quotation (Doc 19 section 19.30 - Phase H4d),
 * while Settings → eCommerce lets customers attach them: up to three, as
 * attachments are taken anywhere, within the business's storage - each
 * refusal said as a customer may read it. They stay with the request, for
 * whoever may view orders at its storefront's location to download; the
 * lead or draft quotation it becomes names them.
 */
class EcommerceQuoteRequestFilesTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsInStoreCustomers;
    use SignsIntoBusinesses;

    private const SETTINGS = '/api/v1/business-settings/ecommerce';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('local');
        Storage::fake('public');
        $this->travelTo('2026-11-28 10:00:00');
        $this->openStorefront(['Custom Shelf' => ['sale_price' => '90.00']]);
        $this->listed('Custom Shelf', ['sales_method' => 'quotation']);
    }

    /** Settings → eCommerce as they start, but for $changes. */
    private function settings(array $changes)
    {
        Sanctum::actingAs($this->user);

        return $this->putJson(self::SETTINGS, ['guest_checkout' => true, 'verify_customer_email' => true, 'price_display' => 'with_tax', 'order_prefix' => 'EC-', ...$changes])->assertOk();
    }

    private function member(array $permissions, ?array $locations = null): User
    {
        $user   = User::factory()->create();
        $role   = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $member = $this->business->members()->create([
            'user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id,
            'location_access' => $locations === null ? WorkspaceMember::ALL_LOCATIONS : WorkspaceMember::SELECTED_LOCATIONS,
        ]);
        $member->locations()->sync($locations ?? []);

        return $user;
    }

    private function drawing(): UploadedFile
    {
        return UploadedFile::fake()->create('drawing.pdf', 120, 'application/pdf');
    }

    /** Karim's request for 4 Custom Shelves, with $files. */
    private function ask(array $files)
    {
        return $this->asCustomer(null)->postJson(self::STORE.'/quote-requests', [
            'name' => 'Karim Rahman', 'email' => 'karim@example.test',
            'delivery' => ['country' => 'BD', 'city' => 'Dhaka'], 'message' => 'Oak, if you have it.',
            'items' => [['product' => 'custom-shelf', 'quantity' => 4]],
            ...($files ? ['files' => $files] : []),
        ]);
    }

    public function test_files_come_with_a_request_while_the_store_takes_them(): void
    {
        // Off to start: the store says so, and refuses them.
        $this->asCustomer(null)->getJson(self::STORE)->assertJsonPath('data.quote_files', false);
        $this->ask([$this->drawing()])->assertUnprocessable()->assertJsonPath('errors.files.0', 'This store does not take files with a request.');
        $this->assertDatabaseCount('ecommerce_quote_requests', 0);

        $this->settings(['quote_request_files' => true])->assertJsonPath('data.quote_request_files', true);
        $this->asCustomer(null)->getJson(self::STORE)->assertJsonPath('data.quote_files', true);
        $this->ask([$this->drawing(), UploadedFile::fake()->image('shelf.png', 300, 200)])->assertCreated();

        $request = EcommerceQuoteRequest::sole();
        $files   = WorkspaceFile::query()->where('attachable_type', EcommerceQuoteRequest::class)->where('attachable_id', $request->id)->orderBy('id')->get();
        $this->assertSame([['drawing.pdf', 'attachments', null], ['shelf.png', 'attachments', null]], $files->map(fn (WorkspaceFile $file) => [$file->name, $file->collection->value, $file->uploaded_by])->all());
        $this->assertDatabaseHas('activity_logs', ['description' => 'Karim Rahman asked for a quotation on Rahman Shop: QR-00001, with 2 files']);

        // Its View lists them; whoever may view orders at its location downloads them.
        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/ecommerce/quote-requests/{$request->id}")
            ->assertJsonPath('data.files', $files->map(fn (WorkspaceFile $file) => ['id' => $file->id, 'name' => $file->name, 'size' => (int) $file->size])->all());
        $this->getJson("/api/v1/files/{$files[0]->id}/link")->assertOk()->assertJsonPath('data.name', 'drawing.pdf');

        $elsewhere = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Chattogram', 'code' => 'CTG']);
        Sanctum::actingAs($this->member(['ecommerce.orders.view']));
        $this->getJson("/api/v1/files/{$files[0]->id}/link")->assertOk();
        Sanctum::actingAs($this->member(['ecommerce.store.view']));
        $this->getJson("/api/v1/files/{$files[0]->id}/link")->assertForbidden();
        Sanctum::actingAs($this->member(['ecommerce.orders.view'], [$elsewhere->id]));
        $this->getJson("/api/v1/files/{$files[0]->id}/link")->assertNotFound();
    }

    public function test_up_to_three_of_the_kinds_attachments_take_within_the_storage(): void
    {
        $this->settings(['quote_request_files' => true]);

        $this->ask(array_map(fn () => $this->drawing(), range(1, 4)))->assertUnprocessable()->assertJsonPath('errors.files.0', 'Attach up to 3 files.');
        $this->ask([UploadedFile::fake()->create('setup.exe', 10, 'application/x-msdownload')])->assertUnprocessable()->assertJsonValidationErrors('files.0');

        // No room left: refused without saying how full the business's storage is; without files it goes.
        Package::where('key', 'premium')->update(['storage_limit_mb' => 0]);
        $this->ask([$this->drawing()])->assertUnprocessable()->assertJsonPath('errors.files.0', 'This store cannot take files just now: send your request without them.');
        $this->assertDatabaseCount('ecommerce_quote_requests', 0);
        $this->ask([])->assertCreated();
        $this->assertDatabaseCount('workspace_files', 0);
    }

    public function test_the_lead_or_draft_quotation_it_becomes_names_them(): void
    {
        $this->settings(['quote_request_files' => true, 'quote_requests_become' => 'quotation']);
        $this->ask([$this->drawing()])->assertCreated();
        $this->assertStringEndsWith("Message: Oak, if you have it.\nFiles with the request: drawing.pdf", SalesDocument::sole()->internal_notes);

        $crm = Application::create(['app_key' => 'crm', 'name' => 'CRM', 'family_key' => 'crm', 'edition' => 'full', 'category_id' => Application::first()->category_id, 'sort_order' => 20, 'is_active' => true, 'status' => 'available']);
        $crm->packages()->attach(Package::pluck('id'));
        $this->postJson('/api/v1/apps/crm/activate')->assertOk();
        $this->settings(['quote_request_files' => true, 'quote_requests_become' => 'lead']);
        $this->ask([$this->drawing()])->assertCreated();
        $this->assertSame("Oak, if you have it.\n\nFiles with the request QR-00002: drawing.pdf", CrmLead::sole()->notes);
        $this->assertSame(2, WorkspaceFile::count());
    }
}
