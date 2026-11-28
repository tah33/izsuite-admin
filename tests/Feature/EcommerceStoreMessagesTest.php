<?php

namespace Tests\Feature;

use App\Mail\StoreQuoteRequestMail;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\CrmLead;
use App\Models\User\EcommerceQuoteRequest;
use App\Models\User\StorefrontCustomer;
use App\Models\User\User;
use App\Models\User\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsInStoreCustomers;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * A message from a storefront's contact page (Doc 19 section 19.30 - Phase
 * H4e): kept as the business's enquiry, of its own kind - numbered EN-00001,
 * with no products or delivery - its customer e-mailed it arrived and the
 * Activity Log told; a signed-in customer's their account's. The Messages
 * page lists them apart from the requests for a quotation, under View Orders
 * at the member's locations. As Settings → eCommerce makes requests CRM
 * leads, a message is one too - never a draft quotation.
 */
class EcommerceStoreMessagesTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsInStoreCustomers;
    use SignsIntoBusinesses;

    private const REQUESTS = '/api/v1/ecommerce/quote-requests';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo('2026-11-28 10:00:00');
        $this->openStorefront(['Custom Shelf' => ['sale_price' => '90.00']]);
    }

    /** Karim's message from the contact page - a guest's, or signed in by $token. */
    private function write(?string $token = null, array $changes = [])
    {
        return $this->asCustomer($token)->postJson(self::STORE.'/messages', [
            'name' => 'Karim Rahman', 'email' => 'Karim@Example.test', 'phone' => '+8801700000001', 'message' => "Do you deliver to Sylhet?\nThanks.", ...$changes,
        ]);
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

    public function test_a_guest_writes_from_the_contact_page_and_is_emailed_it_arrived(): void
    {
        $this->write(null, ['message' => ' '])->assertUnprocessable()->assertJsonValidationErrors(['message' => 'Write your message.']);

        $this->write()->assertCreated()
            ->assertExactJson(['message' => 'Your message is sent: we will reply to karim@example.test.', 'data' => ['number' => 'EN-00001', 'email' => 'karim@example.test']]);

        $message = EcommerceQuoteRequest::sole();
        $this->assertSame(
            [EcommerceQuoteRequest::KIND_MESSAGE, 'Karim Rahman', 'karim@example.test', '+8801700000001', "Do you deliver to Sylhet?\nThanks.", null, 0],
            [$message->kind, $message->customer_name, $message->customer_email, $message->customer_phone, $message->message, $message->delivery_country, $message->items()->count()],
        );
        Mail::assertSent(StoreQuoteRequestMail::class, function (StoreQuoteRequestMail $mail) {
            $mail->assertSeeInText('We have your message, EN-00001. We will reply to this e-mail.');
            $mail->assertDontSeeInText('You asked about');
            $mail->assertSeeInHtml('Do you deliver to Sylhet?');

            return $mail->hasTo('karim@example.test') && $mail->hasReplyTo('help@rahman.test') && $mail->mailSubject === 'We have your message, EN-00001';
        });
        $this->assertDatabaseHas('activity_logs', ['description' => 'Karim Rahman wrote to Rahman Shop: EN-00001']);

        // Its own numbers; a request for a quotation still QR-00001.
        $this->write()->assertCreated()->assertJsonPath('data.number', 'EN-00002');
        $this->listed('Custom Shelf', ['sales_method' => 'quotation']);
        $this->asCustomer(null)->postJson(self::STORE.'/quote-requests', [
            'name' => 'Nadia Islam', 'email' => 'nadia@example.test', 'delivery' => ['country' => 'BD'], 'items' => [['product' => 'custom-shelf', 'quantity' => 2]],
        ])->assertCreated()->assertJsonPath('data.number', 'QR-00001');
    }

    public function test_the_messages_page_lists_them_apart_from_the_requests(): void
    {
        $this->write()->assertCreated();
        $this->listed('Custom Shelf', ['sales_method' => 'quotation']);
        $this->asCustomer(null)->postJson(self::STORE.'/quote-requests', [
            'name' => 'Nadia Islam', 'email' => 'nadia@example.test', 'delivery' => ['country' => 'BD'], 'items' => [['product' => 'custom-shelf', 'quantity' => 2]],
        ])->assertCreated();
        $id = EcommerceQuoteRequest::where('number', 'EN-00001')->value('id');

        Sanctum::actingAs($this->user);
        $this->getJson(self::REQUESTS.'?kind=message')->assertOk()->assertJsonPath('data', [[
            'id' => $id, 'number' => 'EN-00001', 'received_at' => '2026-11-28T10:00:00+00:00', 'storefront' => 'Rahman Shop',
            'customer' => ['name' => 'Karim Rahman', 'email' => 'karim@example.test', 'account' => false],
            'message' => "Do you deliver to Sylhet?\nThanks.", 'lead_id' => null,
        ]])->assertJsonPath('pagination.total', 1);
        $this->getJson(self::REQUESTS)->assertJsonCount(1, 'data')->assertJsonPath('data.0.number', 'QR-00001');
        $this->getJson(self::REQUESTS.'?kind=message&search=sylhet')->assertJsonCount(0, 'data');
        $this->getJson(self::REQUESTS.'?kind=message&search=karim')->assertJsonCount(1, 'data');
        $this->getJson(self::REQUESTS."/{$id}")->assertOk()
            ->assertJsonPath('data.kind', 'message')->assertJsonPath('data.items', [])->assertJsonPath('data.delivery', '')->assertJsonPath('data.message', "Do you deliver to Sylhet?\nThanks.");
        $this->getJson(self::REQUESTS.'?kind=order')->assertJsonValidationErrors('kind');

        // Under View Orders, at the member's locations.
        $elsewhere = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Chattogram', 'code' => 'CTG']);
        Sanctum::actingAs($this->member(['ecommerce.orders.view'], [$elsewhere->id]));
        $this->getJson(self::REQUESTS.'?kind=message')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(self::REQUESTS."/{$id}")->assertNotFound();
        Sanctum::actingAs($this->member(['ecommerce.store.view']));
        $this->getJson(self::REQUESTS.'?kind=message')->assertForbidden();
    }

    public function test_a_signed_in_customers_is_their_accounts_and_becomes_a_lead_as_requests_do(): void
    {
        $token = $this->verifiedCustomer('karim@example.test', 'Karim A. Rahman');
        $this->write($token, ['name' => 'Someone Else', 'email' => 'else@example.test'])->assertCreated();
        $this->assertSame(['Karim A. Rahman', 'karim@example.test', StorefrontCustomer::sole()->id], [EcommerceQuoteRequest::sole()->customer_name, EcommerceQuoteRequest::sole()->customer_email, EcommerceQuoteRequest::sole()->storefront_customer_id]);

        // With CRM leads chosen and CRM on, a lead too - from Website, the message its notes, no items.
        $crm = Application::create(['app_key' => 'crm', 'name' => 'CRM', 'family_key' => 'crm', 'edition' => 'full', 'category_id' => Application::first()->category_id, 'sort_order' => 20, 'is_active' => true, 'status' => 'available']);
        $crm->packages()->attach(Package::pluck('id'));
        Sanctum::actingAs($this->user);
        $this->postJson('/api/v1/apps/crm/activate')->assertOk();
        $this->write()->assertCreated();

        $lead = CrmLead::with(['interestedItems', 'source'])->sole();
        $this->assertSame(['Karim Rahman', 'Website', 'Rahman Shop: EN-00002', "Do you deliver to Sylhet?\nThanks.", 0], [$lead->name, $lead->source?->name, $lead->source_details, $lead->notes, $lead->interestedItems->count()]);
        $this->assertSame($lead->id, EcommerceQuoteRequest::where('number', 'EN-00002')->value('crm_lead_id'));
        $this->assertDatabaseHas('activity_logs', ['description' => 'Added the lead Karim Rahman from the message EN-00002 on Rahman Shop']);

        // Draft quotations chosen: a message stays a message.
        $this->putJson('/api/v1/business-settings/ecommerce', ['guest_checkout' => true, 'verify_customer_email' => true, 'price_display' => 'with_tax', 'order_prefix' => 'EC-', 'quote_requests_become' => 'quotation'])->assertOk();
        $this->write()->assertCreated();
        $this->assertDatabaseCount('sales_documents', 0);
        $this->assertSame(1, CrmLead::count());
    }
}
