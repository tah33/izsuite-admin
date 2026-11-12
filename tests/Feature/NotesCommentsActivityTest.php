<?php

namespace Tests\Feature;

use App\Models\Shared\UserNotification;
use App\Models\User\Location;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Services\Shared\LocationAccess;
use App\Services\Shared\RecordActivityService;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersExpensesLite;
use Tests\Concerns\OffersPurchasesLite;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Notes, comments and activity (Doc 14 section 14.14), Doc 14 Phase D.
 * Internal notes sit beside the document notes and never print. Comments
 * mention the users who may see the record, who are notified. Each record's
 * page has an activity timeline from the Activity Log, without amounts for
 * a role that may not see the app's values.
 */
class NotesCommentsActivityTest extends TestCase
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
        Mail::fake();
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])]);

        $this->offerSalesLite();
        $this->offerPurchasesLite();
        $this->offerExpensesLite();
        $this->user     = $this->signIn(User::factory()->create(['first_name' => 'Nadia', 'last_name' => 'Karim']));
        $this->business = $this->businessOf($this->user);

        app(SetUpNewWorkspace::class)->addRoles($this->business, $this->user, 'api');
        $this->setUpSales();

        $this->dhaka      = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka', 'code' => 'DHK', 'is_primary' => true, 'country' => 'BD', 'timezone' => 'Asia/Dhaka']);
        $this->chattogram = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Chattogram', 'code' => 'CTG', 'country' => 'BD', 'timezone' => 'Asia/Dhaka']);
    }

    /** A user of the business under role $role, at $locations (null for all) - not signed in. */
    private function memberUser(string $role, ?array $locations = null, string $first = 'Salma'): User
    {
        $user = User::factory()->create(['first_name' => $first, 'last_name' => 'Uddin']);

        $member = $this->business->members()->create([
            'user_id'         => $user->id,
            'role_id'         => $this->business->roles()->where('name', $role)->value('id'),
            'name'            => $user->name,
            'email'           => $user->email,
            'location_access' => $locations === null ? WorkspaceMember::ALL_LOCATIONS : WorkspaceMember::SELECTED_LOCATIONS,
        ]);

        $member->locations()->sync(array_map(fn (Location $location) => $location->id, $locations ?? []));

        return $user;
    }

    /** As $user, the header that picks the business. */
    private function as(User $user): array
    {
        Sanctum::actingAs($user);

        return $this->pick($this->business);
    }

    private function descriptions(string $url, array $headers = []): array
    {
        return array_column($this->getJson($url, $headers)->assertOk()->json('data'), 'description');
    }

    private function sentHtml(): string
    {
        /** @var Request $request */
        [$request] = Http::recorded()->last();

        return (string) collect($request->data())->firstWhere('filename', 'index.html')['contents'];
    }

    public function test_internal_notes_sit_beside_the_notes_and_never_print(): void
    {
        $id = $this->draft('invoices', ['location_id' => $this->dhaka->id, 'notes' => 'Thank you for your order.', 'internal_notes' => 'Asked for a discount next time.']);

        $this->getJson("/api/v1/sales/invoices/{$id}")->assertOk()
            ->assertJsonPath('data.notes', 'Thank you for your order.')
            ->assertJsonPath('data.internal_notes', 'Asked for a discount next time.');

        $this->putJson("/api/v1/sales/invoices/{$id}", $this->invoice(['location_id' => $this->dhaka->id, 'notes' => 'Thank you for your order.', 'internal_notes' => 'Call before delivery.']))
            ->assertOk()->assertJsonPath('data.internal_notes', 'Call before delivery.');

        $this->get("/api/v1/sales/invoices/{$id}/pdf")->assertOk();
        $this->assertStringContainsString('Thank you for your order.', $this->sentHtml());
        $this->assertStringNotContainsString('Call before delivery.', $this->sentHtml());

        // A quotation's internal notes go with it into the invoice made from it.
        $quotation = $this->draft('quotations', ['location_id' => $this->dhaka->id, 'internal_notes' => 'Prefers e-mail.']);
        $this->postJson("/api/v1/sales/quotations/{$quotation}/send")->assertOk();
        $invoice = $this->postJson("/api/v1/sales/quotations/{$quotation}/convert", ['to' => 'invoice'])->assertCreated()->json('data.id');

        $this->getJson("/api/v1/sales/invoices/{$invoice}")->assertJsonPath('data.internal_notes', 'Prefers e-mail.');

        // Purchases and expenses keep their own.
        $supplier = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'supplier', 'name' => 'Karim Stores']);
        $rent     = $this->business->expenseCategories()->create(['name' => 'Rent']);

        $this->postJson('/api/v1/purchases', [
            'contact_id' => $supplier->id, 'document_date' => '2026-10-28', 'currency' => 'USD', 'location_id' => $this->dhaka->id, 'internal_notes' => 'Check the delivery.',
            'lines'      => [['name' => 'Courier', 'quantity' => '1', 'unit_price' => '12.00']],
        ])->assertCreated()->assertJsonPath('data.internal_notes', 'Check the delivery.');

        $this->postJson('/api/v1/expenses', [
            'contact_id' => $supplier->id, 'document_date' => '2026-10-28', 'currency' => 'USD', 'location_id' => $this->dhaka->id, 'internal_notes' => 'Split with the office next door.',
            'lines'      => [['expense_category_id' => $rent->id, 'description' => 'October rent', 'quantity' => '1', 'unit_price' => '500.00']],
        ])->assertCreated()->assertJsonPath('data.internal_notes', 'Split with the office next door.');
    }

    public function test_a_documents_timeline_tells_what_was_done_to_it_and_its_money_newest_first(): void
    {
        $id = $this->issuedInvoice(['location_id' => $this->dhaka->id]);
        $this->pay($id, $this->account(), '100.00')->assertCreated();

        $entries = $this->getJson("/api/v1/sales/invoices/{$id}/activity")->assertOk()->json('data');

        $this->assertSame('Recorded payment RCT-00001 of USD 100.00 for invoice INV-00001', $entries[0]['description']);
        $this->assertSame('Nadia Karim', $entries[0]['by']);
        $this->assertFalse($entries[0]['system']);
        $this->assertStringStartsWith('Added draft invoice', end($entries)['description']);
        $this->assertGreaterThanOrEqual(3, count($entries));

        // A quotation's own timeline says what was made from it.
        $quotation = $this->draft('quotations', ['location_id' => $this->dhaka->id]);
        $this->postJson("/api/v1/sales/quotations/{$quotation}/send")->assertOk();
        $this->postJson("/api/v1/sales/quotations/{$quotation}/convert", ['to' => 'invoice'])->assertCreated();

        $this->assertStringStartsWith('Converted quotation', $this->descriptions("/api/v1/sales/quotations/{$quotation}/activity")[0]);
    }

    public function test_without_the_apps_values_the_timeline_leaves_its_amounts_out(): void
    {
        $id = $this->issuedInvoice(['location_id' => $this->dhaka->id]);
        $this->pay($id, $this->account(), '100.00')->assertCreated();

        $this->business->roles()->create(['name' => 'Invoice Reader', 'permissions' => ['sales.invoices.view']]);
        $reader = $this->as($this->memberUser('Invoice Reader'));

        $entries = $this->getJson("/api/v1/sales/invoices/{$id}/activity", $reader)->assertOk()->json('data');

        $this->assertSame('Recorded payment RCT-00001 for invoice INV-00001', $entries[0]['description']);
        $this->assertSame([], array_merge(...array_column($entries, 'changes')));

        $this->assertSame('Refunded from credit note CN-00001', RecordActivityService::withoutAmounts('Refunded USD 1,250.50 from credit note CN-00001'));
    }

    public function test_a_comment_mentions_and_notifies_only_those_who_may_see_the_record(): void
    {
        $id     = $this->issuedInvoice(['location_id' => $this->dhaka->id]);
        $salma  = $this->memberUser('Sales User');
        $rafi   = $this->memberUser('Expense User', first: 'Rafi');
        $tanvir = $this->memberUser('Manager', [$this->chattogram], 'Tanvir');

        // Rafi may not view invoices, and Tanvir may not use Dhaka.
        $this->assertSame(['Salma Uddin'], array_column($this->getJson("/api/v1/sales/invoices/{$id}/comments/people")->assertOk()->json('data'), 'name'));

        $this->postJson("/api/v1/sales/invoices/{$id}/comments", ['body' => '  @Salma Uddin please call them.  ', 'mentions' => [$salma->id, $rafi->id, $tanvir->id]])
            ->assertCreated()
            ->assertJsonPath('message', 'Comment added.')
            ->assertJsonPath('data.body', '@Salma Uddin please call them.')
            ->assertJsonPath('data.author', 'Nadia Karim')
            ->assertJsonPath('data.mine', true)
            ->assertJsonPath('data.mentions', [['id' => $salma->id, 'name' => 'Salma Uddin']]);

        $notice = UserNotification::where('user_id', $salma->id)->sole();
        $this->assertSame('comments.mentioned', $notice->trigger);
        $this->assertSame('Nadia Karim mentioned you on invoice INV-00001', $notice->title);
        $this->assertSame("/sales/invoices/{$id}#comments", $notice->link);
        $this->assertSame(0, UserNotification::whereIn('user_id', [$rafi->id, $tanvir->id, $this->user->id])->count());

        // Salma reads it, not as her own; the timeline says it was written.
        $this->getJson("/api/v1/sales/invoices/{$id}/comments", $this->as($salma))->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.mine', false);
        $this->assertSame('Commented on invoice INV-00001', $this->descriptions("/api/v1/sales/invoices/{$id}/activity", $this->pick($this->business))[0]);
    }

    public function test_only_its_author_deletes_a_comment(): void
    {
        $id      = $this->issuedInvoice(['location_id' => $this->dhaka->id]);
        $comment = $this->postJson("/api/v1/sales/invoices/{$id}/comments", ['body' => 'Paid by cheque?'])->assertCreated()->json('data.id');
        $salma   = $this->memberUser('Sales User');

        $this->deleteJson("/api/v1/sales/invoices/{$id}/comments/{$comment}", [], $this->as($salma))
            ->assertForbidden()->assertJsonPath('message', 'Only the person who wrote a comment can delete it.');

        $this->deleteJson("/api/v1/sales/invoices/{$id}/comments/{$comment}", [], $this->as($this->user))->assertOk()->assertJsonPath('message', 'Comment deleted.');
        $this->getJson("/api/v1/sales/invoices/{$id}/comments")->assertJsonCount(0, 'data');
        $this->deleteJson("/api/v1/sales/invoices/{$id}/comments/{$comment}")->assertNotFound();

        $this->assertSame('Deleted a comment on invoice INV-00001', $this->descriptions("/api/v1/sales/invoices/{$id}/activity")[0]);
    }

    public function test_comments_and_activity_follow_the_role_the_location_and_the_business(): void
    {
        $id = $this->issuedInvoice(['location_id' => $this->dhaka->id]);

        $this->postJson("/api/v1/sales/invoices/{$id}/comments", ['body' => '   '])->assertJsonValidationErrors(['body' => 'Write your comment first.']);
        $this->getJson("/api/v1/sales/quotations/{$id}/comments")->assertNotFound();

        $expenses = $this->as($this->memberUser('Expense User'));
        $this->getJson("/api/v1/sales/invoices/{$id}/comments", $expenses)->assertForbidden();
        $this->getJson("/api/v1/sales/invoices/{$id}/activity", $expenses)->assertForbidden();

        $elsewhere = $this->as($this->memberUser('Manager', [$this->chattogram], 'Tanvir'));
        $this->getJson("/api/v1/sales/invoices/{$id}/activity", $elsewhere)->assertForbidden()->assertJsonPath('message', LocationAccess::REFUSED);
        $this->postJson("/api/v1/sales/invoices/{$id}/comments", ['body' => 'Hello'], $elsewhere)->assertForbidden();

        // Another business's invoice is not found.
        $other = $this->user->workspaces()->create(['name' => 'Second Business']);
        $this->getJson("/api/v1/sales/invoices/{$id}/comments", [...$this->as($this->user), ...$this->pick($other)])->assertNotFound();
    }

    public function test_purchases_and_expenses_have_comments_and_a_timeline_too(): void
    {
        $supplier = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'supplier', 'name' => 'Karim Stores']);
        $rent     = $this->business->expenseCategories()->create(['name' => 'Rent']);

        $purchase = $this->postJson('/api/v1/purchases', [
            'contact_id' => $supplier->id, 'document_date' => '2026-10-28', 'currency' => 'USD', 'location_id' => $this->dhaka->id,
            'lines'      => [['name' => 'Courier', 'quantity' => '1', 'unit_price' => '12.00']],
        ])->assertCreated()->json('data.id');

        $expense = $this->postJson('/api/v1/expenses', [
            'contact_id' => $supplier->id, 'document_date' => '2026-10-28', 'currency' => 'USD', 'location_id' => $this->dhaka->id,
            'lines'      => [['expense_category_id' => $rent->id, 'description' => 'October rent', 'quantity' => '1', 'unit_price' => '500.00']],
        ])->assertCreated()->json('data.id');

        foreach (["/api/v1/purchases/{$purchase}" => 'draft purchase from "Karim Stores"', "/api/v1/expenses/{$expense}" => 'draft expense'] as $path => $label) {
            $this->postJson("{$path}/comments", ['body' => 'Receipt is in the drawer.'])->assertCreated();
            $this->getJson("{$path}/comments")->assertOk()->assertJsonCount(1, 'data');

            $timeline = $this->descriptions("{$path}/activity");
            $this->assertStringStartsWith("Commented on {$label}", $timeline[0]);
            $this->assertStringStartsWith('Added draft', end($timeline));
        }
    }
}
