<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\SalesDocumentStatus;
use App\Mail\StoreOrderMail;
use App\Models\Frontend\Application;
use App\Models\User\CatalogProduct;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderEmail;
use App\Models\User\SalesDocument;
use App\Models\User\ShippingZone;
use App\Models\User\Storefront;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Repositories\User\TaxRateRepository;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\ChecksOutAStore;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsInStoreCustomers;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Confirming an online order (Doc 19 section 19.20 - Phase F2), under Confirm
 * Orders: what it would do, then its invoice issued as the order has it, its
 * Limited quantities counted down, and the customer e-mailed the invoice and
 * shown it - or what stands in the way.
 */
class EcommerceOrderConfirmTest extends TestCase
{
    use ChecksOutAStore;
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsInStoreCustomers;
    use SignsIntoBusinesses;

    private const ORDERS = '/api/v1/ecommerce/orders';

    private CatalogProduct $mug;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo('2026-11-24 10:00:00');
        $this->openStorefront(['Ceramic Mug' => ['sale_price' => '8.00', 'sku' => 'MUG-1']]);
        $this->mug = $this->listed('Ceramic Mug', ['availability' => CatalogProduct::LIMITED, 'stock_quantity' => '10']);
        $this->setUpCheckout();
    }

    /** An order placed by a guest with $products, its total $total and checkout's $fields - and the business's owner signed in again. */
    private function placed(array $products, string $total, array $fields = []): EcommerceOrder
    {
        $this->place($this->cartWith($products), $this->order($total, $fields))->assertCreated();
        Sanctum::actingAs($this->user);

        return EcommerceOrder::query()->latest('id')->firstOrFail();
    }

    /** A member whose role holds $permissions - at $locations only, when given. */
    private function member(array $permissions, ?array $locations = null): User
    {
        $user   = User::factory()->create();
        $role   = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $member = $this->business->members()->create([
            'user_id'         => $user->id,
            'status'          => 'active',
            'role_id'         => $role->id,
            'location_access' => $locations === null ? WorkspaceMember::ALL_LOCATIONS : WorkspaceMember::SELECTED_LOCATIONS,
        ]);
        $member->locations()->sync($locations ?? []);

        return $user;
    }

    public function test_confirming_issues_its_invoice_as_the_order_has_it_and_counts_its_limited_products_down(): void
    {
        $order   = $this->placed(['ceramic-mug' => 2], '21.00', ['payment_reference' => 'TRX-1001']);
        $contact = $order->contact;

        $this->getJson(self::ORDERS."/{$order->id}/confirmation")->assertOk()
            ->assertJsonPath('data.ready', true)
            ->assertJsonPath('data.problems', [])
            ->assertJsonPath('data.notices', [])
            ->assertJsonPath('data.prices', [])
            ->assertJsonPath('data.stock', [['title' => 'Ceramic Mug', 'left' => '10.000', 'after' => '8.000']])
            ->assertJsonPath('data.invoice', ['date' => '2026-11-24', 'contact' => "Karim Rahman ({$contact->contact_number})", 'total' => '21.00', 'currency' => 'USD'])
            ->assertJsonPath('data.email', 'Karim@Example.test');

        $this->postJson(self::ORDERS."/{$order->id}/confirm")->assertOk()
            ->assertJsonPath('message', 'Order EC-00001 confirmed. Invoice INV-00001 issued.')
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.payment_status', 'pending')
            ->assertJsonPath('data.invoice.number', 'INV-00001')
            ->assertJsonPath('data.confirmed_by', $this->user->name);

        // Its invoice: issued today for its contact, at its location, made by eCommerce - its lines, shipping and totals the order's.
        $invoice = SalesDocument::sole();
        $this->assertSame([DocumentType::Invoice, SalesDocumentStatus::Issued, 'unpaid', $contact->id, $order->location_id, 'USD'], [$invoice->type, $invoice->status, $invoice->payment_status, $invoice->contact_id, $invoice->location_id, $invoice->currency]);
        $this->assertSame(['2026-11-24', '2026-11-24', 'EC-00001'], [substr((string) $invoice->document_date, 0, 10), substr((string) $invoice->due_date, 0, 10), $invoice->customer_reference]);
        $this->assertSame(['21.00', '21.00', '21.00'], [(string) $invoice->subtotal, (string) $invoice->grand_total, (string) $invoice->balance_due]);
        $this->assertSame(Application::where('app_key', 'ecommerce')->value('id'), $invoice->source_app_id);
        $this->assertSame([
            [$this->items['Ceramic Mug']->id, 'Ceramic Mug', '2.000', '8.00', '16.00'],
            [null, 'Shipping: Standard', '1.000', '5.00', '5.00'],
        ], $invoice->lines->map(fn ($line) => [$line->item_id, $line->name, (string) $line->quantity, (string) $line->unit_price, (string) $line->amount])->all());
        $this->assertContains('House 12, Road 5', $invoice->customer_snapshot['billing_address']);
        $this->assertSame($order->id, EcommerceOrder::where('sales_document_id', $invoice->id)->value('id'));
        $this->getJson("/api/v1/sales/invoices/{$invoice->id}")->assertOk()->assertJsonPath('data.number', 'INV-00001');

        // Counted down - and both timelines say so.
        $this->assertSame('8.000', (string) $this->mug->fresh()->stock_quantity);
        $this->getJson(self::ORDERS."/{$order->id}/activity")->assertOk()
            ->assertJsonPath('data.0.description', 'Confirmed online order EC-00001 and issued invoice INV-00001')
            ->assertJsonPath('data.0.by', $this->user->name);
        $this->getJson("/api/v1/sales/invoices/{$invoice->id}/activity")->assertOk()
            ->assertJsonPath('data.0.description', 'Issued invoice INV-00001 for online order EC-00001');

        // Confirmed once: again, it is refused, and nothing more is issued or counted.
        $this->getJson(self::ORDERS."/{$order->id}/confirmation")->assertJsonPath('data.ready', false)->assertJsonPath('data.problems', ['Order EC-00001 is confirmed already.']);
        $this->postJson(self::ORDERS."/{$order->id}/confirm")->assertStatus(409)->assertJsonPath('message', 'Order EC-00001 is confirmed already.');
        $this->assertSame(1, SalesDocument::count());
        $this->assertSame('8.000', (string) $this->mug->fresh()->stock_quantity);
    }

    public function test_the_invoice_keeps_the_order_s_prices_and_taxes_and_a_price_changed_since_is_pointed_out(): void
    {
        $vat = $this->business->taxRates()->create(['name' => 'VAT', 'code' => 'VAT10', 'rate' => '10', 'treatment' => 'standard', 'for_sales' => true, 'effective_from' => '2026-01-01']);
        app(TaxRateRepository::class)->setDefault($this->business, 'sales', $vat);
        $this->business->forceFill(['tax_mode' => Workspace::TAXES_ENABLED, 'price_tax_mode' => 'exclusive'])->save();
        $this->putJson('/api/v1/business-settings/ecommerce/shipping-methods/'.$this->ids['Standard'], ['shipping_zone_id' => ShippingZone::sole()->id, 'name' => 'Standard', 'kind' => 'flat', 'price' => 5, 'tax_rate_id' => $vat->id])->assertOk();
        $order = $this->placed(['ceramic-mug' => 2], '23.10');

        // Since: the mug at 9.00 and the rate at 15% - shown with tax, the mug is 10.35 now; it was 8.80.
        $this->items['Ceramic Mug']->update(['sale_price' => '9.00']);
        $vat->update(['rate' => '15']);

        $this->getJson(self::ORDERS."/{$order->id}/confirmation")->assertOk()
            ->assertJsonPath('data.ready', true)
            ->assertJsonPath('data.prices', [['title' => 'Ceramic Mug', 'ordered' => '8.80', 'now' => '10.35']])
            ->assertJsonPath('data.invoice.total', '23.10');

        $this->postJson(self::ORDERS."/{$order->id}/confirm")->assertOk();

        $invoice = SalesDocument::sole();
        $this->assertSame(['21.00', '2.10', '23.10', '21.00'], [(string) $invoice->subtotal, (string) $invoice->tax_total, (string) $invoice->grand_total, (string) $invoice->net_total]);
        $this->assertSame([['8.00', '10.0000', '1.60'], ['5.00', '10.0000', '0.50']], $invoice->lines->map(fn ($line) => [(string) $line->unit_price, (string) $line->tax_rate, (string) $line->tax_amount])->all());
        $this->assertSame([['name' => 'VAT', 'tax_amount' => '2.10']], array_map(fn (array $tax) => ['name' => $tax['name'], 'tax_amount' => (string) $tax['tax_amount']], $invoice->taxes));
    }

    public function test_an_order_waits_while_a_product_cannot_be_sold_or_its_contact_is_no_customer(): void
    {
        $order  = $this->placed(['ceramic-mug' => 2], '21.00');
        $number = $order->contact->contact_number;

        // Since: one mug left, the item and the contact made inactive.
        $this->mug->update(['stock_quantity' => '1']);
        $this->items['Ceramic Mug']->update(['is_active' => false]);
        $order->contact->update(['is_active' => false]);

        $this->getJson(self::ORDERS."/{$order->id}/confirmation")->assertOk()
            ->assertJsonPath('data.ready', false)
            ->assertJsonPath('data.stock', [])
            ->assertJsonPath('data.problems', [
                'Ceramic Mug is inactive in Items: make it active, then confirm the order.',
                'Only 1 of Ceramic Mug left, and the order has 2: change what is left in the Catalog, then confirm the order.',
                "Karim Rahman ({$number}) is inactive in Contacts: make the contact active, then confirm the order.",
            ]);

        $this->postJson(self::ORDERS."/{$order->id}/confirm")->assertStatus(409)->assertJsonPath('message', 'Ceramic Mug is inactive in Items: make it active, then confirm the order.');
        $this->assertSame([0, EcommerceOrder::PENDING, null, '1.000'], [SalesDocument::count(), $order->fresh()->status, $order->fresh()->sales_document_id, (string) $this->mug->fresh()->stock_quantity]);
        $this->assertSame(1, EcommerceOrderEmail::count());

        // Out of Stock, set by hand, stops nothing - nor does the store closed for a while: the order is the customer's.
        $this->items['Ceramic Mug']->update(['is_active' => true]);
        $order->contact->update(['is_active' => true]);
        $this->mug->update(['availability' => 'out_of_stock']);
        $this->store->update(['status' => Storefront::MAINTENANCE]);

        $this->postJson(self::ORDERS."/{$order->id}/confirm")->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->assertSame('1.000', (string) $this->mug->fresh()->stock_quantity);
    }

    public function test_cash_on_delivery_stays_unpaid_and_a_merged_contact_s_invoice_goes_to_the_one_it_was_merged_into(): void
    {
        $order = $this->placed(['ceramic-mug' => 1], '13.00', ['payment_method_id' => $this->ids['Cash on Delivery']]);
        $kept  = $this->postJson('/api/v1/contacts', ['contact_type' => 'individual', 'role' => 'customer', 'name' => 'Karim R.', 'email' => 'karim@rahman.test'])->assertCreated()->json('data');
        // Merged as ContactMergeService merges: archived, naming the one it went into.
        $order->contact->forceFill(['merged_into_id' => $kept['id'], 'merged_at' => now(), 'is_active' => false])->save();
        $was   = $order->contact->contact_number;

        $this->getJson(self::ORDERS."/{$order->id}/confirmation")->assertOk()
            ->assertJsonPath('data.ready', true)
            ->assertJsonPath('data.notices', ["Karim Rahman ({$was}) was merged into Karim R. ({$kept['contact_number']}) since: the invoice goes to {$kept['contact_number']}."]);

        $this->postJson(self::ORDERS."/{$order->id}/confirm")->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.payment_status', 'unpaid');
        $this->assertSame([$kept['id'], 'unpaid', '13.00'], [SalesDocument::sole()->contact_id, SalesDocument::sole()->payment_status, (string) SalesDocument::sole()->balance_due]);
    }

    public function test_confirming_needs_confirm_orders_at_the_order_s_location(): void
    {
        $order     = $this->placed(['ceramic-mug' => 1], '13.00');
        $elsewhere = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Chattogram', 'code' => 'CTG']);

        $this->assertContains('ecommerce.orders.view', Permissions::expand(['ecommerce.orders.confirm']));
        $this->assertNotContains('ecommerce.orders.confirm', Permissions::forTemplate('view_only', ['sales', 'ecommerce']));
        $this->assertContains('ecommerce.orders.confirm', Permissions::forTemplate('manager', ['sales', 'ecommerce']));

        // View Orders alone: refused.
        Sanctum::actingAs($this->member(['ecommerce.orders.view']));
        $this->getJson(self::ORDERS."/{$order->id}/confirmation")->assertForbidden();
        $this->postJson(self::ORDERS."/{$order->id}/confirm")->assertForbidden();

        // At another location only: not found.
        Sanctum::actingAs($this->member(['ecommerce.orders.confirm'], [$elsewhere->id]));
        $this->getJson(self::ORDERS."/{$order->id}/confirmation")->assertNotFound();
        $this->postJson(self::ORDERS."/{$order->id}/confirm")->assertNotFound();

        // At the store's: confirmed.
        Sanctum::actingAs($this->member(['ecommerce.orders.confirm'], [$this->store->location_id]));
        $this->postJson(self::ORDERS."/{$order->id}/confirm")->assertOk()->assertJsonPath('data.status', 'confirmed');
    }

    public function test_the_customer_is_e_mailed_the_invoice_s_link_and_their_order_s_pages_show_the_invoice(): void
    {
        $token = $this->verifiedCustomer();
        $key   = $this->asCustomer($token)->place($this->cartWith(['ceramic-mug' => 2]), $this->order('21.00'))->assertCreated()->json('data.key');
        $order = EcommerceOrder::sole();
        $this->flushHeaders();
        Sanctum::actingAs($this->user);

        $this->postJson(self::ORDERS."/{$order->id}/confirm")->assertOk();

        // At the API's own address, whoever asks.
        $link  = rtrim((string) config('app.url'), '/').'/d/'.SalesDocument::sole()->activeLink->token;
        $email = EcommerceOrderEmail::where('kind', EcommerceOrderEmail::ORDER_CONFIRMED)->sole();
        $this->assertSame(['karim@example.test', EcommerceOrderEmail::SENT], [$email->to, $email->status]);
        Mail::assertSent(StoreOrderMail::class, function (StoreOrderMail $mail) use ($link) {
            $html = $mail->render();

            return $mail->hasTo('karim@example.test')
                && $mail->mailSubject === 'Your order EC-00001 at Rahman Shop is confirmed'
                && str_contains($html, 'is confirmed. Its invoice is <strong>INV-00001</strong>')
                && str_contains($html, '2 × Ceramic Mug')
                && str_contains($html, 'See your invoice')
                && str_contains($html, $link);
        });
        $this->getJson(self::ORDERS."/{$order->id}")->assertJsonPath('data.emails.1.kind', 'order_confirmed')->assertJsonPath('data.emails.1.status', 'sent');

        // The account's page of the order, and the order's own page: its invoice, by its link.
        $this->asCustomer($token)->getJson(self::STORE."/account/orders/{$order->id}")->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.invoice', ['number' => 'INV-00001', 'url' => $link]);
        $this->asCustomer(null)->getJson(self::STORE."/orders/{$key}")->assertOk()->assertJsonPath('data.invoice.number', 'INV-00001');

        // Asked for by the store's page rendered on the server, from inside (http://api:8000): still the address the customer can open.
        $this->getJson('http://api:8000'.self::STORE."/orders/{$key}")->assertOk()->assertJsonPath('data.invoice.url', $link);

        // Its link revoked: the invoice is shown no more.
        SalesDocument::sole()->links()->update(['revoked_at' => now()]);
        $this->asCustomer($token)->getJson(self::STORE."/account/orders/{$order->id}")->assertOk()->assertJsonPath('data.invoice', null);
    }
}
