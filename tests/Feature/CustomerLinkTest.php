<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\UserNotification;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 16 Phase G2: what a customer does through a document's secure link on
 * the full Sales app - accepting or declining a sent quotation, and
 * commenting, each kept with when and where from, shown on the document and
 * told to the business - and links that expire after Settings > Sales's
 * days. Sales Lite's links only show the document. Wednesday 28 October 2026.
 */
class CustomerLinkTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Mail::fake();
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])]);
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);

        $this->offerSalesLite();
        $full = Application::create(['app_key' => 'sales', 'name' => 'Sales', 'family_key' => 'sales', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 10, 'is_active' => true, 'status' => 'available']);
        $full->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        // The owner as a member, so the business's notices reach them.
        app(SetUpNewWorkspace::class)->handle($this->business, $this->user, 'api');
        $this->setUpSales();
    }

    private function full(): void
    {
        $this->postJson('/api/v1/apps/sales/activate')->assertOk();
    }

    /** A sent quotation, or an issued invoice, shared: [its id, its link's token]. The new business has a location, so the documents name it. */
    private function shared(string $kind = 'quotations'): array
    {
        $id = $this->draft($kind, ['location_id' => $this->business->locations()->value('id')]);

        $this->postJson("/api/v1/sales/{$kind}/{$id}/".($kind === 'invoices' ? 'issue' : 'send'))->assertOk();
        $url = $this->postJson("/api/v1/sales/{$kind}/{$id}/link")->assertOk()->json('data.url');

        return [$id, basename($url)];
    }

    public function test_a_customer_accepts_a_quotation_through_its_link_once(): void
    {
        $this->full();
        [$id, $token] = $this->shared();

        $this->get("/d/{$token}")->assertOk()->assertSee('Accept Quotation')->assertSee('Decline Quotation')->assertSee('Send Comment');

        $this->post("/d/{$token}/accept", ['name' => ' Rahim '])
            ->assertRedirect("/d/{$token}")
            ->assertSessionHas('responded', fn (string $message) => str_starts_with($message, 'Thank you - you accepted this quotation, and ') && str_ends_with($message, ' has been told.'));

        $shown = $this->getJson("/api/v1/sales/quotations/{$id}")->assertOk()->json('data');
        $this->assertSame('accepted', $shown['status']);
        $this->assertSame([['accepted', 'Rahim', null, '127.0.0.1']], array_map(fn (array $row) => [$row['kind'], $row['name'], $row['body'], $row['ip_address']], $shown['customer_responses']));

        $this->assertDatabaseHas('activity_logs', ['description' => 'Uddin Traders accepted quotation QUO-00001 through its link (as Rahim)', 'user_id' => null]);
        $this->assertSame(
            ['customers.accepted', 'Uddin Traders accepted quotation QUO-00001', 'Through its link, as Rahim.', "/sales/quotations/{$id}"],
            array_values(UserNotification::where('user_id', $this->user->id)->where('trigger', 'customers.accepted')->sole()->only(['trigger', 'title', 'body', 'link'])),
        );

        // Answered, it shows so - and is not answered again.
        $this->get("/d/{$token}")->assertSee('Accepted by Rahim on 28 Oct 2026')->assertDontSee('Accept Quotation');
        $this->post("/d/{$token}/reject", ['reason' => 'Changed our mind'])->assertSessionHasErrors('response');
        $this->getJson("/api/v1/sales/quotations/{$id}")->assertJsonPath('data.status', 'accepted');
    }

    public function test_a_customer_declines_with_why_and_comments_while_allowed(): void
    {
        $this->full();
        [$quotation, $token] = $this->shared();

        $this->post("/d/{$token}/reject", ['name' => 'Rahim', 'reason' => 'Too dear for now'])->assertRedirect("/d/{$token}");
        $this->getJson("/api/v1/sales/quotations/{$quotation}")->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.status_reason', 'Too dear for now');

        // A comment on an issued invoice, shown back on its page and on the document.
        [$invoice, $invoiceToken] = $this->shared('invoices');

        $this->get("/d/{$invoiceToken}")->assertSee('Send Comment')->assertDontSee('Accept Quotation');
        $this->post("/d/{$invoiceToken}/comment", ['body' => ''])->assertSessionHasErrors(['body' => 'Write your comment first.']);
        $this->post("/d/{$invoiceToken}/comment", ['name' => 'Rahim', 'body' => 'Can we pay in two parts?'])
            ->assertRedirect("/d/{$invoiceToken}")
            ->assertSessionHas('responded', fn (string $message) => str_starts_with($message, 'Your comment was sent to '));

        $this->get("/d/{$invoiceToken}")->assertSee('Can we pay in two parts?');
        $this->getJson("/api/v1/sales/invoices/{$invoice}")->assertJsonPath('data.customer_responses.0.kind', 'comment')->assertJsonPath('data.customer_responses.0.body', 'Can we pay in two parts?');
        $this->assertSame('Can we pay in two parts?', UserNotification::where('trigger', 'customers.comment')->sole()->body);

        // Comments switched off in Settings > Sales: no form, and none taken.
        $this->putJson('/api/v1/business-settings/sales', [
            'payment_terms' => 0, 'quotation_validity' => 30, 'proforma_validity' => 30, 'line_discounts' => true, 'document_discounts' => true, 'link_comments' => false,
        ])->assertOk()->assertJsonPath('data.link_comments', false);

        $this->get("/d/{$invoiceToken}")->assertDontSee('Send Comment');
        $this->post("/d/{$invoiceToken}/comment", ['body' => 'Hello?'])->assertSessionHasErrors('response');
        $this->assertSame(1, $this->business->customerResponses()->where('kind', 'comment')->count());
    }

    public function test_links_expire_after_the_days_set_and_sales_lite_links_only_show(): void
    {
        // Sales Lite: the document, nothing to answer.
        [$id, $token] = $this->shared();

        $this->get("/d/{$token}")->assertOk()->assertDontSee('Accept Quotation')->assertDontSee('Send Comment');
        $this->post("/d/{$token}/accept")->assertSessionHasErrors('response');
        $this->getJson("/api/v1/sales/quotations/{$id}")->assertJsonPath('data.status', 'sent')->assertJsonPath('data.link.expires_at', null);

        // Full Sales, 7 days: a link made now lasts until 4 November.
        $this->full();
        $this->putJson('/api/v1/business-settings/sales', [
            'payment_terms' => 0, 'quotation_validity' => 30, 'proforma_validity' => 30, 'line_discounts' => true, 'document_discounts' => true, 'link_expiry_days' => 7,
        ])->assertOk()->assertJsonPath('data.link_expiry_days', 7);

        [$second, $lasting] = $this->shared();
        $this->getJson("/api/v1/sales/quotations/{$second}")->assertJsonPath('data.link.expires_at', '2026-11-04T10:00:00+00:00');

        $this->travelTo(Carbon::parse('2026-11-05 10:00:00', 'UTC'));

        $this->get("/d/{$lasting}")->assertStatus(410)->assertSee('have expired');
        $this->post("/d/{$lasting}/accept")->assertStatus(410);
        $this->getJson("/api/v1/sales/quotations/{$second}")->assertJsonPath('data.link', null);

        // Shared again, it has a new one.
        $fresh = basename($this->postJson("/api/v1/sales/quotations/{$second}/link")->assertOk()->json('data.url'));
        $this->assertNotSame($lasting, $fresh);
        $this->get("/d/{$fresh}")->assertOk();
    }
}
