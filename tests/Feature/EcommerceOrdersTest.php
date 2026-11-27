<?php

namespace Tests\Feature;

use App\Models\User\EcommerceOrder;
use App\Models\User\User;
use App\Models\User\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\ChecksOutAStore;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * The business's online orders (Doc 19 sections 19.18 and 19.19 - Phase
 * F1): the Orders page with its filters, an order's page and its timeline,
 * under View Orders and kept to the locations a member may use.
 */
class EcommerceOrdersTest extends TestCase
{
    use ChecksOutAStore;
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsIntoBusinesses;

    private const ORDERS = '/api/v1/ecommerce/orders';

    protected function setUp(): void
    {
        parent::setUp();

        $this->openStorefront([
            'Ceramic Mug' => ['sale_price' => '8.00', 'sku' => 'MUG-1'],
            'E-Book'      => ['sale_price' => '4.00', 'type' => 'digital'],
        ]);
        $this->listed('Ceramic Mug', ['weight' => '0.5']);
        $this->listed('E-Book');
        $this->setUpCheckout();
    }

    /** Two orders: EC-00001, two mugs shipped and paid by transfer with a reference; EC-00002, an e-book for Nadia, unpaid. */
    private function placeTwo(): void
    {
        $this->place($this->cartWith(['ceramic-mug' => 2]), $this->order('21.00', ['payment_reference' => 'TRX-1001', 'note' => 'Ring the bell.', 'marketing' => true, 'campaign' => ['source' => 'facebook']]))->assertCreated();
        $this->place($this->cartWith(['e-book' => 1]), $this->order('4.00', [
            'customer' => ['name' => 'Nadia Islam', 'email' => 'nadia@example.test', 'phone' => '+8801700000002'], 'delivery' => null, 'shipping_method_id' => null,
        ]))->assertCreated();
        Sanctum::actingAs($this->user);
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

    public function test_the_orders_page_lists_the_newest_first_with_their_three_statuses(): void
    {
        $this->getJson(self::ORDERS)->assertOk()->assertJsonPath('data', []);
        $this->placeTwo();

        $this->getJson(self::ORDERS)->assertOk()
            ->assertJsonPath('data.*.number', ['EC-00002', 'EC-00001'])
            ->assertJsonPath('data.1.storefront', 'Rahman Shop')
            ->assertJsonPath('data.1.customer', ['name' => 'Karim Rahman', 'email' => 'Karim@Example.test'])
            ->assertJsonPath('data.1.products', 2)
            ->assertJsonPath('data.1.delivery', 'shipping')
            ->assertJsonPath('data.1.total', '21.00')
            ->assertJsonPath('data.1.currency', 'USD')
            ->assertJsonPath('data.1.status', 'pending')
            ->assertJsonPath('data.1.payment_status', 'pending')
            ->assertJsonPath('data.1.fulfilment_status', 'unfulfilled')
            ->assertJsonPath('data.0.payment_status', 'unpaid')
            ->assertJsonPath('data.0.fulfilment_status', 'unfulfilled')
            ->assertJsonPath('pagination.total', 2);

        // Each filter, and a number, name or e-mail searched for. From Phase G3a, an order with nothing to ship is still to be fulfilled.
        $this->getJson(self::ORDERS.'?payment_status=unpaid')->assertJsonPath('data.*.number', ['EC-00002']);
        $this->getJson(self::ORDERS.'?fulfilment_status=unfulfilled')->assertJsonPath('data.*.number', ['EC-00002', 'EC-00001']);
        $this->getJson(self::ORDERS.'?fulfilment_status=not_required')->assertJsonPath('data', []);
        $this->getJson(self::ORDERS.'?status=pending&storefront_id='.$this->store->id)->assertJsonPath('pagination.total', 2);
        $this->getJson(self::ORDERS.'?storefront_id=999')->assertJsonPath('data', []);
        $this->getJson(self::ORDERS.'?search=00001')->assertJsonPath('data.*.number', ['EC-00001']);
        $this->getJson(self::ORDERS.'?search=Nadia')->assertJsonPath('data.*.number', ['EC-00002']);
        $this->getJson(self::ORDERS.'?search=KARIM@')->assertJsonPath('data.*.number', ['EC-00001']);
        $this->getJson(self::ORDERS.'?status=lost')->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_an_order_shows_what_was_bought_how_and_by_whom_with_its_timeline(): void
    {
        $this->placeTwo();
        $order = EcommerceOrder::where('number', 'EC-00001')->sole();

        $this->getJson(self::ORDERS.'/'.$order->id)->assertOk()
            ->assertJsonPath('data.number', 'EC-00001')
            ->assertJsonPath('data.storefront.name', 'Rahman Shop')
            ->assertJsonPath('data.location', 'Dhaka')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.customer.name', 'Karim Rahman')
            ->assertJsonPath('data.account', false)
            ->assertJsonPath('data.contact.name', 'Karim Rahman')
            ->assertJsonPath('data.contact.created', true)
            ->assertJsonPath('data.shipping_address.city', 'Dhaka')
            ->assertJsonPath('data.delivery.kind', 'shipping')
            ->assertJsonPath('data.delivery.name', 'Standard')
            ->assertJsonPath('data.payment', ['name' => 'Bank Transfer', 'type' => 'bank_transfer', 'reference' => 'TRX-1001'])
            ->assertJsonPath('data.note', 'Ring the bell.')
            ->assertJsonPath('data.campaign', ['source' => 'facebook'])
            ->assertJsonPath('data.consents.marketing', true)
            ->assertJsonPath('data.lines.0.title', 'Ceramic Mug')
            ->assertJsonPath('data.lines.0.sku', 'MUG-1')
            ->assertJsonPath('data.lines.0.quantity', '2.000')
            ->assertJsonPath('data.lines.0.unit_price', '8.00')
            ->assertJsonPath('data.lines.0.amount', '16.00')
            ->assertJsonPath('data.lines.0.price_list', null)
            ->assertJsonPath('data.totals.shipping', '5.00')
            ->assertJsonPath('data.totals.grand_total', '21.00')
            ->assertJsonPath('data.emails.0.kind', 'order_received')
            ->assertJsonPath('data.emails.0.to', 'Karim@Example.test')
            ->assertJsonMissingPath('data.access_token_hash')
            ->assertJsonMissingPath('data.ip_address');

        // Its timeline: placed, by the customer - no user, and not izSuite's own.
        $this->getJson(self::ORDERS.'/'.$order->id.'/activity')->assertOk()
            ->assertJsonPath('data.0.description', 'Online order EC-00001 placed on Rahman Shop by Karim Rahman')
            ->assertJsonPath('data.0.by', 'The customer')
            ->assertJsonPath('data.0.system', false);

        $this->getJson(self::ORDERS.'/999')->assertNotFound()->assertJsonPath('message', 'There is no such order.');
        $this->getJson(self::ORDERS.'/999/activity')->assertNotFound();
    }

    public function test_orders_need_view_orders_and_are_kept_to_the_members_locations(): void
    {
        $this->placeTwo();
        $order    = EcommerceOrder::where('number', 'EC-00001')->sole();
        $elsewhere = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Chattogram', 'code' => 'CTG']);

        // Without View Orders: refused.
        Sanctum::actingAs($this->member(['ecommerce.store.view']));
        $this->getJson(self::ORDERS)->assertForbidden();
        $this->getJson(self::ORDERS.'/'.$order->id)->assertForbidden();

        // With it, at Dhaka - the store's location: its orders.
        Sanctum::actingAs($this->member(['ecommerce.orders.view'], [$this->store->location_id]));
        $this->getJson(self::ORDERS)->assertOk()->assertJsonPath('pagination.total', 2);
        $this->getJson(self::ORDERS.'/'.$order->id)->assertOk();

        // At another location only: none, and the order is not found.
        Sanctum::actingAs($this->member(['ecommerce.orders.view'], [$elsewhere->id]));
        $this->getJson(self::ORDERS)->assertOk()->assertJsonPath('data', []);
        $this->getJson(self::ORDERS.'/'.$order->id)->assertNotFound();
        $this->getJson(self::ORDERS.'/'.$order->id.'/activity')->assertNotFound();
    }
}
