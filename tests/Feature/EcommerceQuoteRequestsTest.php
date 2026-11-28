<?php

namespace Tests\Feature;

use App\Mail\StoreQuoteRequestMail;
use App\Models\User\EcommerceQuoteRequest;
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
 * Requests for a quotation (Doc 19 section 19.30 - Phase H4a): a store's
 * Request a Quote for products sold by quotation - by a guest or a signed-in
 * customer - kept as the business's, numbered, the customer e-mailed it
 * arrived; never an order. The Quote Requests page, under View Orders,
 * lists them at the member's locations, and opens one whole.
 */
class EcommerceQuoteRequestsTest extends TestCase
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
        $this->openStorefront([
            'Custom Shelf' => ['sale_price' => '90.00'],
            'Ceramic Mug'  => ['sale_price' => '8.00'],
            'T-Shirt S'    => ['sale_price' => '10.00'],
            'T-Shirt M'    => ['sale_price' => '10.00'],
        ]);
        $this->listed('Custom Shelf', ['sales_method' => 'quotation']);
        $this->listed('Ceramic Mug');
        $this->listed('T-Shirt S', ['title' => 'Team T-Shirt', 'slug' => 'team-t-shirt', 'sales_method' => 'quotation']);
        $this->putJson('/api/v1/ecommerce/catalog/'.$this->items['T-Shirt S']->id.'/variants', ['options' => [['name' => 'Size', 'values' => ['S', 'M']]], 'variants' => [
            ['item_id' => $this->items['T-Shirt S']->id, 'option_values' => ['S'], 'status' => 'active', 'availability' => 'available'],
            ['item_id' => $this->items['T-Shirt M']->id, 'option_values' => ['M'], 'status' => 'active', 'availability' => 'available'],
        ]])->assertOk();
    }

    /** What the store's page sends: Karim's details, delivered to Dhaka, wanted by 10 December, with $fields. */
    private function fields(array $fields = []): array
    {
        return [
            'name' => 'Karim Rahman', 'email' => 'Karim@Example.test', 'phone' => '+8801700000001', 'company' => 'Rahman Traders',
            'delivery' => ['country' => 'BD', 'city' => 'Dhaka', 'address' => 'House 12, Road 5'],
            'required_on' => '2026-12-10', 'message' => 'Oak, if you have it.',
            'items' => [['product' => 'custom-shelf', 'quantity' => 4]],
            ...$fields,
        ];
    }

    private function submit(array $fields, ?string $customer = null)
    {
        return $this->asCustomer($customer)->postJson(self::STORE.'/quote-requests', $fields);
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

    public function test_a_guest_asks_for_a_quotation_and_is_emailed_it_arrived(): void
    {
        $variant = $this->getJson(self::STORE.'/products/team-t-shirt')->json('data.variants.1.id');

        $this->submit($this->fields(['items' => [['product' => 'custom-shelf', 'quantity' => 4], ['product' => 'team-t-shirt', 'variant_id' => $variant, 'quantity' => 25]]]))
            ->assertCreated()
            ->assertJsonPath('message', 'Your request is sent: we will reply to karim@example.test.')
            ->assertExactJson(['message' => 'Your request is sent: we will reply to karim@example.test.', 'data' => ['number' => 'QR-00001', 'email' => 'karim@example.test']]);

        $quote = EcommerceQuoteRequest::with('items')->sole();
        $this->assertSame(['Karim Rahman', 'karim@example.test', '+8801700000001', 'Rahman Traders', 'BD', 'Dhaka', 'House 12, Road 5', '2026-12-10', 'Oak, if you have it.', null],
            [$quote->customer_name, $quote->customer_email, $quote->customer_phone, $quote->company, $quote->delivery_country, $quote->delivery_city, $quote->delivery_address, $quote->required_on->toDateString(), $quote->message, $quote->storefront_customer_id]);
        $this->assertSame([['Custom Shelf', '4.000'], ['Team T-Shirt (M)', '25.000']], $quote->items->map(fn ($item) => [$item->title, $item->quantity])->all());
        $this->assertSame($this->items['T-Shirt M']->id, $quote->items[1]->item_id);

        Mail::assertSent(StoreQuoteRequestMail::class, function (StoreQuoteRequestMail $mail) {
            $mail->assertSeeInText('We have your request for a quotation, QR-00001.');
            $mail->assertSeeInText('25 × Team T-Shirt (M)');
            $mail->assertSeeInHtml('Oak, if you have it.');

            return $mail->hasTo('karim@example.test') && $mail->hasReplyTo('help@rahman.test') && $mail->mailSubject === 'We have your request for a quotation, QR-00001'
                && $mail->quote['delivery'] === 'House 12, Road 5, Dhaka, Bangladesh';
        });
        $this->assertDatabaseHas('activity_logs', ['description' => 'Karim Rahman asked for a quotation on Rahman Shop: QR-00001']);
        $this->assertDatabaseCount('ecommerce_orders', 0);

        // The next is QR-00002.
        $this->submit($this->fields())->assertCreated()->assertJsonPath('data.number', 'QR-00002');
    }

    public function test_only_a_product_sold_by_quotation_is_asked_for(): void
    {
        $this->submit($this->fields(['items' => [['product' => 'ceramic-mug', 'quantity' => 1]]]))->assertUnprocessable()->assertJsonPath('errors', ['items.0.product' => ['Add it to your cart instead.']]);
        $this->submit($this->fields(['items' => [['product' => 'no-such-thing', 'quantity' => 1]]]))->assertJsonValidationErrors(['items.0.product' => 'This product is not sold in this store.']);
        $this->submit($this->fields(['items' => [['product' => 'team-t-shirt', 'quantity' => 1]]]))->assertJsonValidationErrors(['items.0.variant_id' => 'Choose its options.']);
        $this->submit($this->fields(['items' => [['product' => 'custom-shelf', 'variant_id' => 5, 'quantity' => 1]]]))->assertJsonValidationErrors(['items.0.variant_id' => 'This product has no options to choose.']);
        $this->submit($this->fields(['items' => []]))->assertJsonValidationErrors(['items' => 'Choose a product to ask about.']);
        $this->submit($this->fields(['items' => [['product' => 'custom-shelf', 'quantity' => 0]]]))->assertJsonValidationErrors(['items.0.quantity' => 'Ask for 1 at least.']);
        $this->submit($this->fields(['required_on' => '2026-11-27']))->assertJsonValidationErrors(['required_on' => 'Choose today or a later day.']);
        $this->submit($this->fields(['delivery' => ['country' => 'XX']]))->assertJsonValidationErrors(['delivery.country' => 'Choose the country it is delivered to.']);
        $this->submit($this->fields(['email' => 'karim']))->assertJsonValidationErrors(['email' => 'Give an e-mail we can reply to.']);

        $this->assertDatabaseCount('ecommerce_quote_requests', 0);
        Mail::assertNothingSent();
    }

    public function test_a_signed_in_customer_asks_as_their_account(): void
    {
        $karim = $this->verifiedCustomer();

        $this->submit($this->fields(['name' => 'Someone Else', 'email' => 'someone@example.test', 'phone' => null]), $karim)->assertCreated()
            ->assertJsonPath('data.email', 'karim@example.test');

        $quote = EcommerceQuoteRequest::sole();
        $this->assertSame(['Karim Rahman', 'karim@example.test'], [$quote->customer_name, $quote->customer_email]);
        $this->assertNotNull($quote->storefront_customer_id);
    }

    public function test_the_quote_requests_page_lists_them_and_opens_one_whole(): void
    {
        $this->submit($this->fields())->assertCreated();
        $this->travel(1)->hours();
        $this->submit($this->fields(['name' => 'Nadia Islam', 'email' => 'nadia@example.test', 'required_on' => null, 'message' => null, 'delivery' => ['country' => 'IN']]))->assertCreated();

        Sanctum::actingAs($this->user);
        $this->getJson(self::REQUESTS)->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.number', 'QR-00002')
            ->assertJsonPath('data.0.delivery', 'India')
            ->assertJsonPath('data.0.required_on', null)
            ->assertJsonPath('data.1', [
                'id' => EcommerceQuoteRequest::where('number', 'QR-00001')->value('id'), 'number' => 'QR-00001', 'received_at' => '2026-11-28T10:00:00+00:00', 'storefront' => 'Rahman Shop',
                'customer' => ['name' => 'Karim Rahman', 'email' => 'karim@example.test', 'account' => false],
                'items' => [['title' => 'Custom Shelf', 'quantity' => '4']], 'lines' => 1, 'delivery' => 'Dhaka, Bangladesh', 'required_on' => '2026-12-10',
                // Phase H4b and H4c: no lead, CRM being off, nor a quotation, as Settings → eCommerce does not ask for one.
                'lead_id' => null, 'quotation' => null,
            ]);
        $this->getJson(self::REQUESTS.'?search=nadia')->assertJsonCount(1, 'data');
        $this->getJson(self::REQUESTS.'?search=QR-00001')->assertJsonCount(1, 'data');
        $this->getJson(self::REQUESTS.'?storefront_id='.$this->store->id)->assertJsonCount(2, 'data');

        $id = EcommerceQuoteRequest::where('number', 'QR-00001')->value('id');
        $this->getJson(self::REQUESTS."/{$id}")->assertOk()
            ->assertJsonPath('data.customer', ['name' => 'Karim Rahman', 'email' => 'karim@example.test', 'phone' => '+8801700000001', 'company' => 'Rahman Traders', 'account' => false])
            ->assertJsonPath('data.delivery', 'House 12, Road 5, Dhaka, Bangladesh')
            ->assertJsonPath('data.message', 'Oak, if you have it.');

        // Under View Orders, at the member's locations.
        $elsewhere = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Chattogram', 'code' => 'CTG']);
        Sanctum::actingAs($this->member(['ecommerce.orders.view'], [$elsewhere->id]));
        $this->getJson(self::REQUESTS)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(self::REQUESTS."/{$id}")->assertNotFound();
        Sanctum::actingAs($this->member(['ecommerce.store.view']));
        $this->getJson(self::REQUESTS)->assertForbidden();
    }
}
