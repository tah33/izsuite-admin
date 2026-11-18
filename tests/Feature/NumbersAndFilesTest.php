<?php

namespace Tests\Feature;

use App\Models\Admin\Role;
use App\Models\Shared\ActivityLog;
use App\Models\User\Contact;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceFile;
use App\Models\User\WorkspaceMember;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersExpensesLite;
use Tests\Concerns\OffersPurchasesLite;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 14 Phase E2. A document's number typed by hand, by a role that may and
 * only when it is free (Doc 14 section 14.6). What a business may attach,
 * set in the admin. A settled record's files removed only by a role that may
 * undo the record, and logged with its status (section 14.12).
 */
class NumbersAndFilesTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersExpensesLite;
    use OffersPurchasesLite;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private Contact $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded first, so super-admin holds id 1 and the owner is not taken for an admin.
        $this->seed(RoleSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));

        Storage::fake('local');
        Storage::fake('public');

        $this->offerSalesLite();
        $this->offerPurchasesLite();
        $this->offerExpensesLite();
        $this->user     = $this->signIn(User::factory()->create(['first_name' => 'Nadia', 'last_name' => 'Karim']));
        $this->business = $this->businessOf($this->user);

        app(SetUpNewWorkspace::class)->addRoles($this->business, $this->user, 'api');
        $this->setUpSales();

        $this->supplier = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'supplier', 'name' => 'Karim Stores']);
    }

    /** A member of the business with the role named $role, signed in: the header that picks it. */
    private function member(string $role): array
    {
        $user = User::factory()->create();

        $this->business->members()->create([
            'user_id' => $user->id, 'role_id' => $this->business->roles()->where('name', $role)->value('id'),
            'name'    => $user->name, 'email' => $user->email, 'location_access' => WorkspaceMember::ALL_LOCATIONS,
        ]);

        Sanctum::actingAs($user);

        return $this->pick($this->business);
    }

    /** A draft purchase from Karim Stores against their invoice $invoice - its id. */
    private function purchase(string $invoice): int
    {
        return $this->postJson('/api/v1/purchases', [
            'contact_id' => $this->supplier->id, 'document_date' => '2026-10-28', 'currency' => 'USD', 'supplier_invoice_number' => $invoice,
            'lines'      => [['name' => 'Courier', 'quantity' => '1', 'unit_price' => '12.00']],
        ])->assertCreated()->json('data.id');
    }

    /** A PDF attached to the record at $path ("sales/invoices/4") - the file's id. */
    private function attach(string $path): int
    {
        return $this->upload($path, UploadedFile::fake()->create('signed.pdf', 20, 'application/pdf'))->assertCreated()->json('data.0.id');
    }

    private function upload(string $path, UploadedFile $file): TestResponse
    {
        return $this->post("/api/v1/{$path}/attachments", ['files' => [$file]], ['Accept' => 'application/json']);
    }

    /* ---------------------------------------------------------- numbers typed by hand */

    public function test_a_number_typed_by_hand_is_put_on_when_it_is_free(): void
    {
        $first = $this->draft('invoices');
        $this->postJson("/api/v1/sales/invoices/{$first}/issue", ['number' => ' INV-00002 '])->assertOk()->assertJsonPath('data.number', 'INV-00002');

        // Taken: another is asked for, and the draft stays a draft.
        $second = $this->draft('invoices');
        $this->postJson("/api/v1/sales/invoices/{$second}/issue", ['number' => 'INV-00002'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['number' => 'INV-00002 is on another invoice already. Choose another number.']);
        $this->getJson("/api/v1/sales/invoices/{$second}")->assertJsonPath('data.status', 'draft')->assertJsonPath('data.number', null);

        // Left empty: the next number, which steps over the one typed.
        $this->postJson("/api/v1/sales/invoices/{$second}/issue")->assertOk()->assertJsonPath('data.number', 'INV-00001');
        $this->postJson('/api/v1/sales/invoices/'.$this->draft('invoices').'/issue')->assertOk()->assertJsonPath('data.number', 'INV-00003');

        // Letters, digits and a few marks only, and not too long.
        $third = $this->draft('invoices');
        $this->postJson("/api/v1/sales/invoices/{$third}/issue", ['number' => 'INV #4'])->assertUnprocessable()->assertJsonValidationErrors('number');
        $this->postJson("/api/v1/sales/invoices/{$third}/issue", ['number' => str_repeat('9', 41)])->assertUnprocessable()->assertJsonValidationErrors('number');

        // A credit note's too.
        $note = $this->postJson("/api/v1/sales/invoices/{$first}/credit-notes", [
            'document_date' => '2026-10-28', 'reason' => 'Returned',
            'lines'         => [['source_line_id' => $this->lineOf($first, 0), 'quantity' => '1', 'unit_price' => '150']],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/sales/credit-notes/{$note}/issue", ['number' => 'CN-RET/1'])->assertOk()->assertJsonPath('data.number', 'CN-RET/1');
    }

    public function test_only_a_role_that_may_types_a_number(): void
    {
        $draft = $this->draft('invoices');
        $sales = $this->member('Sales User');

        $this->postJson("/api/v1/sales/invoices/{$draft}/issue", ['number' => 'INV-77'], $sales)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['number' => 'Your role does not allow typing a number. Leave it empty for the next one.']);

        $this->postJson("/api/v1/sales/invoices/{$draft}/issue", [], $sales)->assertOk()->assertJsonPath('data.number', 'INV-00001');
    }

    public function test_purchases_and_expenses_take_a_number_typed_by_hand_when_recorded(): void
    {
        $first = $this->purchase('KS-1');
        $this->postJson("/api/v1/purchases/{$first}/record", ['number' => 'PO-2026-7'])->assertOk()->assertJsonPath('data.number', 'PO-2026-7');

        $second = $this->purchase('KS-2');
        $this->postJson("/api/v1/purchases/{$second}/record", ['number' => 'PO-2026-7'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['number' => 'PO-2026-7 is on another purchase already. Choose another number.']);
        $this->postJson("/api/v1/purchases/{$second}/record")->assertOk()->assertJsonPath('data.number', 'PUR-00001');

        $rent    = $this->business->expenseCategories()->create(['name' => 'Rent']);
        $expense = $this->postJson('/api/v1/expenses', [
            'contact_id' => $this->supplier->id, 'document_date' => '2026-10-28', 'currency' => 'USD',
            'lines'      => [['expense_category_id' => $rent->id, 'description' => 'October rent', 'quantity' => '1', 'unit_price' => '500.00']],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/expenses/{$expense}/record", ['number' => 'RENT-10'])->assertOk()->assertJsonPath('data.number', 'RENT-10');
    }

    /* ---------------------------------------------------------- what may be attached */

    public function test_the_admin_sets_what_a_business_may_attach(): void
    {
        $admin = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID, 'first_name' => 'Admin']);

        $this->actingAs($admin)->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('data-tab="files"', false)
            ->assertSee('settings[files_attachment_types]', false)
            ->assertSee('settings[files_attachment_max_mb]', false);

        // Only types izSuite takes, and up to 50 MB.
        $this->actingAs($admin)->put(route('admin.settings.update'), ['_group' => 'files', 'settings' => ['files_attachment_types' => 'pdf, exe', 'files_attachment_max_mb' => '51']])
            ->assertSessionHasErrors(['settings.files_attachment_types', 'settings.files_attachment_max_mb']);
        $this->actingAs($admin)->put(route('admin.settings.update'), ['_group' => 'files', 'settings' => ['files_attachment_types' => ' , ', 'files_attachment_max_mb' => '5']])
            ->assertSessionHasErrors(['settings.files_attachment_types' => 'Choose at least one file type.']);

        $this->actingAs($admin)->put(route('admin.settings.update'), ['_group' => 'files', 'settings' => ['files_attachment_types' => 'PDF, .png', 'files_attachment_max_mb' => '2']])
            ->assertSessionHasNoErrors();

        // A business's pages are told them...
        Sanctum::actingAs($this->user);
        $this->getJson('/api/v1/files/limits')
            ->assertOk()
            ->assertExactJson(['data' => ['attachments' => ['extensions' => ['pdf', 'png'], 'max_bytes' => 2 * 1024 * 1024]]]);

        // ...and its uploads follow them.
        $contact = "contacts/{$this->supplier->id}";
        $this->upload($contact, UploadedFile::fake()->create('photo.jpg', 10, 'image/jpeg'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['files.0' => 'Attach PDF or PNG files.']);
        $this->upload($contact, UploadedFile::fake()->create('big.pdf', 2 * 1024 + 1, 'application/pdf'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['files.0' => 'Files can be up to 2 MB.']);
        $this->upload($contact, UploadedFile::fake()->create('price-list.pdf', 2 * 1024, 'application/pdf'))->assertCreated();
    }

    /* ---------------------------------------------------------- removing a settled record's files */

    public function test_a_settled_records_files_are_removed_only_by_a_role_that_may_undo_it(): void
    {
        $draft     = $this->draft('invoices');
        $issued    = $this->issuedInvoice();
        $payment   = $this->pay($issued, $this->account(), '100.00')->assertCreated()->json('data.id');
        $onDraft   = $this->attach("sales/invoices/{$draft}");
        $onIssued  = $this->attach("sales/invoices/{$issued}");
        $onPayment = $this->attach("sales/payments/{$payment}");

        $sales = $this->member('Sales User');

        // A draft's go with changing it.
        $this->deleteJson("/api/v1/sales/invoices/{$draft}/attachments/{$onDraft}", [], $sales)->assertOk();

        // An issued invoice's need voiding it, a payment's reversing it.
        $this->deleteJson("/api/v1/sales/invoices/{$issued}/attachments/{$onIssued}", [], $sales)
            ->assertForbidden()
            ->assertJsonPath('message', 'This invoice is issued. Removing its files needs the permission to void it.');
        $this->deleteJson("/api/v1/sales/payments/{$payment}/attachments/{$onPayment}", [], $sales)
            ->assertForbidden()
            ->assertJsonPath('message', 'This payment is completed. Removing its files needs the permission to reverse it.');
        $this->assertEqualsCanonicalizing([$onIssued, $onPayment], WorkspaceFile::pluck('id')->all());

        // A role that may: logged with the status it had.
        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/v1/sales/invoices/{$issued}/attachments/{$onIssued}")->assertOk();

        $log = ActivityLog::where('action', 'deleted')->latest('id')->first();
        $this->assertSame('Removed attachment "signed.pdf" from invoice INV-00001 (issued)', $log->description);
        $this->assertSame('issued', $log->properties['status']);

        // A recorded purchase's need voiding it.
        $purchase = $this->purchase('KS-9');
        $this->postJson("/api/v1/purchases/{$purchase}/record")->assertOk();
        $onPurchase = $this->attach("purchases/{$purchase}");

        $this->deleteJson("/api/v1/purchases/{$purchase}/attachments/{$onPurchase}", [], $this->member('Purchase User'))
            ->assertForbidden()
            ->assertJsonPath('message', 'This purchase is recorded. Removing its files needs the permission to void it.');
    }
}
