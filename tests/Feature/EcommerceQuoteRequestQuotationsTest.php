<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Models\User\CrmLead;
use App\Models\User\EcommerceQuoteRequest;
use App\Models\User\SalesDocument;
use App\Models\User\StorefrontCustomer;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Repositories\User\TaxRateRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsInStoreCustomers;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * A request for a quotation becomes a draft quotation (Doc 19 section 19.30 -
 * Phase H4c), while Settings → eCommerce says so: started as a new quotation
 * is, at the storefront's location, in the base currency, the request its
 * internal notes and its number the customer's reference; each product a
 * line at its item's price, with the tax a new line takes; for the
 * signed-in account's contact, else - for a guest no order knows - none
 * yet. The request keeps it, named to whoever may open it.
 */
class EcommerceQuoteRequestQuotationsTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsInStoreCustomers;
    use SignsIntoBusinesses;

    private const SETTINGS = '/api/v1/business-settings/ecommerce';

    private const REQUESTS = '/api/v1/ecommerce/quote-requests';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo('2026-11-28 10:00:00');
        $this->openStorefront(['Custom Shelf' => ['sale_price' => '90.00'], 'Oak Table' => ['sale_price' => '200.00']]);
        $this->listed('Custom Shelf', ['sales_method' => 'quotation']);
        $this->listed('Oak Table', ['sales_method' => 'quotation']);
        $this->putJson(self::SETTINGS, ['guest_checkout' => true, 'verify_customer_email' => true, 'price_display' => 'with_tax', 'order_prefix' => 'EC-', 'quote_requests_become' => 'quotation'])
            ->assertOk()->assertJsonPath('data.quote_requests_become', 'quotation');
        $this->business->salesSetting()->create(['quotation_validity' => 15, 'notes' => 'Thank you for asking.', 'terms' => 'Prices before delivery.']);
    }

    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    /** Karim's request for 4 Custom Shelves and an Oak Table - a guest's, or signed in by $token. */
    private function ask(?string $token = null)
    {
        return $this->asCustomer($token)->postJson(self::STORE.'/quote-requests', [
            'name' => 'Karim Rahman', 'email' => 'karim@example.test', 'phone' => '+8801700000001', 'company' => 'Rahman Traders',
            'delivery' => ['country' => 'BD', 'city' => 'Dhaka', 'address' => 'House 12, Road 5'],
            'required_on' => '2026-12-10', 'message' => 'Oak, if you have it.',
            'items' => [['product' => 'custom-shelf', 'quantity' => 4], ['product' => 'oak-table', 'quantity' => 1]],
        ]);
    }

    public function test_a_guests_request_becomes_a_draft_quotation_for_the_business_to_price(): void
    {
        $this->ask()->assertCreated();

        $request   = EcommerceQuoteRequest::sole();
        $quotation = SalesDocument::with('lines')->sole();
        $this->assertSame($quotation->id, $request->sales_document_id);
        $this->assertSame(
            [DocumentType::Quotation, 'draft', null, null, $this->store->location_id, '2026-11-28', '2026-12-13', 'USD', 'QR-00001', 'Thank you for asking.', 'Prices before delivery.', null],
            [$quotation->type, $quotation->status->value, $quotation->number, $quotation->contact_id, $quotation->location_id, $quotation->document_date, $quotation->expiry_date, $quotation->currency, $quotation->customer_reference, $quotation->notes, $quotation->terms, $quotation->created_by],
        );
        $this->assertSame(implode("\n", [
            'From the request for a quotation QR-00001 on Rahman Shop:',
            'Karim Rahman, Rahman Traders',
            'karim@example.test, +8801700000001',
            'Delivered to: House 12, Road 5, Dhaka, Bangladesh',
            'Wanted by: 2026-12-10',
            'Message: Oak, if you have it.',
        ]), $quotation->internal_notes);
        $this->assertSame(
            [[$this->items['Custom Shelf']->id, 'Custom Shelf', '4.000', 'Piece', '90.00', null], [$this->items['Oak Table']->id, 'Oak Table', '1.000', 'Piece', '200.00', null]],
            $quotation->lines->map(fn ($line) => [$line->item_id, $line->name, $line->quantity, $line->unit, (string) $line->unit_price, $line->tax_rate_id])->all(),
        );
        $this->assertEquals(560, (float) $quotation->grand_total);
        $this->assertDatabaseHas('activity_logs', ['description' => 'Added a draft quotation for Karim Rahman from the request for a quotation QR-00001 on Rahman Shop']);
        $this->assertDatabaseCount('crm_leads', 0);
        $this->assertDatabaseCount('contacts', 0);

        // Sales has it as any draft, made in eCommerce; the Quote Requests page names it.
        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/sales/quotations/{$quotation->id}")->assertOk()
            ->assertJsonPath('data.status', 'draft')->assertJsonPath('data.customer_reference', 'QR-00001')->assertJsonPath('data.made_in', 'eCommerce');
        $this->getJson(self::REQUESTS)->assertJsonPath('data.0.quotation', ['id' => $quotation->id, 'number' => null])->assertJsonPath('data.0.lead_id', null);
        $this->getJson(self::REQUESTS."/{$request->id}")->assertJsonPath('data.quotation', ['id' => $quotation->id, 'number' => null]);

        // Only to whoever may view quotations.
        Sanctum::actingAs($this->member(['ecommerce.orders.view']));
        $this->getJson(self::REQUESTS)->assertOk()->assertJsonPath('data.0.quotation', null);
        Sanctum::actingAs($this->member(['ecommerce.orders.view', 'sales.quotations.view']));
        $this->getJson(self::REQUESTS."/{$request->id}")->assertJsonPath('data.quotation.id', $quotation->id);

        // Settings → eCommerce choosing nothing more: the request alone.
        Sanctum::actingAs($this->user);
        $this->putJson(self::SETTINGS, ['guest_checkout' => true, 'verify_customer_email' => true, 'price_display' => 'with_tax', 'order_prefix' => 'EC-', 'quote_requests_become' => 'nothing'])->assertOk();
        $this->ask()->assertCreated();
        $this->assertDatabaseCount('sales_documents', 1);
        $this->putJson(self::SETTINGS, ['guest_checkout' => true, 'verify_customer_email' => true, 'price_display' => 'with_tax', 'order_prefix' => 'EC-', 'quote_requests_become' => 'draft'])
            ->assertJsonValidationErrors('quote_requests_become');
    }

    public function test_a_signed_in_customers_is_for_their_accounts_contact(): void
    {
        $token = $this->verifiedCustomer();
        $this->ask($token)->assertCreated();

        $contact = StorefrontCustomer::sole()->contact_id;
        $this->assertNotNull($contact);
        $this->assertSame($contact, SalesDocument::sole()->contact_id);
        $this->assertSame(0, CrmLead::count());
    }

    public function test_each_line_takes_the_tax_a_new_line_would(): void
    {
        $vat = $this->business->taxRates()->create(['name' => 'VAT', 'code' => 'VAT10', 'rate' => '10', 'treatment' => 'standard', 'for_sales' => true, 'effective_from' => '2026-01-01']);
        $gst = $this->business->taxRates()->create(['name' => 'GST', 'code' => 'GST5', 'rate' => '5', 'treatment' => 'standard', 'for_sales' => true, 'effective_from' => '2026-01-01']);
        app(TaxRateRepository::class)->setDefault($this->business, 'sales', $vat);
        $this->business->forceFill(['tax_mode' => Workspace::TAXES_ENABLED, 'price_tax_mode' => 'exclusive'])->save();
        $this->items['Custom Shelf']->update(['sales_tax_id' => $gst->id]);

        $this->ask()->assertCreated();

        // The shelf its own GST; the table, with none, the business's VAT: 360 + 18, and 200 + 20.
        $quotation = SalesDocument::with('lines')->sole();
        $this->assertSame([$gst->id, $vat->id], $quotation->lines->pluck('tax_rate_id')->all());
        $this->assertEquals([560, 38, 598], [(float) $quotation->subtotal, (float) $quotation->tax_total, (float) $quotation->grand_total]);
    }
}
