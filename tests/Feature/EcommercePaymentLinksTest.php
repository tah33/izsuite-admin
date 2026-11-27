<?php

namespace Tests\Feature;

use App\Mail\StoreOrderMail;
use App\Models\User\CatalogProduct;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderEmail;
use App\Models\User\EcommercePaymentLink;
use App\Models\User\EcommercePaymentMethod;
use App\Models\User\User;
use App\Models\User\WorkspaceMember;
use App\Services\Documents\DocumentFormat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\ChecksOutAStore;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Payment links for online orders (Doc 19 section 19.23 - Phase F6a), under
 * Confirm Orders: what one would ask - all the order owes, or less with
 * paying in part on - by the store's methods paid from afar; sent and
 * e-mailed to the customer, a newer one replacing the one active; its page
 * on the store, by its token, until the end of its last day where the
 * business is; revoked by hand, with its order cancelled or its total
 * changed; paid with its order - and never a sale of its own.
 */
class EcommercePaymentLinksTest extends TestCase
{
    use ChecksOutAStore;
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsIntoBusinesses;

    private const ORDERS = '/api/v1/ecommerce/orders';

    private const SETTINGS = '/api/v1/business-settings/ecommerce';

    private const TODAY = '2026-11-24';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        // 16:00 in Dhaka, where the business is.
        $this->travelTo(self::TODAY.' 10:00:00');
        $this->openStorefront(['Ceramic Mug' => ['sale_price' => '8.00']]);
        $this->listed('Ceramic Mug', ['availability' => CatalogProduct::LIMITED, 'stock_quantity' => '10']);
        $this->setUpCheckout();
        $this->business->businessSetting()->create([
            'user_id' => $this->user->id, 'business_name' => 'Rahman Traders', 'language' => 'en', 'country' => 'BD', 'state' => 'Dhaka',
            'city'    => 'Dhaka', 'zip_code' => '1207', 'address_line_1' => 'House 12, Road 5', 'timezone' => 'Asia/Dhaka', 'stock_accounting_method' => 'fifo',
        ]);
    }

    /** An order of two mugs ($21.00), shipped and paid by bank transfer with TRX-1001 - checkout's $fields over that - and the owner signed in again. */
    private function placed(array $fields = []): EcommerceOrder
    {
        $this->place($this->cartWith(['ceramic-mug' => 2]), $this->order('21.00', ['payment_reference' => 'TRX-1001', ...$fields]))->assertCreated();
        Sanctum::actingAs($this->user);

        return EcommerceOrder::query()->latest('id')->firstOrFail();
    }

    /** Order $order sent a link of $amount for $days by Bank Transfer and bKash - $fields over that. */
    private function send(EcommerceOrder $order, string $amount = '21.00', array $fields = [])
    {
        return $this->postJson(self::ORDERS."/{$order->id}/payment-links", [
            'amount' => $amount, 'days' => 7, 'payment_method_ids' => [$this->ids['Bank Transfer'], $this->ids['bKash']], ...$fields,
        ]);
    }

    /** The newest payment link e-mailed: the mail itself. */
    private function linkMail(): StoreOrderMail
    {
        return Mail::sent(StoreOrderMail::class, fn (StoreOrderMail $mail) => $mail->order['kind'] === EcommerceOrderEmail::PAYMENT_LINK)->last();
    }

    /** The newest link's page on the store, as its customer opens it. */
    private function page()
    {
        return $this->getJson(self::STORE.'/pay/'.Str::afterLast($this->linkMail()->order['link'], '/'));
    }

    private function settings(array $fields): void
    {
        $this->putJson(self::SETTINGS, ['guest_checkout' => true, 'verify_customer_email' => true, 'price_display' => 'with_tax', 'order_prefix' => 'EC-', ...$fields])->assertOk();
    }

    /** $amount of USD as the order's e-mails write it. */
    private function money(string $amount): string
    {
        return (new DocumentFormat('d M Y', 'comma_dot'))->money($amount, 'USD');
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

    public function test_a_link_asks_all_the_order_owes_by_the_methods_paid_from_afar_and_is_e_mailed(): void
    {
        $order = $this->placed();

        // Its own method first, then the store's other one paid from afar - neither cash on delivery nor at the shop.
        $this->getJson(self::ORDERS."/{$order->id}/payment-link")->assertOk()
            ->assertExactJson(['data' => [
                'owed' => '21.00', 'currency' => 'USD', 'partial' => false, 'days' => 7, 'today' => self::TODAY, 'to' => 'Karim@Example.test',
                'methods' => [['id' => $this->ids['Bank Transfer'], 'name' => 'Bank Transfer'], ['id' => $this->ids['bKash'], 'name' => 'bKash']],
            ]]);

        // Paying in part is off: all of it, through a method offered.
        $this->send($order, '20.00')->assertUnprocessable()
            ->assertJsonPath('errors.amount.0', 'A link asks all that order EC-00001 owes, USD 21.00, while paying in part is off in Settings → eCommerce.');
        $this->send($order, '21.00', ['payment_method_ids' => [$this->ids['Cash on Delivery']]])->assertUnprocessable()
            ->assertJsonPath('errors.payment_method_ids.0', 'Choose among the ways to pay a link may offer this order.');
        $this->send($order, '21.00', ['payment_method_ids' => []])->assertUnprocessable()
            ->assertJsonPath('errors.payment_method_ids.0', 'Choose at least one way to pay.');

        $this->send($order)->assertOk()
            ->assertJsonPath('message', 'A payment link for order EC-00001 is on its way to Karim@Example.test.')
            ->assertJsonPath('data.payment_links', [[
                'id' => EcommercePaymentLink::sole()->id, 'amount' => '21.00', 'currency' => 'USD', 'methods' => ['Bank Transfer', 'bKash'], 'sent_to' => 'Karim@Example.test',
                'sent_by' => $this->user->name, 'sent_at' => '2026-11-24T10:00:00+00:00', 'expires_at' => '2026-12-01T17:59:59+00:00', 'state' => 'active', 'revoked_why' => null,
            ]]);

        // Its token is kept only hashed; its page is on the store, e-mailed to the customer.
        $mail  = $this->linkMail();
        $token = Str::afterLast($mail->order['link'], '/');
        $this->assertSame(rtrim((string) config('app.frontend_url'), '/')."/store/rahman-shop/pay/{$token}", $mail->order['link']);
        $this->assertSame([64, hash('sha256', $token)], [strlen($token), EcommercePaymentLink::sole()->token_hash]);

        $html = $mail->render();
        $this->assertSame('Pay for your order EC-00001 at Rahman Shop', $mail->mailSubject);
        $this->assertStringContainsString("You can pay <strong>{$this->money('21.00')}</strong> for your order <strong>EC-00001</strong>, placed on 24 Nov 2026, through the link below until 01 Dec 2026.", $html);
        $this->assertStringContainsString('<strong>Payment: Bank Transfer or bKash</strong>', $html);
        $this->assertStringContainsString(e("Pay {$this->money('21.00')} by 01 Dec 2026, with your order number, EC-00001, as the payment's reference: the link below shows how."), $html);
        $this->assertStringContainsString('Pay for your order</a>', $html);
        $this->assertStringNotContainsString('Pay into City Bank', $html);

        $this->assertSame(['order_received', 'payment_link'], EcommerceOrderEmail::orderBy('id')->pluck('kind')->all());
        $this->getJson(self::ORDERS."/{$order->id}/activity")->assertJsonPath('data.0.description', 'Sent a payment link of USD 21.00 for online order EC-00001 to Karim@Example.test');

        // Never a sale of its own.
        $this->assertSame(['pending', 'pending', null], [$order->fresh()->status, $order->fresh()->payment_status, $order->fresh()->sales_document_id]);
    }

    public function test_paying_in_part_lets_a_link_ask_less_and_a_newer_link_replaces_the_active_one(): void
    {
        $order = $this->placed();
        $this->settings(['partial_payments' => true, 'payment_link_days' => 3]);

        $this->getJson(self::ORDERS."/{$order->id}/payment-link")->assertOk()->assertJsonPath('data.partial', true)->assertJsonPath('data.days', 3);

        $this->send($order, '22.00')->assertUnprocessable()->assertJsonPath('errors.amount.0', 'Order EC-00001 owes USD 21.00: ask that much at most.');
        $this->send($order, '5.00', ['days' => 3, 'payment_method_ids' => [$this->ids['bKash']]])->assertOk()
            ->assertJsonPath('data.payment_links.0.expires_at', '2026-11-27T17:59:59+00:00');
        $first = Str::afterLast($this->linkMail()->order['link'], '/');

        $this->send($order, '10.00', ['payment_method_ids' => [$this->ids['Bank Transfer']]])->assertOk()
            ->assertJsonPath('data.payment_links.0.amount', '10.00')
            ->assertJsonPath('data.payment_links.0.state', 'active')
            ->assertJsonPath('data.payment_links.1.amount', '5.00')
            ->assertJsonPath('data.payment_links.1.state', 'revoked')
            ->assertJsonPath('data.payment_links.1.revoked_why', 'replaced');

        $this->getJson(self::STORE."/pay/{$first}")->assertOk()->assertJsonPath('data.state', 'revoked')->assertJsonPath('data.methods', []);
        $this->page()->assertOk()->assertJsonPath('data.amount', '10.00')->assertJsonPath('data.methods.0.name', 'Bank Transfer');
    }

    public function test_a_link_s_page_shows_what_it_asks_and_how_to_pay_until_its_last_day_is_over(): void
    {
        $order = $this->placed();
        $this->send($order)->assertOk();

        $this->page()->assertOk()->assertExactJson(['data' => [
            'number' => 'EC-00001', 'state' => 'active', 'amount' => '21.00', 'currency' => 'USD', 'total' => '21.00', 'expires_on' => '2026-12-01',
            'lines'   => [['title' => 'Ceramic Mug', 'options' => [], 'quantity' => 2]],
            'methods' => [
                ['id' => $this->ids['Bank Transfer'], 'name' => 'Bank Transfer', 'instructions' => 'Pay into City Bank 0123456789.'],
                ['id' => $this->ids['bKash'], 'name' => 'bKash', 'instructions' => 'Send to 01700000000.'],
            ],
        ]]);

        // A method the store no longer takes is not offered.
        EcommercePaymentMethod::whereKey($this->ids['bKash'])->update(['is_active' => false]);
        $this->page()->assertOk()->assertJsonPath('data.methods.*.name', ['Bank Transfer']);

        // Not found by another store, or by a token it never gave.
        $token = Str::afterLast($this->linkMail()->order['link'], '/');
        $this->getJson('/api/v1/stores/other-shop/pay/'.$token)->assertNotFound()->assertJsonPath('message', 'There is no such store.');
        $this->getJson(self::STORE.'/pay/'.str_repeat('a', 64))->assertNotFound()->assertJsonPath('message', 'There is no such payment link. Check the link you were sent.');

        // Its last day in Dhaka, then over.
        $this->travelTo('2026-12-01 17:59:00');
        $this->page()->assertJsonPath('data.state', 'active');
        $this->travelTo('2026-12-01 18:00:00');
        $this->page()->assertOk()->assertJsonPath('data.state', 'expired')->assertJsonPath('data.methods', []);
        $this->getJson(self::ORDERS."/{$order->id}")->assertJsonPath('data.payment_links.0.state', 'expired');
        $this->postJson(self::ORDERS."/{$order->id}/payment-links/".EcommercePaymentLink::sole()->id.'/revoke')->assertStatus(409)
            ->assertJsonPath('message', 'The payment link of USD 21.00 is expired already.');
    }

    public function test_a_link_is_revoked_by_hand_with_its_order_s_total_changed_or_with_the_order_cancelled(): void
    {
        $order = $this->placed();
        $this->send($order)->assertOk();
        $link  = EcommercePaymentLink::sole();

        $this->postJson(self::ORDERS."/{$order->id}/payment-links/{$link->id}/revoke")->assertOk()
            ->assertJsonPath('message', 'The payment link for order EC-00001 is revoked.')
            ->assertJsonPath('data.payment_links.0.state', 'revoked')
            ->assertJsonPath('data.payment_links.0.revoked_why', 'revoked');
        $this->getJson(self::ORDERS."/{$order->id}/activity")->assertJsonPath('data.0.description', 'Revoked the payment link of USD 21.00 for online order EC-00001');
        $this->page()->assertOk()->assertJsonPath('data.state', 'revoked')->assertJsonPath('data.methods', []);
        $this->postJson(self::ORDERS."/{$order->id}/payment-links/{$link->id}/revoke")->assertStatus(409)->assertJsonPath('message', 'The payment link of USD 21.00 is revoked already.');

        // Another order's link is not this order's.
        $other = $this->placed();
        $this->postJson(self::ORDERS."/{$other->id}/payment-links/{$link->id}/revoke")->assertNotFound()->assertJsonPath('message', 'There is no such payment link.');

        // The total changed by an edit: the link asked what the order came to.
        $this->send($order)->assertOk();
        $this->putJson(self::ORDERS."/{$order->id}/discount", ['type' => 'amount', 'value' => '1.00', 'reason' => 'A loyal customer.'])->assertOk()
            ->assertJsonPath('data.payment_links.0.state', 'revoked')
            ->assertJsonPath('data.payment_links.0.revoked_why', 'changed');

        // The order cancelled: its link with it, and no link for it since.
        $this->send($order, '20.00')->assertOk();
        $this->postJson(self::ORDERS."/{$order->id}/cancel", ['reason' => 'The customer asked us to.'])->assertOk()
            ->assertJsonPath('data.payment_links.0.state', 'revoked')
            ->assertJsonPath('data.payment_links.0.revoked_why', 'cancelled');
        $this->page()->assertJsonPath('data.state', 'revoked');
        $this->getJson(self::ORDERS."/{$order->id}/payment-link")->assertStatus(409)->assertJsonPath('message', 'Order EC-00001 is cancelled: it takes no payment.');
        $this->send($order, '20.00')->assertStatus(409);

        // Three links, each revoked, and none ever the other order's.
        $this->assertSame([['revoked', 'revoked'], ['revoked', 'changed'], ['revoked', 'cancelled']], EcommercePaymentLink::orderBy('id')->get()->map(fn ($link) => [$link->status, $link->revoked_why])->all());
        $this->assertSame(0, EcommercePaymentLink::where('ecommerce_order_id', $other->id)->count());
    }

    public function test_a_link_asks_no_more_than_is_owed_and_is_paid_with_its_order(): void
    {
        $order = $this->placed();
        $this->send($order)->assertOk();

        // Part paid since: the page asks the rest.
        $this->postJson(self::ORDERS."/{$order->id}/payments", ['amount' => '10.00', 'payment_date' => self::TODAY, 'reference' => 'TRX-1001'])->assertOk();
        $this->page()->assertOk()->assertJsonPath('data.state', 'active')->assertJsonPath('data.amount', '11.00');

        // Paid in full: the link is paid, never paid again, and no new one is sent.
        $this->postJson(self::ORDERS."/{$order->id}/payments", ['amount' => '11.00', 'payment_date' => self::TODAY, 'reference' => 'TRX-1002'])->assertOk()
            ->assertJsonPath('data.payment_links.0.state', 'paid');
        $this->page()->assertOk()->assertJsonPath('data.state', 'paid')->assertJsonPath('data.methods', []);
        $this->getJson(self::ORDERS."/{$order->id}/payment-link")->assertStatus(409)->assertJsonPath('message', 'Order EC-00001 is paid already.');
        $this->send($order)->assertStatus(409);
    }

    public function test_an_order_on_hold_takes_no_link_until_released(): void
    {
        $order = $this->placed();
        $this->postJson(self::ORDERS."/{$order->id}/hold", ['reason' => 'Checking the address.'])->assertOk();

        $this->getJson(self::ORDERS."/{$order->id}/payment-link")->assertStatus(409)->assertJsonPath('message', 'Order EC-00001 is on hold: release it first.');
        $this->send($order)->assertStatus(409);

        $this->postJson(self::ORDERS."/{$order->id}/release", ['reason' => 'The address is right.'])->assertOk();
        $this->send($order)->assertOk();
    }

    public function test_links_need_confirm_orders_at_the_order_s_location_and_their_settings_are_saved(): void
    {
        $order = $this->placed();
        $this->send($order)->assertOk();
        $link      = EcommercePaymentLink::sole();
        $elsewhere = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Chattogram', 'code' => 'CTG']);

        Sanctum::actingAs($this->member(['ecommerce.orders.view']));
        $this->getJson(self::ORDERS."/{$order->id}/payment-link")->assertForbidden();
        $this->send($order)->assertForbidden();
        $this->postJson(self::ORDERS."/{$order->id}/payment-links/{$link->id}/revoke")->assertForbidden();
        // The order's page shows its links to whoever may view it.
        $this->getJson(self::ORDERS."/{$order->id}")->assertOk()->assertJsonPath('data.payment_links.0.state', 'active');

        Sanctum::actingAs($this->member(['ecommerce.orders.confirm'], [$elsewhere->id]));
        $this->getJson(self::ORDERS."/{$order->id}/payment-link")->assertNotFound();
        $this->send($order)->assertNotFound();

        Sanctum::actingAs($this->member(['ecommerce.orders.confirm'], [$this->store->location_id]));
        $this->getJson(self::ORDERS."/{$order->id}/payment-link")->assertOk()->assertJsonPath('data.owed', '21.00');

        // Settings → eCommerce: a link's days, 1 to 90, and paying in part.
        Sanctum::actingAs($this->user);
        $this->putJson(self::SETTINGS, ['guest_checkout' => true, 'verify_customer_email' => true, 'price_display' => 'with_tax', 'order_prefix' => 'EC-', 'payment_link_days' => 91])
            ->assertUnprocessable()->assertJsonValidationErrors('payment_link_days');
        $this->settings(['payment_link_days' => 14, 'partial_payments' => true]);
        $this->getJson(self::SETTINGS)->assertJsonPath('data.payment_link_days', 14)->assertJsonPath('data.partial_payments', true);
    }
}
