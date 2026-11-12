<?php

namespace Tests\Feature;

use App\Models\Shared\ActivityLog;
use App\Models\User\Contact;
use App\Models\User\Location;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use App\Support\Authors;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersExpensesLite;
use Tests\Concerns\OffersPurchasesLite;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Who and where from (Doc 14 sections 14.5, 14.9 and 14.17), Doc 14 Phase
 * E1. Contacts, items, payment accounts and locations keep who added them
 * and who last changed them. Transactions keep the app that made them. A
 * document's exchange rate keeps its date and where it came from. The
 * account transactions export is logged.
 */
class RecordAuthorsAndOriginsTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));

        $this->offerSalesLite();
        $this->offerPurchasesLite();
        $this->offerExpensesLite();
        $this->user     = $this->signIn(User::factory()->create(['first_name' => 'Nadia', 'last_name' => 'Karim']));
        $this->business = $this->businessOf($this->user);

        app(SetUpNewWorkspace::class)->addRoles($this->business, $this->user, 'api');
        $this->setUpSales();

        $this->dhaka = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka', 'code' => 'DHK', 'is_primary' => true, 'country' => 'BD', 'timezone' => 'Asia/Dhaka']);
    }

    /** A Manager of the business, signed in: the header that picks it. */
    private function manager(): array
    {
        $user = User::factory()->create(['first_name' => 'Salma', 'last_name' => 'Uddin']);

        $this->business->members()->create([
            'user_id' => $user->id, 'role_id' => $this->business->roles()->where('name', 'Manager')->value('id'),
            'name'    => $user->name, 'email' => $user->email, 'location_access' => WorkspaceMember::ALL_LOCATIONS,
        ]);

        Sanctum::actingAs($user);

        return $this->pick($this->business);
    }

    public function test_shared_records_keep_who_added_them_and_who_last_changed_them(): void
    {
        $contact = $this->postJson('/api/v1/contacts', ['contact_type' => 'business', 'role' => 'customer', 'name' => 'Karim Stores'])->assertCreated()->json('data.id');

        $this->getJson("/api/v1/contacts/{$contact}")->assertOk()
            ->assertJsonPath('data.authors.added_by', 'Nadia Karim')
            ->assertJsonPath('data.authors.changed_by', 'Nadia Karim')
            ->assertJsonPath('data.authors.added_at', '2026-10-28T10:00:00+00:00');

        // Lists stay short: no authors in them.
        $this->assertArrayNotHasKey('authors', $this->getJson('/api/v1/contacts')->json('data.0'));

        // Changed later by someone else: only the change is theirs.
        $this->travel(2)->days();
        $manager = $this->manager();
        $this->postJson("/api/v1/contacts/{$contact}/deactivate", [], $manager)->assertOk();

        $this->getJson("/api/v1/contacts/{$contact}", $manager)
            ->assertJsonPath('data.authors.added_by', 'Nadia Karim')
            ->assertJsonPath('data.authors.changed_by', 'Salma Uddin')
            ->assertJsonPath('data.authors.changed_at', '2026-10-30T10:00:00+00:00');

        // Items, payment accounts and locations keep the same.
        $this->getJson("/api/v1/items/{$this->chair->id}", $manager)->assertJsonPath('data.authors.added_by', 'Nadia Karim');
        $account = $this->postJson('/api/v1/payment-accounts', ['type' => 'cash', 'name' => 'Till', 'currency' => 'USD'], $manager)->assertCreated()->json('data.id');
        $this->getJson("/api/v1/payment-accounts/{$account}", $manager)->assertJsonPath('data.authors.added_by', 'Salma Uddin');
        $this->assertSame(['Nadia Karim'], array_column(array_column($this->getJson('/api/v1/locations', $manager)->json('data'), 'authors'), 'added_by'));
    }

    public function test_work_with_nobody_signed_in_is_put_down_to_whom_it_is_done_for(): void
    {
        $contact = Authors::as($this->user->id, function () {
            auth()->forgetGuards();

            return $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'supplier', 'name' => 'Imported Supplier']);
        });

        $this->assertSame($this->user->id, $contact->created_by);
        $this->assertNull(Authors::current(), 'The work done, it is nobody\'s again.');

        // Nobody behind it at all: nothing is made up.
        auth()->forgetGuards();
        $this->assertNull(Contact::create(['workspace_id' => $this->business->id, 'contact_type' => 'business', 'role' => 'supplier', 'name' => 'From Nowhere'])->created_by);
    }

    public function test_transactions_keep_the_app_that_made_them(): void
    {
        $invoice = $this->issuedInvoice(['location_id' => $this->dhaka->id]);
        $payment = $this->pay($invoice, $this->account(), '100.00')->assertCreated()->json('data.id');

        $this->getJson("/api/v1/sales/invoices/{$invoice}")->assertJsonPath('data.made_in', 'Sales Lite')->assertJsonPath('data.made_by', 'Nadia Karim');
        $this->getJson("/api/v1/sales/payments/{$payment}")->assertJsonPath('data.made_in', 'Sales Lite');

        $supplier = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'supplier', 'name' => 'Karim Stores']);
        $purchase = $this->postJson('/api/v1/purchases', [
            'contact_id' => $supplier->id, 'document_date' => '2026-10-28', 'currency' => 'USD', 'location_id' => $this->dhaka->id,
            'lines'      => [['name' => 'Courier', 'quantity' => '1', 'unit_price' => '12.00']],
        ])->assertCreated()->json('data.id');

        $this->getJson("/api/v1/purchases/{$purchase}")->assertJsonPath('data.made_in', 'Purchases Lite')->assertJsonPath('data.made_by', 'Nadia Karim');
    }

    public function test_a_documents_exchange_rate_keeps_its_date_and_where_it_came_from(): void
    {
        $this->business->forceFill(['additional_currencies' => ['EUR']])->save();
        $this->business->exchangeRates()->create(['currency' => 'EUR', 'base_currency' => 'USD', 'rate' => '1.08', 'effective_date' => '2026-10-01']);

        // The business's own rate for its date.
        $ours = $this->draft('invoices', ['location_id' => $this->dhaka->id, 'currency' => 'EUR', 'exchange_rate' => '1.08']);
        $this->getJson("/api/v1/sales/invoices/{$ours}")->assertJsonPath('data.exchange_rate_date', '2026-10-01')->assertJsonPath('data.exchange_rate_source', 'business');

        // One typed in: the document's own date.
        $typed = $this->draft('invoices', ['location_id' => $this->dhaka->id, 'currency' => 'EUR', 'exchange_rate' => '1.10']);
        $this->getJson("/api/v1/sales/invoices/{$typed}")->assertJsonPath('data.exchange_rate_date', '2026-10-28')->assertJsonPath('data.exchange_rate_source', 'transaction');

        // Issued at the business's rate instead: it says so.
        $this->postJson("/api/v1/sales/invoices/{$typed}/issue", ['exchange_rate' => 'current'])->assertOk()
            ->assertJsonPath('data.exchange_rate_date', '2026-10-01')->assertJsonPath('data.exchange_rate_source', 'business');

        // The base currency has neither.
        $base = $this->draft('invoices', ['location_id' => $this->dhaka->id]);
        $this->getJson("/api/v1/sales/invoices/{$base}")->assertJsonPath('data.exchange_rate_date', null)->assertJsonPath('data.exchange_rate_source', null);
    }

    public function test_the_account_transactions_export_is_logged(): void
    {
        $account = $this->account();

        $this->get("/api/v1/payment-accounts/{$account->id}/transactions/export")->assertOk();

        $this->assertSame('Exported 0 transactions of payment account Cash', ActivityLog::where('action', 'exported')->sole()->description);
    }
}
