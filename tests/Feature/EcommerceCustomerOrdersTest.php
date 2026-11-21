<?php

namespace Tests\Feature;

use App\Models\Shared\ActivityLog;
use App\Models\User\Contact;
use App\Models\User\EcommerceOrder;
use App\Models\User\StorefrontCustomer;
use App\Models\User\StorefrontCustomerAddress;
use App\Repositories\User\ContactRepository;
use App\Repositories\User\EcommerceSettingRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ChecksOutAStore;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsInStoreCustomers;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 19 Phase E2: a customer account's contact, orders and addresses
 * (sections 19.16 and 19.17) - linked once verified, its e-mail's guest
 * orders its own; checkout signed in; its orders and saved addresses.
 */
class EcommerceCustomerOrdersTest extends TestCase
{
    use ChecksOutAStore;
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsInStoreCustomers;
    use SignsIntoBusinesses;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->openStorefront(['Ceramic Mug' => ['sale_price' => '8.00']]);
        $this->listed('Ceramic Mug');
        $this->setUpCheckout();
    }

    public function test_verified_an_account_takes_its_e_mail_s_guest_orders_and_their_contact(): void
    {
        $this->asCustomer(null);
        $this->place($this->cartWith(['ceramic-mug' => 2]), $this->order('21.00'))->assertCreated();
        $guestContact = EcommerceOrder::sole()->contact_id;

        // Signed up, not yet verified: none of it is the account's.
        $this->signUpCustomer();
        $this->assertNull(EcommerceOrder::sole()->storefront_customer_id);

        $token   = $this->asCustomer(null)->postJson(self::STORE.'/account/verify', ['email' => 'karim@example.test', 'code' => $this->codeSentTo('karim@example.test')])->json('data.token');
        $account = StorefrontCustomer::sole();
        $this->assertSame($guestContact, $account->contact_id);
        $this->assertSame($account->id, EcommerceOrder::sole()->storefront_customer_id);
        $this->assertSame('Linked the store account of Karim Rahman (karim@example.test) to the contact Karim Rahman', ActivityLog::where('model_type', Contact::class)->latest('id')->first()->description);

        $id = EcommerceOrder::sole()->id;
        $this->asCustomer($token)->getJson(self::STORE.'/account/orders')->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertExactJson([
                'data'       => [['id' => $id, 'number' => 'EC-00001', 'placed_at' => EcommerceOrder::sole()->placed_at->toIso8601String(), 'status' => 'pending', 'payment_status' => 'unpaid', 'fulfilment_status' => 'unfulfilled', 'currency' => 'USD', 'grand_total' => '21.00', 'products' => 2]],
                'pagination' => ['current_page' => 1, 'last_page' => 1, 'per_page' => 20, 'total' => 1, 'from' => 1, 'to' => 1, 'has_prev_page' => false, 'has_next_page' => false],
            ]);
        $this->asCustomer($token)->getJson(self::STORE."/account/orders/{$id}")->assertOk()
            ->assertJsonPath('data.number', 'EC-00001')->assertJsonPath('data.key', null)->assertJsonPath('data.lines.0.title', 'Ceramic Mug');

        // Another account sees none of it.
        $other = $this->verifiedCustomer('nadia@example.test', 'Nadia Islam');
        $this->asCustomer($other)->getJson(self::STORE.'/account/orders')->assertOk()->assertJsonPath('data', []);
        $this->asCustomer($other)->getJson(self::STORE."/account/orders/{$id}")->assertNotFound()->assertJsonPath('message', 'There is no such order in your account.');
        $this->asCustomer(null)->getJson(self::STORE.'/account/orders')->assertUnauthorized();
    }

    public function test_without_guest_orders_an_account_takes_the_business_s_one_customer_with_its_e_mail_else_a_new_one(): void
    {
        $contacts = app(ContactRepository::class);
        $nadia    = $contacts->create($this->business, ['contact_type' => 'individual', 'role' => 'both', 'name' => 'Nadia Islam', 'email' => ' Nadia@Example.test ', 'is_active' => true]);
        foreach (['Rumana A.', 'Rumana Akter'] as $name) {
            $contacts->create($this->business, ['contact_type' => 'individual', 'role' => 'customer', 'name' => $name, 'email' => 'rumana@example.test', 'is_active' => true]);
        }
        $contacts->create($this->business, ['contact_type' => 'business', 'role' => 'supplier', 'name' => 'Salma Traders', 'email' => 'salma@example.test', 'is_active' => true]);

        $this->verifiedCustomer('nadia@example.test', 'Nadia Islam');
        $this->assertSame($nadia->id, StorefrontCustomer::where('email', 'nadia@example.test')->sole()->contact_id);

        // Two customers with the e-mail is not certain; a supplier alone is not a customer: a new contact each.
        $count = Contact::count();
        $this->verifiedCustomer('rumana@example.test', 'Rumana Akter');
        $this->verifiedCustomer('salma@example.test', 'Salma Begum');
        $this->assertSame($count + 2, Contact::count());

        $made = Contact::find(StorefrontCustomer::where('email', 'rumana@example.test')->sole()->contact_id);
        $this->assertSame(['Rumana Akter', 'rumana@example.test', Contact::ROLE_CUSTOMER, Contact::TYPE_INDIVIDUAL, 'en', 'Made by the store account of Rumana Akter on Rahman Shop.'], [$made->name, $made->email, $made->role, $made->contact_type, $made->preferred_language, $made->notes]);
        $this->assertSame('Added the contact Rumana Akter from a store account on Rahman Shop', ActivityLog::where('model_type', Contact::class)->where('model_id', $made->id)->sole()->description);
    }

    public function test_signed_in_the_order_is_the_account_s_at_its_e_mail_and_keeps_its_addresses(): void
    {
        $token   = $this->verifiedCustomer();
        $account = StorefrontCustomer::sole();
        $cart    = $this->asCustomer($token)->cartWith(['ceramic-mug' => 2]);

        $this->asCustomer($token)->quote($cart, ['address' => ['country' => 'BD']])->assertOk()->assertJsonPath('data.checkout.signed_in', true);

        $this->asCustomer($token)->place($cart, $this->order('21.00', ['customer' => ['name' => 'Karim Rahman', 'email' => 'someone@example.test', 'phone' => '+8801700000001']]))->assertCreated()
            ->assertJsonPath('data.customer.email', 'karim@example.test');
        $order = EcommerceOrder::sole();
        $this->assertSame([$account->id, $account->contact_id, false, 'karim@example.test'], [$order->storefront_customer_id, $order->contact_id, $order->contact_created, $order->customer_email]);

        // Its address kept, the default for billing and delivery.
        $this->asCustomer($token)->getJson(self::STORE.'/account/addresses')->assertOk()->assertExactJson(['data' => [[
            'id' => StorefrontCustomerAddress::sole()->id, 'line1' => 'House 12, Road 5', 'line2' => null, 'city' => 'Dhaka', 'region' => null, 'postal_code' => '1207', 'country' => 'BD', 'country_name' => 'Bangladesh',
            'default_billing' => true, 'default_shipping' => true,
        ]]]);

        // Shipped elsewhere: that address kept too, the defaults as they were. The same address again is not kept twice.
        $elsewhere = ['shipping_same' => false, 'shipping' => ['line1' => 'Flat 3, Lake Road', 'city' => 'Sylhet', 'postal_code' => '3100', 'country' => 'BD']];
        $this->asCustomer($token)->place($this->asCustomer($token)->cartWith(['ceramic-mug' => 2]), $this->order('21.00', $elsewhere))->assertCreated();
        $this->asCustomer($token)->place($this->asCustomer($token)->cartWith(['ceramic-mug' => 2]), $this->order('21.00', ['billing' => ['line1' => ' house 12,  ROAD 5', 'city' => 'dhaka', 'postal_code' => '1207', 'country' => 'BD']]))->assertCreated();

        $kept = StorefrontCustomerAddress::orderBy('id')->get();
        $this->assertSame([['Dhaka', true, true], ['Sylhet', false, false]], $kept->map(fn ($address) => [$address->city, $address->is_default_billing, $address->is_default_shipping])->all());
        $this->assertSame(3, EcommerceOrder::where('storefront_customer_id', $account->id)->count());
    }

    public function test_with_guest_checkout_off_only_signed_in_customers_place_orders(): void
    {
        app(EcommerceSettingRepository::class)->save($this->business, ['guest_checkout' => false]);
        $this->asCustomer(null);
        $cart = $this->cartWith(['ceramic-mug' => 2]);

        $this->quote($cart)->assertOk()->assertJsonPath('data.checkout.guests', false)->assertJsonPath('data.checkout.signed_in', false);
        $this->place($cart, $this->order('21.00'))->assertForbidden()->assertJsonPath('message', 'Sign in to place your order: this store takes orders from its signed-in customers.');

        $token = $this->verifiedCustomer();
        $this->asCustomer($token)->place($cart, $this->order('21.00'))->assertCreated();

        // An account not verified - with the business not asking for it - orders too: as a guest would, for its contact.
        app(EcommerceSettingRepository::class)->save($this->business, ['verify_customer_email' => false]);
        $unverified = $this->signUpCustomer('nadia@example.test', 'Nadia Islam')->json('data.token');
        $this->asCustomer($unverified)->place($this->asCustomer($unverified)->cartWith(['ceramic-mug' => 2]), $this->order('21.00'))->assertCreated();

        $nadia = StorefrontCustomer::where('email', 'nadia@example.test')->sole();
        $order = EcommerceOrder::where('storefront_customer_id', $nadia->id)->sole();
        $this->assertNull($nadia->fresh()->contact_id);
        $this->assertTrue($order->contact_created);
        $this->assertSame('nadia@example.test', $order->customer_email);
    }

    public function test_saved_addresses_have_one_default_each_up_to_twenty(): void
    {
        $token   = $this->verifiedCustomer();
        $address = fn (string $city, array $fields = []) => ['line1' => '1 Main Road', 'city' => $city, 'country' => 'BD', ...$fields];

        $first = $this->asCustomer($token)->postJson(self::STORE.'/account/addresses', $address('Dhaka'))->assertCreated()
            ->assertJsonPath('message', 'Address saved.')->assertJsonPath('data.default_billing', true)->assertJsonPath('data.default_shipping', true)->json('data.id');
        $second = $this->asCustomer($token)->postJson(self::STORE.'/account/addresses', $address('Sylhet', ['default_shipping' => true]))->assertCreated()
            ->assertJsonPath('data.default_billing', false)->json('data.id');
        $this->assertFalse(StorefrontCustomerAddress::find($first)->is_default_shipping);

        // Unticking a default alone changes nothing; a field changes.
        $this->asCustomer($token)->putJson(self::STORE."/account/addresses/{$first}", $address('Dhaka', ['postal_code' => '1207', 'default_billing' => false]))->assertOk()
            ->assertJsonPath('data.postal_code', '1207')->assertJsonPath('data.default_billing', true);

        // The default for delivery deleted: it passes to the newest address left.
        $this->asCustomer($token)->deleteJson(self::STORE."/account/addresses/{$second}")->assertOk()->assertExactJson(['message' => 'Address deleted.']);
        $this->assertTrue(StorefrontCustomerAddress::find($first)->is_default_shipping);

        $this->asCustomer($token)->postJson(self::STORE.'/account/addresses', $address('Dhaka', ['country' => 'XX']))->assertUnprocessable()->assertJsonPath('errors.country.0', 'Choose among the listed countries.');

        foreach (range(2, 20) as $at) {
            $this->asCustomer($token)->postJson(self::STORE.'/account/addresses', $address("Town {$at}"))->assertCreated();
        }
        $this->asCustomer($token)->postJson(self::STORE.'/account/addresses', $address('Town 21'))->assertUnprocessable()
            ->assertJsonPath('errors.line1.0', 'You can keep 20 addresses: delete one first.');

        // Another account's address is not found.
        $other = $this->verifiedCustomer('nadia@example.test', 'Nadia Islam');
        $this->asCustomer($other)->deleteJson(self::STORE."/account/addresses/{$first}")->assertNotFound();
        $this->asCustomer($other)->getJson(self::STORE.'/account/addresses')->assertOk()->assertExactJson(['data' => []]);
    }

    public function test_an_account_opened_again_shows_its_earlier_orders_once_verified_again(): void
    {
        $token = $this->verifiedCustomer();
        $this->asCustomer($token)->place($this->asCustomer($token)->cartWith(['ceramic-mug' => 2]), $this->order('21.00'))->assertCreated();
        $this->assertSame(1, StorefrontCustomerAddress::count());

        $this->asCustomer($token)->deleteJson(self::STORE.'/account', ['password' => self::CUSTOMER_PASSWORD])->assertOk();
        $this->assertSame(0, StorefrontCustomerAddress::count());

        // Whoever signs up with its e-mail - signed in at once, as the business no longer asks to verify - sees none of it.
        app(EcommerceSettingRepository::class)->save($this->business, ['verify_customer_email' => false]);
        $reopened = $this->signUpCustomer()->json('data.token');
        $this->assertNull(StorefrontCustomer::sole()->contact_id);
        $this->asCustomer($reopened)->getJson(self::STORE.'/account/orders')->assertOk()->assertJsonPath('data', []);

        // Verified again: its orders are back.
        $this->asCustomer($reopened)->postJson(self::STORE.'/account/verify', ['email' => 'karim@example.test', 'code' => $this->codeSentTo('karim@example.test')])->assertOk();
        $this->asCustomer($reopened)->getJson(self::STORE.'/account/orders')->assertOk()->assertJsonPath('data.0.number', 'EC-00001');
        $this->assertNotNull(StorefrontCustomer::sole()->contact_id);
    }
}
