<?php

namespace Tests\Feature;

use App\Models\User\EcommerceCart;
use App\Models\User\StorefrontCustomer;
use App\Models\User\User;
use App\Models\User\WorkspaceMember;
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
 * Abandoned carts (Doc 19 section 19.32 - Phase H3a): a cart learns its
 * customer at checkout - the account signed in, or the name and e-mail typed
 * - and whether they agreed to marketing by e-mail. Unused for Settings →
 * eCommerce's hours it is Abandoned: its visitor's still, Active again once
 * used, and placed as an active one is. Expired, its customer is cleared.
 * The Abandoned Carts page, under View Orders, lists each cart that was
 * abandoned, at the member's locations.
 */
class EcommerceAbandonedCartsTest extends TestCase
{
    use ChecksOutAStore;
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsInStoreCustomers;
    use SignsIntoBusinesses;

    private const CARTS = '/api/v1/ecommerce/abandoned-carts';

    private const SETTINGS = '/api/v1/business-settings/ecommerce';

    private const FIELDS = ['guest_checkout' => true, 'verify_customer_email' => true, 'price_display' => 'with_tax', 'order_prefix' => 'EC-'];

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo('2026-11-28 10:00:00');
        $this->openStorefront(['Ceramic Mug' => ['sale_price' => '8.00'], 'Tea Towel' => ['sale_price' => '3.00'], 'Tea Pot' => ['sale_price' => '20.00']]);
        $this->listed('Ceramic Mug');
        $this->listed('Tea Towel');
        $this->listed('Tea Pot');
        $this->setUpCheckout();
    }

    /** A new cart of $products - two mugs and a towel ($19) - its checkout asked with $choices, as a guest or signed in by $customer's token: its token. */
    private function atCheckout(array $choices, ?string $customer = null, array $products = ['ceramic-mug' => 2, 'tea-towel' => 1]): string
    {
        $this->asCustomer($customer);
        $token = $this->cartWith($products);
        $this->quote($token, $choices)->assertOk();

        return $token;
    }

    private function cartOf(string $token): EcommerceCart
    {
        return EcommerceCart::where('token_hash', hash('sha256', $token))->sole();
    }

    private function abandon(string $said): void
    {
        $this->artisan('ecommerce:abandon-carts')->expectsOutput($said)->assertSuccessful();
    }

    /** A member of the business with $permissions, at $locations (all, when null). */
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

    public function test_checkout_teaches_the_cart_its_customer_and_their_consent(): void
    {
        // A guest: the name and a whole e-mail typed, in small letters, and the box ticked with it.
        $token = $this->atCheckout(['name' => 'Karim Rahman', 'email' => 'Karim@Example.TEST', 'marketing' => true]);
        $cart  = $this->cartOf($token);
        $this->assertSame(['Karim Rahman', 'karim@example.test', true, null], [$cart->customer_name, $cart->customer_email, $cart->marketing, $cart->storefront_customer_id]);

        // Half an e-mail, or none, keeps what it knew, the box with it; a new e-mail replaces it, unticked.
        $this->quote($token, ['email' => 'nadia@', 'marketing' => false])->assertOk();
        $this->quote($token, ['name' => '', 'email' => null])->assertOk();
        $this->assertSame(['Karim Rahman', 'karim@example.test', true], [$cart->refresh()->customer_name, $cart->customer_email, $cart->marketing]);
        $this->quote($token, ['name' => 'Nadia Islam', 'email' => 'nadia@example.test'])->assertOk();
        $this->assertSame(['Nadia Islam', 'nadia@example.test', false], [$cart->refresh()->customer_name, $cart->customer_email, $cart->marketing]);

        // Signed in: the account's name and e-mail, whatever is typed - and the box, or their contact's standing consent.
        $karim  = $this->verifiedCustomer();
        $signed = $this->atCheckout(['name' => 'Someone Else', 'email' => 'someone@example.test'], $karim);
        $cart   = $this->cartOf($signed);
        $this->assertSame(['Karim Rahman', 'karim@example.test', false], [$cart->customer_name, $cart->customer_email, $cart->marketing]);
        $this->assertSame(StorefrontCustomer::where('email', 'karim@example.test')->value('id'), $cart->storefront_customer_id);
        $this->quote($signed, ['marketing' => true])->assertOk();
        $this->assertTrue($cart->refresh()->marketing);

        $this->asCustomer($karim)->putJson(self::STORE.'/account', ['name' => 'Karim Rahman', 'marketing' => true])->assertOk();
        $this->assertTrue($this->cartOf($this->atCheckout([], $karim))->marketing);
    }

    public function test_a_cart_whose_customer_is_known_is_abandoned_once_unused_for_the_period(): void
    {
        $known = $this->atCheckout(['email' => 'karim@example.test']);
        $this->asCustomer(null);
        $unknown = $this->cartWith(['tea-pot' => 1]);

        // Within the 24 hours nothing; past them, only the cart whose customer is known.
        $this->travel(23)->hours();
        $this->abandon('Abandoned 0 carts.');
        $this->travel(2)->hours();
        $this->abandon('Abandoned 1 cart.');
        $cart = $this->cartOf($known);
        $this->assertSame(EcommerceCart::ABANDONED, $cart->status);
        $this->assertSame(now()->toDateTimeString(), $cart->abandoned_at->toDateTimeString());
        $this->assertSame(EcommerceCart::ACTIVE, $this->cartOf($unknown)->status);

        // Still its visitor's: opened, it is active again - when it was abandoned kept.
        $this->asCustomer(null)->withHeaders(['X-Cart-Token' => $known])->getJson(self::STORE.'/cart')->assertOk()->assertJsonPath('data.count', 3);
        $this->assertSame(EcommerceCart::ACTIVE, $cart->refresh()->status);
        $this->assertNotNull($cart->abandoned_at);

        // The business's own hours.
        Sanctum::actingAs($this->user);
        $this->putJson(self::SETTINGS, [...self::FIELDS, 'abandoned_hours' => 2])->assertOk()->assertJsonPath('data.abandoned_hours', 2);
        $this->travel(1)->hours();
        $this->abandon('Abandoned 0 carts.');
        $this->travel(1)->hours();
        $this->abandon('Abandoned 1 cart.');

        // Placed while abandoned, it is converted, as an active cart is.
        $this->asCustomer(null);
        $this->place($known, $this->order('24.00'))->assertCreated();
        $this->assertSame(EcommerceCart::CONVERTED, $cart->refresh()->status);

        // Expired: its customer's details go.
        $late = $this->atCheckout(['name' => 'Nadia Islam', 'email' => 'nadia@example.test', 'marketing' => true]);
        $this->travel(31)->days();
        $this->artisan('ecommerce:expire-carts')->assertSuccessful();
        $cart = $this->cartOf($late);
        $this->assertSame([EcommerceCart::EXPIRED, null, null, false], [$cart->status, $cart->customer_name, $cart->customer_email, $cart->marketing]);
    }

    public function test_the_abandoned_carts_page_lists_each_cart_that_was_abandoned(): void
    {
        $karim = $this->atCheckout(['name' => 'Karim Rahman', 'email' => 'karim@example.test', 'marketing' => true]);
        $this->travel(1)->hours();
        $nadia = $this->atCheckout([], $this->verifiedCustomer('nadia@example.test', 'Nadia Islam'), ['tea-pot' => 1, 'ceramic-mug' => 1, 'tea-towel' => 2]);
        $this->travel(1)->hours();
        $rafi = $this->atCheckout(['name' => 'Rafi Ahmed', 'email' => 'rafi@example.test']);
        $this->travel(25)->hours();
        $this->abandon('Abandoned 3 carts.');

        // Rafi's placed afterwards; a cart never abandoned is not listed.
        $this->asCustomer(null);
        $this->place($rafi, $this->order('24.00'))->assertCreated();
        $this->atCheckout(['email' => 'sumi@example.test']);

        Sanctum::actingAs($this->user);
        $this->getJson(self::CARTS)->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['id', 'storefront', 'customer', 'items', 'lines', 'value', 'currency', 'last_activity_at', 'marketing', 'status', 'order']], 'pagination'])
            ->assertJsonPath('data.0.customer', ['name' => 'Rafi Ahmed', 'email' => 'rafi@example.test', 'account' => false])
            ->assertJsonPath('data.0.status', 'converted')
            ->assertJsonPath('data.0.order.number', 'EC-00001')
            ->assertJsonPath('data.1.customer', ['name' => 'Nadia Islam', 'email' => 'nadia@example.test', 'account' => true])
            ->assertJsonPath('data.1.items', [['title' => 'Tea Pot', 'quantity' => '1'], ['title' => 'Ceramic Mug', 'quantity' => '1']])
            ->assertJsonPath('data.1.lines', 3)
            ->assertJsonPath('data.1.value', '34.00')
            ->assertJsonPath('data.1.marketing', false)
            ->assertJsonPath('data.1.status', 'abandoned')
            ->assertJsonPath('data.1.order', null)
            ->assertJsonPath('data.2.customer.email', 'karim@example.test')
            ->assertJsonPath('data.2.storefront', 'Rahman Shop')
            ->assertJsonPath('data.2.value', '19.00')
            ->assertJsonPath('data.2.marketing', true);

        // Narrowed by status and storefront, searched by name or e-mail.
        $this->getJson(self::CARTS.'?status=abandoned')->assertJsonCount(2, 'data');
        $this->getJson(self::CARTS.'?search=nadia')->assertJsonCount(1, 'data')->assertJsonPath('data.0.customer.name', 'Nadia Islam');
        $this->getJson(self::CARTS.'?search=KARIM@')->assertJsonCount(1, 'data');
        $this->getJson(self::CARTS.'?storefront_id='.$this->store->id)->assertJsonCount(3, 'data');
        $this->getJson(self::CARTS.'?storefront_id=999')->assertJsonCount(0, 'data');
        $this->getJson(self::CARTS.'?status=lost')->assertUnprocessable();

        // Under View Orders, at the member's locations.
        Sanctum::actingAs($this->member(['ecommerce.orders.view']));
        $this->getJson(self::CARTS)->assertOk()->assertJsonCount(3, 'data');
        $elsewhere = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Chattogram', 'code' => 'CTG']);
        Sanctum::actingAs($this->member(['ecommerce.orders.view'], [$elsewhere->id]));
        $this->getJson(self::CARTS)->assertOk()->assertJsonCount(0, 'data');
        Sanctum::actingAs($this->member(['ecommerce.store.view']));
        $this->getJson(self::CARTS)->assertForbidden();
    }

    public function test_the_abandoned_period_is_set_in_the_settings(): void
    {
        $this->getJson(self::SETTINGS)->assertJsonPath('data.abandoned_hours', 24);
        $this->putJson(self::SETTINGS, [...self::FIELDS, 'abandoned_hours' => 0])->assertJsonValidationErrors(['abandoned_hours' => 'Give an hour at least.']);
        $this->putJson(self::SETTINGS, [...self::FIELDS, 'abandoned_hours' => 721])->assertJsonValidationErrors(['abandoned_hours' => 'Give up to 720 hours (30 days).']);

        // Shorter than the days carts are kept - those sent, or those saved.
        $this->putJson(self::SETTINGS, [...self::FIELDS, 'cart_days' => 2, 'abandoned_hours' => 48])->assertJsonValidationErrors(['abandoned_hours' => 'Make it shorter than the days carts are kept.']);
        $this->putJson(self::SETTINGS, [...self::FIELDS, 'cart_days' => 2, 'abandoned_hours' => 47])->assertOk()->assertJsonPath('data.abandoned_hours', 47);
        $this->putJson(self::SETTINGS, [...self::FIELDS, 'cart_days' => 1])->assertJsonValidationErrors(['abandoned_hours' => 'Make it shorter than the days carts are kept.']);
        $this->putJson(self::SETTINGS, self::FIELDS)->assertOk()->assertJsonPath('data.abandoned_hours', 47)->assertJsonPath('data.cart_days', 2);
    }
}
