<?php

namespace Tests\Feature;

use App\Enums\FileCollection;
use App\Models\User\Location;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Services\Files\FileStorage;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersExpensesLite;
use Tests\Concerns\OffersPurchasesLite;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Global Search in the header (Doc 14 section 16), Doc 14 Phase C. A term
 * finds contacts by name, phone or e-mail, items by name, SKU or barcode,
 * documents by number or reference, and payments by number or reference.
 * Results follow the role, the locations and the business. The header also
 * gets the business's logo (section 3).
 */
class GlobalSearchTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersExpensesLite;
    use OffersPurchasesLite;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private Location $dhaka;

    private Location $chattogram;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));

        $this->offerSalesLite();
        $this->offerPurchasesLite();
        $this->offerExpensesLite();
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);

        app(SetUpNewWorkspace::class)->addRoles($this->business, $this->user, 'api');
        $this->setUpSales();

        $this->dhaka      = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka', 'code' => 'DHK', 'is_primary' => true, 'country' => 'BD', 'timezone' => 'Asia/Dhaka']);
        $this->chattogram = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Chattogram', 'code' => 'CTG', 'country' => 'BD', 'timezone' => 'Asia/Dhaka']);
    }

    /** What searching $term finds, as [type, title] pairs. */
    private function found(string $term, array $headers = []): array
    {
        return array_map(fn (array $row) => [$row['type'], $row['title']], $this->getJson('/api/v1/search?q='.urlencode($term), $headers)->assertOk()->json('data'));
    }

    /** A member under template $role with $locations chosen (null for All Locations), signed in - the header that picks the business. */
    private function member(string $role, ?array $locations = null): array
    {
        $user   = User::factory()->create();
        $member = $this->business->members()->create([
            'user_id'         => $user->id,
            'role_id'         => $this->business->roles()->where('name', $role)->value('id'),
            'name'            => $user->name,
            'email'           => $user->email,
            'location_access' => $locations === null ? WorkspaceMember::ALL_LOCATIONS : WorkspaceMember::SELECTED_LOCATIONS,
        ]);

        $member->locations()->sync(array_map(fn (Location $location) => $location->id, $locations ?? []));
        Sanctum::actingAs($user);

        return $this->pick($this->business);
    }

    public function test_contacts_are_found_by_name_phone_or_email_and_items_by_name_sku_or_barcode(): void
    {
        $this->customer->forceFill(['phone' => '+880 1711-000111', 'email' => 'orders@uddin.test'])->save();
        $this->chair->forceFill(['sku' => 'CHR-01', 'barcode' => '4006381333931'])->save();

        $contact = $this->getJson('/api/v1/search?q=Uddin')->assertOk()->assertJsonCount(1, 'data')->json('data.0');

        $this->assertSame([
            'group' => 'contacts', 'type' => 'Contact', 'title' => 'Uddin Traders', 'subtitle' => 'orders@uddin.test', 'status' => 'active',
            'date' => '2026-10-28', 'location' => null, 'app' => 'Contacts', 'archived' => false, 'link' => "/contacts/{$this->customer->id}",
        ], $contact);

        // The phone however it is typed, its digits alone compared.
        $this->assertSame([['Contact', 'Uddin Traders']], $this->found('1711 000 111'));
        $this->assertSame([['Contact', 'Uddin Traders']], $this->found('orders@uddin'));

        foreach (['Office', 'chr-01', '4006381333931'] as $term) {
            $this->assertSame([['Item', 'Office Chair']], $this->found($term), $term);
        }

        $this->getJson('/api/v1/search?q=CHR')->assertJsonPath('data.0.link', "/items/{$this->chair->id}")->assertJsonPath('data.0.subtitle', 'SKU CHR-01');
    }

    public function test_documents_and_payments_are_found_by_number_or_reference(): void
    {
        $invoice = $this->issuedInvoice(['location_id' => $this->dhaka->id, 'sales_reference' => 'PO-7788']);
        $payment = $this->pay($invoice, $this->account(), '100.00', ['reference' => 'TXN-4455'])->assertCreated()->json('data.id');

        foreach (['INV-00001', 'po-7788'] as $term) {
            $this->getJson('/api/v1/search?q='.$term)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0', [
                'group' => 'sales', 'type' => 'Invoice', 'title' => 'INV-00001', 'subtitle' => 'Uddin Traders', 'status' => 'issued',
                'date' => '2026-10-28', 'location' => 'Dhaka', 'app' => 'Sales Lite', 'archived' => false, 'link' => "/sales/invoices/{$invoice}",
            ]);
        }

        $this->getJson('/api/v1/search?q=TXN-4455')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.group', 'payments')
            ->assertJsonPath('data.0.type', 'Customer Payment')
            ->assertJsonPath('data.0.status', 'completed')
            ->assertJsonPath('data.0.location', 'Dhaka')
            ->assertJsonPath('data.0.link', "/sales/payments/{$payment}");

        // A purchase and an expense, drafts still: by their references.
        $supplier = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'supplier', 'name' => 'Karim Stores']);
        $rent     = $this->business->expenseCategories()->create(['name' => 'Rent']);

        $purchase = $this->postJson('/api/v1/purchases', [
            'contact_id' => $supplier->id, 'document_date' => '2026-10-27', 'currency' => 'USD', 'supplier_invoice_number' => 'KS-1001', 'location_id' => $this->chattogram->id,
            'lines'      => [['name' => 'Courier', 'quantity' => '1', 'unit_price' => '12.00']],
        ])->assertCreated()->json('data.id');

        $expense = $this->postJson('/api/v1/expenses', [
            'contact_id' => $supplier->id, 'document_date' => '2026-10-26', 'currency' => 'USD', 'receipt_number' => 'R-1001', 'location_id' => $this->dhaka->id,
            'lines'      => [['expense_category_id' => $rent->id, 'description' => 'October rent', 'quantity' => '1', 'unit_price' => '500.00']],
        ])->assertCreated()->json('data.id');

        $this->getJson('/api/v1/search?q=KS-1001')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'Purchase')->assertJsonPath('data.0.title', 'Draft')->assertJsonPath('data.0.status', 'draft')
            ->assertJsonPath('data.0.subtitle', 'Karim Stores')->assertJsonPath('data.0.location', 'Chattogram')
            ->assertJsonPath('data.0.app', 'Purchases Lite')->assertJsonPath('data.0.link', "/purchases/{$purchase}");

        $this->getJson('/api/v1/search?q=R-1001')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'Expense')->assertJsonPath('data.0.app', 'Expenses Lite')->assertJsonPath('data.0.link', "/expenses/{$expense}");
    }

    public function test_archived_contacts_and_items_are_marked_and_come_after_active_ones(): void
    {
        $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin & Sons', 'is_active' => false]);
        $this->chair->forceFill(['is_active' => false])->save();

        $rows = $this->getJson('/api/v1/search?q=Uddin')->assertOk()->json('data');

        $this->assertSame(['Uddin Traders', 'Uddin & Sons'], array_column($rows, 'title'));
        $this->assertSame([false, true], array_column($rows, 'archived'));
        $this->assertSame(['active', 'archived'], array_column($rows, 'status'));

        $this->getJson('/api/v1/search?q=Chair')->assertJsonPath('data.0.archived', true);
    }

    public function test_a_member_finds_only_the_kinds_their_role_may_view(): void
    {
        $this->issuedInvoice(['location_id' => $this->dhaka->id, 'sales_reference' => 'REF-1']);
        $this->draft('quotations', ['location_id' => $this->dhaka->id, 'sales_reference' => 'REF-2']);

        // Same day: the newest made first.
        $this->assertSame([['Quotation', 'Draft'], ['Invoice', 'INV-00001']], $this->found('REF-'));

        // Finance User: invoices, no quotations, no items.
        $finance = $this->member('Finance User');

        $this->assertSame([['Invoice', 'INV-00001']], $this->found('REF-', $finance));
        $this->assertSame([], $this->found('Office Chair', $finance));
        $this->assertSame([['Contact', 'Uddin Traders']], $this->found('Uddin', $finance));

        // Expense User: no sales at all.
        $this->assertSame([], $this->found('REF-', $this->member('Expense User')));
    }

    public function test_a_member_finds_transactions_only_at_their_locations(): void
    {
        $ours   = $this->issuedInvoice(['location_id' => $this->dhaka->id, 'sales_reference' => 'SHARED']);
        $theirs = $this->issuedInvoice(['location_id' => $this->chattogram->id, 'sales_reference' => 'SHARED']);

        $this->pay($ours, $this->account(), '10.00', ['reference' => 'MONEY-1'])->assertCreated();
        $this->pay($theirs, $this->account(), '10.00', ['reference' => 'MONEY-2'])->assertCreated();

        $this->assertCount(2, $this->found('SHARED'));

        $dhaka = $this->member('Manager', [$this->dhaka]);

        $this->assertSame(["/sales/invoices/{$ours}"], array_column($this->getJson('/api/v1/search?q=SHARED', $dhaka)->json('data'), 'link'));
        $this->assertSame(['Dhaka'], array_column($this->getJson('/api/v1/search?q=MONEY-', $dhaka)->json('data'), 'location'));

        // Contacts and items belong to the whole business.
        $this->assertSame([['Contact', 'Uddin Traders']], $this->found('Uddin', $dhaka));
    }

    public function test_only_the_picked_business_is_searched(): void
    {
        $other = $this->user->workspaces()->create(['name' => 'Second Business']);
        $other->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin Exports']);

        $this->assertSame([['Contact', 'Uddin Traders']], $this->found('Uddin'));
        $this->assertSame([['Contact', 'Uddin Exports']], $this->found('Uddin', $this->pick($other)));

        // Someone else's business is never searched.
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/search?q=Uddin', $this->pick($this->business))->assertNotFound();
    }

    public function test_a_search_needs_two_characters(): void
    {
        $this->getJson('/api/v1/search?q=%20a%20')->assertUnprocessable()->assertJsonValidationErrors(['q' => 'Type at least 2 characters to search.']);
        $this->getJson('/api/v1/search')->assertUnprocessable()->assertJsonValidationErrors('q');
    }

    public function test_the_header_has_the_business_logo(): void
    {
        Storage::fake('public');

        $this->getJson('/api/v1/navigation')->assertOk()->assertJsonPath('data.workspace.logo_url', null);

        app(FileStorage::class)->store($this->business, $this->business, FileCollection::Logo, [UploadedFile::fake()->image('logo.png', 200, 200)], 'logo');

        $this->assertNotNull($url = $this->getJson('/api/v1/navigation')->json('data.workspace.logo_url'));
        $this->assertStringContainsString('/storage/', $url);
    }
}
