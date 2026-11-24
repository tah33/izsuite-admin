<?php

namespace Tests\Feature;

use App\Enums\SalesDocumentStatus;
use App\Mail\StoreOrderMail;
use App\Models\User\CatalogProduct;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderEmail;
use App\Models\User\SalesDocument;
use App\Models\User\SalesPayment;
use App\Models\User\User;
use App\Models\User\WorkspaceMember;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\ChecksOutAStore;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Holding an online order for review, releasing it, and cancelling it (Doc
 * 19 sections 19.21 and 19.24 - Phase F4), each with a reason: what is
 * blocked while it is held, what cancelling gives back and voids, what
 * stops it, what may want a look - shown to the business, never the
 * customer, and holding an order as it is placed when Settings → eCommerce
 * says so.
 */
class EcommerceOrderHoldsTest extends TestCase
{
    use ChecksOutAStore;
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsIntoBusinesses;

    private const ORDERS = '/api/v1/ecommerce/orders';

    private const SETTINGS = '/api/v1/business-settings/ecommerce';

    private CatalogProduct $mug;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo('2026-11-24 10:00:00');
        $this->openStorefront(['Ceramic Mug' => ['sale_price' => '8.00']]);
        $this->mug = $this->listed('Ceramic Mug', ['availability' => CatalogProduct::LIMITED, 'stock_quantity' => '10']);
        $this->setUpCheckout();
    }

    /** An order of two mugs ($21.00), shipped and paid by bank transfer with TRX-1001 - checkout's $fields over that - and the owner signed in again. */
    private function placed(array $fields = []): EcommerceOrder
    {
        $this->place($this->cartWith(['ceramic-mug' => 2]), $this->order('21.00', ['payment_reference' => 'TRX-1001', ...$fields]))->assertCreated();
        Sanctum::actingAs($this->user);

        return EcommerceOrder::query()->latest('id')->firstOrFail();
    }

    private function act(EcommerceOrder $order, string $what, ?string $reason = 'Checking it.')
    {
        return $this->postJson(self::ORDERS."/{$order->id}/{$what}", $reason === null ? [] : ['reason' => $reason]);
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

    public function test_an_order_is_held_with_why_and_released_back_to_where_it_was(): void
    {
        $order = $this->placed();

        $this->act($order, 'hold', null)->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->act($order, 'hold', 'The address looks wrong.')->assertOk()
            ->assertJsonPath('message', 'Order EC-00001 is on hold.')
            ->assertJsonPath('data.status', 'on_hold')
            ->assertJsonPath('data.held_status', 'pending')
            ->assertJsonPath('data.status_reason', 'The address looks wrong.');

        // Held: not confirmed, paid or held again - and its customer sees it held, never why.
        $this->postJson(self::ORDERS."/{$order->id}/confirm")->assertStatus(409)->assertJsonPath('message', 'Only a pending order is confirmed: EC-00001 is on hold.');
        $this->postJson(self::ORDERS."/{$order->id}/payments", ['amount' => '21.00', 'payment_date' => '2026-11-24'])->assertStatus(409)->assertJsonPath('message', 'Order EC-00001 is on hold: release it first.');
        $this->act($order, 'hold')->assertStatus(409)->assertJsonPath('message', 'Order EC-00001 is on hold already.');

        $this->act($order, 'release', 'The customer confirmed it.')->assertOk()
            ->assertJsonPath('message', 'Order EC-00001 is released.')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.held_status', null)
            ->assertJsonPath('data.status_reason', null);
        $this->act($order, 'release')->assertStatus(409)->assertJsonPath('message', 'Order EC-00001 is not on hold.');
        $this->getJson(self::ORDERS."/{$order->id}/activity")
            ->assertJsonPath('data.0.description', 'Released online order EC-00001 from hold: The customer confirmed it.')
            ->assertJsonPath('data.1.description', 'Put online order EC-00001 on hold: The address looks wrong.');

        // Confirmed, held, released: confirmed again. Being fulfilled, it is held no more.
        $this->postJson(self::ORDERS."/{$order->id}/confirm")->assertOk();
        $this->act($order, 'hold')->assertOk()->assertJsonPath('data.held_status', 'confirmed');
        $this->act($order, 'release')->assertOk()->assertJsonPath('data.status', 'confirmed');
        $order->fresh()->update(['fulfilment_status' => 'shipped']);
        $this->act($order, 'hold')->assertStatus(409)->assertJsonPath('message', 'Order EC-00001 is being fulfilled: it can no longer be put on hold.');
    }

    public function test_cancelling_gives_back_what_was_counted_down_and_voids_an_unpaid_invoice(): void
    {
        $order = $this->placed();
        $this->postJson(self::ORDERS."/{$order->id}/confirm")->assertOk();
        $this->assertSame('8.000', (string) $this->mug->fresh()->stock_quantity);

        $this->act($order, 'cancel', 'The customer asked us to.')->assertOk()
            ->assertJsonPath('message', 'Order EC-00001 is cancelled, and the customer told.')
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.status_reason', 'The customer asked us to.');

        $invoice = SalesDocument::sole();
        $this->assertSame([SalesDocumentStatus::Void, 'not_applicable', '10.000'], [$invoice->status, $invoice->payment_status, (string) $this->mug->fresh()->stock_quantity]);
        $this->assertNull($invoice->activeLink);
        $this->getJson("/api/v1/sales/invoices/{$invoice->id}/activity")->assertJsonPath('data.0.description', 'Voided invoice INV-00001');
        $this->getJson(self::ORDERS."/{$order->id}/activity")->assertJsonPath('data.0.description', 'Cancelled online order EC-00001: The customer asked us to.');

        Mail::assertSent(StoreOrderMail::class, function (StoreOrderMail $mail) {
            $html = $mail->render();

            return $mail->mailSubject === 'Your order EC-00001 at Rahman Shop is cancelled'
                && str_contains($html, 'Your order <strong>EC-00001</strong>, placed on')
                && str_contains($html, 'is cancelled. Here is what it was:')
                && str_contains($html, 'It will not be sent.')
                && str_contains($html, 'If you have paid for it, we will be in touch about your money.')
                && ! str_contains($html, 'asked us to')
                && ! str_contains($html, 'Pay into City Bank')
                && ! str_contains($html, 'See your');
        });
        $this->assertSame(EcommerceOrderEmail::SENT, EcommerceOrderEmail::where('kind', EcommerceOrderEmail::ORDER_CANCELLED)->sole()->status);

        $this->act($order, 'cancel')->assertStatus(409)->assertJsonPath('message', 'Order EC-00001 is cancelled already.');

        // Pending, nothing was counted down or invoiced: nothing is given back.
        $pending = $this->placed();
        $this->act($pending, 'cancel')->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(['10.000', 1], [(string) $this->mug->fresh()->stock_quantity, SalesDocument::count()]);
    }

    public function test_an_order_with_payments_or_on_its_way_is_not_cancelled(): void
    {
        $order = $this->placed();
        $this->postJson(self::ORDERS."/{$order->id}/payments", ['amount' => '5.00', 'payment_date' => '2026-11-24'])->assertOk();

        $this->act($order, 'cancel')->assertStatus(409)
            ->assertJsonPath('message', "Payments settle part of order EC-00001's invoice, INV-00001: refund them through a credit note, or reverse them in Sales, then cancel the order.");
        $this->assertSame(['confirmed', '8.000'], [$order->fresh()->status, (string) $this->mug->fresh()->stock_quantity]);

        // Its payment reversed in Sales: it is cancelled.
        $this->postJson('/api/v1/sales/payments/'.SalesPayment::sole()->id.'/reverse', ['reason' => 'Not ours.'])->assertOk();
        $this->act($order, 'cancel')->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame('10.000', (string) $this->mug->fresh()->stock_quantity);

        $shipped = $this->placed();
        $shipped->update(['fulfilment_status' => 'shipped']);
        $this->act($shipped, 'cancel')->assertStatus(409)->assertJsonPath('message', 'Order EC-00002 is on its way or with its customer: it is returned, not cancelled.');
    }

    public function test_what_may_want_a_look_is_shown_to_the_business_and_held_as_placed_when_asked(): void
    {
        $this->putJson(self::SETTINGS, ['guest_checkout' => true, 'verify_customer_email' => true, 'price_display' => 'with_tax', 'order_prefix' => 'EC-', 'review_above' => '20', 'hold_for_review' => false])->assertOk()
            ->assertJsonPath('data.review_above', '20.00')
            ->assertJsonPath('data.hold_for_review', false);

        // Billed in India, delivered to Bangladesh, $21.00 above $20.00.
        $order = $this->placed(['billing' => ['line1' => '5 Park Street', 'city' => 'Kolkata', 'postal_code' => '700016', 'country' => 'IN'], 'shipping_same' => false, 'shipping' => ['line1' => 'House 12, Road 5', 'city' => 'Dhaka', 'postal_code' => '1207', 'country' => 'BD']]);
        $this->getJson(self::ORDERS."/{$order->id}")->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.review', ['Billed in India but delivered to Bangladesh.', 'Its total, USD 21.00, is above Review Orders Above (USD 20.00).']);

        // Failed twice; and a third order from one e-mail within a day.
        $plain = $this->placed();
        $this->act($plain, 'payment-failed', 'Nothing came.')->assertOk();
        $this->act($plain, 'payment-failed', 'Nothing again.')->assertOk()
            ->assertJsonPath('data.review', ['Its total, USD 21.00, is above Review Orders Above (USD 20.00).', 'Its payment was marked failed 2 times.']);
        $third = $this->placed();
        $this->getJson(self::ORDERS."/{$third->id}")->assertJsonPath('data.review.1', 'karim@example.test placed 3 orders within a day, up to this one.');

        // Never shown to the customer.
        $key = $this->place($this->cartWith(['ceramic-mug' => 1]), $this->order('13.00'))->assertCreated()->assertJsonMissingPath('data.review')->assertJsonMissingPath('data.status_reason')->json('data.key');
        $this->getJson(self::STORE."/orders/{$key}")->assertOk()->assertJsonMissingPath('data.review')->assertJsonPath('data.status', 'pending');

        // Hold Orders for Review on: such an order is held as it is placed; its customer sees it On hold.
        Sanctum::actingAs($this->user);
        $this->putJson(self::SETTINGS, ['guest_checkout' => true, 'verify_customer_email' => true, 'price_display' => 'with_tax', 'order_prefix' => 'EC-', 'review_above' => '20', 'hold_for_review' => true])->assertOk();
        $key  = $this->place($this->cartWith(['ceramic-mug' => 2]), $this->order('21.00'))->assertCreated()->json('data.key');
        $held = EcommerceOrder::query()->latest('id')->firstOrFail();
        $this->assertSame([EcommerceOrder::ON_HOLD, EcommerceOrder::PENDING], [$held->status, $held->held_status]);
        $this->assertStringStartsWith('Held for review as it was placed: Its total, USD 21.00, is above Review Orders Above', $held->status_reason);
        $this->getJson(self::STORE."/orders/{$key}")->assertJsonPath('data.status', 'on_hold')->assertJsonMissingPath('data.status_reason');
        Sanctum::actingAs($this->user);
        $this->getJson(self::ORDERS."/{$held->id}/activity")->assertJsonPath('data.0.description', 'Put online order '.$held->number.' on hold: '.$held->status_reason);

        // Nothing wanting a look: placed as ever.
        $this->place($this->cartWith(['ceramic-mug' => 1]), $this->order('13.00', ['customer' => ['name' => 'Rafi Ahmed', 'email' => 'rafi@example.test', 'phone' => '+8801700000003']]))->assertCreated();
        $this->assertSame(EcommerceOrder::PENDING, EcommerceOrder::query()->latest('id')->firstOrFail()->status);
    }

    public function test_holding_and_cancelling_need_their_own_permissions_at_the_order_s_location(): void
    {
        $order     = $this->placed();
        $elsewhere = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Chattogram', 'code' => 'CTG']);

        $this->assertContains('ecommerce.orders.view', Permissions::expand(['ecommerce.orders.hold', 'ecommerce.orders.cancel']));
        $this->assertContains('ecommerce.orders.cancel', Permissions::forTemplate('manager', ['sales', 'ecommerce']));
        $this->assertNotContains('ecommerce.orders.hold', Permissions::forTemplate('view_only', ['sales', 'ecommerce']));

        Sanctum::actingAs($this->member(['ecommerce.orders.view', 'ecommerce.orders.confirm']));
        foreach (['hold', 'release', 'cancel'] as $what) {
            $this->act($order, $what)->assertForbidden();
        }

        Sanctum::actingAs($this->member(['ecommerce.orders.hold'], [$elsewhere->id]));
        $this->act($order, 'hold')->assertNotFound();

        Sanctum::actingAs($this->member(['ecommerce.orders.hold'], [$this->store->location_id]));
        $this->act($order, 'cancel')->assertForbidden();
        $this->act($order, 'hold')->assertOk();
        $this->act($order, 'release')->assertOk();

        Sanctum::actingAs($this->member(['ecommerce.orders.cancel']));
        $this->act($order, 'hold')->assertForbidden();
        $this->act($order, 'cancel')->assertOk()->assertJsonPath('data.status', 'cancelled');
    }
}
