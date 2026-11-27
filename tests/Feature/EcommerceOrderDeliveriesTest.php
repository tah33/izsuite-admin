<?php

namespace Tests\Feature;

use App\Mail\StoreOrderMail;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderEmail;
use App\Models\User\EcommerceOrderLine;
use App\Models\User\User;
use App\Models\User\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\ChecksOutAStore;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * An online order's digital products delivered and its services performed
 * (Doc 19 sections 19.28 and 19.29 - Phase G3a): once paid in full, a
 * download or an external link at once, a license key or a delivery by hand
 * when the business gives it; a service performed on a day, paid or not;
 * access revoked and given again; the order's fulfilment and status following
 * - completed once all of it reached the customer and it is paid - and its
 * customer e-mailed and shown each, never anything protected before.
 */
class EcommerceOrderDeliveriesTest extends TestCase
{
    use ChecksOutAStore;
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsIntoBusinesses;

    private const ORDERS = '/api/v1/ecommerce/orders';

    private const TODAY = '2026-11-27';

    /** Each order's own link's key, by its id: its page as its customer opens it. */
    private array $keys = [];

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo(self::TODAY.' 10:00:00');
        $this->openStorefront([
            'Ceramic Mug'   => ['sale_price' => '8.00'],
            'Recipe E-Book' => ['sale_price' => '4.00', 'type' => 'digital', 'delivery_method' => 'download', 'delivery_instructions' => 'Open the PDF on any device.'],
            'Online Course' => ['sale_price' => '10.00', 'type' => 'digital', 'delivery_method' => 'external_link', 'delivery_instructions' => 'Join at https://course.example/rahman'],
            'Photo Pack'    => ['sale_price' => '5.00', 'type' => 'digital', 'delivery_method' => 'license_key'],
            'Logo Design'   => ['sale_price' => '20.00', 'type' => 'digital'],
            'Consultation'  => ['sale_price' => '15.00', 'type' => 'service', 'unit' => 'Hour'],
        ]);
        foreach (array_keys($this->items) as $name) {
            $this->listed($name);
        }
        $this->setUpCheckout();
    }

    /** An order of $products for $total - digital alone, by bank transfer, to start - confirmed unless $confirm is false. */
    private function placed(array $products, string $total, array $fields = [], bool $confirm = true): EcommerceOrder
    {
        $key = $this->place($this->cartWith($products), $this->order($total, ['delivery' => null, 'shipping_method_id' => null, ...$fields]))->assertCreated()->json('data.key');
        Sanctum::actingAs($this->user);
        $order = EcommerceOrder::query()->latest('id')->firstOrFail();
        $this->keys[$order->id] = $key;

        if ($confirm) {
            $this->postJson(self::ORDERS."/{$order->id}/confirm")->assertOk();
        }

        return $order;
    }

    private function line(EcommerceOrder $order, string $title): int
    {
        return EcommerceOrderLine::where('ecommerce_order_id', $order->id)->where('title', $title)->value('id');
    }

    private function act(EcommerceOrder $order, string $title, string $what, array $fields = [])
    {
        return $this->postJson(self::ORDERS."/{$order->id}/lines/{$this->line($order, $title)}/{$what}", $fields);
    }

    private function pay(EcommerceOrder $order, string $amount)
    {
        return $this->postJson(self::ORDERS."/{$order->id}/payments", ['amount' => $amount, 'payment_date' => self::TODAY]);
    }

    /** $order's deliverables as its page gives them, by title. */
    private function deliverables(EcommerceOrder $order): array
    {
        return collect($this->getJson(self::ORDERS."/{$order->id}")->assertOk()->json('data.deliverables'))->keyBy('title')->all();
    }

    /** The newest e-mail of $kind, rendered: its subject and HTML. */
    private function mailOf(string $kind): array
    {
        $mail = Mail::sent(StoreOrderMail::class, fn (StoreOrderMail $mail) => $mail->order['kind'] === $kind)->last();

        return [$mail->mailSubject, $mail->render()];
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

    public function test_digital_products_are_delivered_once_paid_as_their_items_say(): void
    {
        $order = $this->placed(['recipe-e-book' => 1, 'online-course' => 1, 'photo-pack' => 2, 'logo-design' => 1], '44.00');

        // Unpaid: nothing delivered, nothing protected shown.
        $this->assertSame('unfulfilled', $order->fresh()->fulfilment_status);
        $this->assertSame(['awaiting_payment'], array_values(array_unique(array_column($this->deliverables($order), 'state'))));
        $this->assertSame(['download', 'external_link', 'license_key', 'manual'], array_column($this->deliverables($order), 'method'));
        $store = $this->getJson(self::STORE.'/orders/'.$this->keys[$order->id])->assertOk()->json('data.deliverables');
        $this->assertSame([null], array_values(array_unique(array_column($store, 'instructions'))));
        $this->act($order, 'Photo Pack', 'deliver', ['license_keys' => ['PP-1111']])->assertStatus(409)
            ->assertJsonPath('message', 'Order EC-00001 is not paid in full: its digital products are delivered once it is.');

        // Paid: the download and the link at once; the keys and the delivery by hand wait for the business.
        $this->pay($order, '44.00')->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.fulfilment_status', 'partially_fulfilled')
            ->assertJsonPath('data.status', 'processing');
        $this->assertSame(['delivered', 'delivered', 'waiting', 'waiting'], array_column($this->deliverables($order), 'state'));
        [$subject, $html] = $this->mailOf(EcommerceOrderEmail::DIGITAL_DELIVERED);
        $this->assertSame('Your digital products from order EC-00001 at Rahman Shop are ready', $subject);
        foreach (['Here is what we delivered from your order <strong>EC-00001</strong>', 'Recipe E-Book: ready to download from your order', 'Open the PDF on any device.', 'Join at https://course.example/rahman'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('Photo Pack', $html);
        $this->assertContains('Delivered Recipe E-Book and Online Course of online order EC-00001, paid in full', $this->getJson(self::ORDERS."/{$order->id}/activity")->json('data.*.description'));

        // The license keys, blank lines dropped; kept encrypted.
        $this->act($order, 'Photo Pack', 'deliver', ['license_keys' => [' ', '']])->assertUnprocessable()
            ->assertJsonPath('errors.license_keys.0', 'Give its license key: one for each one bought, usually.');
        $this->act($order, 'Photo Pack', 'deliver', ['license_keys' => ['PP-1111', ' ', 'PP-2222 '], 'note' => 'Activate them in the app.'])->assertOk()
            ->assertJsonPath('message', 'Photo Pack of order EC-00001 is delivered, and the customer told.');
        $this->assertSame(['PP-1111', 'PP-2222'], $this->deliverables($order)['Photo Pack']['license_keys']);
        $this->assertStringNotContainsString('PP-1111', (string) DB::table('ecommerce_order_deliveries')->where('ecommerce_order_line_id', $this->line($order, 'Photo Pack'))->value('license_keys'));
        [$subject, $html] = $this->mailOf(EcommerceOrderEmail::DIGITAL_DELIVERED);
        $this->assertSame('Photo Pack from your order EC-00001 at Rahman Shop is ready', $subject);
        foreach (['Photo Pack - your license keys:', 'PP-1111', 'PP-2222', 'Activate them in the app.'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->getJson(self::ORDERS."/{$order->id}/activity")->assertJsonPath('data.0.description', 'Gave the license keys of Photo Pack of online order EC-00001');

        // By hand, with a note: all of it reached the customer, and it is paid - completed.
        $this->act($order, 'Logo Design', 'deliver')->assertUnprocessable()->assertJsonPath('errors.note.0', 'Say how it reached the customer: they are e-mailed this note.');
        $this->act($order, 'Logo Design', 'deliver', ['note' => 'We e-mailed your logo files to you on Monday.'])->assertOk()
            ->assertJsonPath('data.fulfilment_status', 'fulfilled')
            ->assertJsonPath('data.status', 'completed');
        $this->act($order, 'Logo Design', 'deliver', ['note' => 'Again.'])->assertStatus(409)->assertJsonPath('message', 'Logo Design of order EC-00001 is delivered already.');

        // The customer's order page: each as it stands.
        $store = collect($this->getJson(self::STORE.'/orders/'.$this->keys[$order->id])->assertOk()->json('data.deliverables'))->keyBy('title');
        $this->assertSame(['delivered', 'Open the PDF on any device.'], [$store['Recipe E-Book']['state'], $store['Recipe E-Book']['instructions']]);
        $this->assertSame('Join at https://course.example/rahman', $store['Online Course']['instructions']);
        $this->assertSame(['PP-1111', 'PP-2222'], $store['Photo Pack']['license_keys']);
        $this->assertSame('We e-mailed your logo files to you on Monday.', $store['Logo Design']['note']);
    }

    public function test_access_is_revoked_without_a_word_to_the_customer_and_given_again(): void
    {
        $order = $this->placed(['recipe-e-book' => 1], '4.00');
        $this->pay($order, '4.00')->assertOk()->assertJsonPath('data.status', 'completed');
        $sent = EcommerceOrderEmail::count();

        $this->act($order, 'Recipe E-Book', 'revoke')->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->act($order, 'Recipe E-Book', 'revoke', ['reason' => 'Chargeback.'])->assertOk()
            ->assertJsonPath('message', 'Access to Recipe E-Book of order EC-00001 is revoked.')
            ->assertJsonPath('data.deliverables.0.state', 'revoked')
            ->assertJsonPath('data.deliverables.0.revoke_reason', 'Chargeback.')
            ->assertJsonPath('data.status', 'completed');
        $this->assertSame($sent, EcommerceOrderEmail::count());
        $this->getJson(self::STORE.'/orders/'.$this->keys[$order->id])->assertJsonPath('data.deliverables.0.state', 'revoked')
            ->assertJsonPath('data.deliverables.0.instructions', null)
            ->assertJsonMissingPath('data.deliverables.0.revoke_reason');
        $this->getJson(self::ORDERS."/{$order->id}/activity")->assertJsonPath('data.0.description', 'Revoked access to Recipe E-Book of online order EC-00001: Chargeback.');
        $this->act($order, 'Recipe E-Book', 'revoke', ['reason' => 'Again.'])->assertStatus(409);

        $this->act($order, 'Recipe E-Book', 'deliver')->assertOk()->assertJsonPath('data.deliverables.0.state', 'delivered')->assertJsonPath('data.deliverables.0.revoke_reason', null);
        $this->getJson(self::ORDERS."/{$order->id}/activity")->assertJsonPath('data.0.description', 'Gave access to Recipe E-Book of online order EC-00001 again');
        $this->assertSame('Recipe E-Book from your order EC-00001 at Rahman Shop is ready', $this->mailOf(EcommerceOrderEmail::DIGITAL_DELIVERED)[0]);
    }

    public function test_a_service_is_performed_on_a_day_paid_or_not(): void
    {
        $waiting = $this->placed(['consultation' => 1], '15.00', confirm: false);
        $this->act($waiting, 'Consultation', 'perform', ['performed_on' => self::TODAY])->assertStatus(409)->assertJsonPath('message', 'Order EC-00001 is pending: confirm it first.');
        $this->postJson(self::ORDERS."/{$waiting->id}/confirm")->assertOk();

        $this->assertSame('to_perform', $this->deliverables($waiting)['Consultation']['state']);
        $this->act($waiting, 'Consultation', 'deliver')->assertStatus(409)->assertJsonPath('message', 'Consultation is a service: mark it performed.');
        $this->act($waiting, 'Consultation', 'perform', ['performed_on' => '2026-11-28'])->assertUnprocessable()->assertJsonPath('errors.performed_on.0', 'Give a day from 2026-11-27 to today.');
        $this->act($waiting, 'Consultation', 'perform', ['performed_on' => self::TODAY, 'note' => 'Two hours, by video.'])->assertOk()
            ->assertJsonPath('message', 'Consultation of order EC-00001 is performed, and the customer told.')
            ->assertJsonPath('data.fulfilment_status', 'fulfilled')
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonPath('data.deliverables.0.performed_on', self::TODAY);
        [$subject, $html] = $this->mailOf(EcommerceOrderEmail::SERVICE_PERFORMED);
        $this->assertSame('Consultation from your order EC-00001 at Rahman Shop is done', $subject);
        foreach (['This service from your order <strong>EC-00001</strong> was performed on', 'Two hours, by video.', 'Payment: Bank Transfer'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->act($waiting, 'Consultation', 'perform', ['performed_on' => self::TODAY])->assertStatus(409)->assertJsonPath('message', 'Consultation of order EC-00001 is performed already.');
        $this->act($waiting, 'Consultation', 'revoke', ['reason' => 'No.'])->assertStatus(409)->assertJsonPath('message', 'Consultation is a service: there is no access to revoke.');
        $this->getJson(self::STORE.'/orders/'.$this->keys[$waiting->id])->assertJsonPath('data.deliverables.0', [
            'title' => 'Consultation', 'options' => [], 'quantity' => 1, 'kind' => 'service', 'method' => null, 'state' => 'performed',
            'license_keys' => [], 'note' => 'Two hours, by video.', 'instructions' => null, 'performed_on' => self::TODAY, 'download' => null,
        ]);

        // Paid after: completed.
        $this->pay($waiting, '15.00')->assertOk()->assertJsonPath('data.status', 'completed');
    }

    public function test_an_order_that_ships_and_delivers_completes_once_all_of_it_is_with_its_customer_and_paid(): void
    {
        $order = $this->placed(['ceramic-mug' => 1, 'recipe-e-book' => 1], '17.00', ['delivery' => 'shipping', 'shipping_method_id' => $this->ids['Standard'], 'payment_method_id' => $this->ids['Cash on Delivery']]);
        $mug   = $this->line($order, 'Ceramic Mug');

        $this->postJson(self::ORDERS."/{$order->id}/shipments", ['lines' => [['id' => $mug, 'quantity' => 1]], 'shipped_on' => self::TODAY])->assertOk()
            ->assertJsonPath('data.fulfilment_status', 'partially_fulfilled')
            ->assertJsonPath('data.status', 'processing');
        $shipment = $this->getJson(self::ORDERS."/{$order->id}")->json('data.fulfilments.0.id');
        $this->postJson(self::ORDERS."/{$order->id}/fulfilments/{$shipment}/complete", ['completed_on' => self::TODAY])->assertOk()
            ->assertJsonPath('data.fulfilment_status', 'partially_fulfilled');

        // Paid on delivery: the e-book goes at once, and the order is delivered and completed.
        $this->pay($order, '17.00')->assertOk()
            ->assertJsonPath('data.fulfilment_status', 'delivered')
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_delivering_needs_manage_fulfilment_at_the_order_s_location(): void
    {
        $order     = $this->placed(['photo-pack' => 1], '5.00');
        $elsewhere = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Chattogram', 'code' => 'CTG']);
        $this->pay($order, '5.00')->assertOk();

        Sanctum::actingAs($this->member(['ecommerce.orders.view']));
        $this->getJson(self::ORDERS."/{$order->id}")->assertOk()->assertJsonPath('data.deliverables.0.state', 'waiting');
        $this->act($order, 'Photo Pack', 'deliver', ['license_keys' => ['PP-1']])->assertForbidden();

        Sanctum::actingAs($this->member(['ecommerce.fulfilment.manage'], [$elsewhere->id]));
        $this->act($order, 'Photo Pack', 'deliver', ['license_keys' => ['PP-1']])->assertNotFound();

        Sanctum::actingAs($this->member(['ecommerce.fulfilment.manage'], [$this->store->location_id]));
        $this->postJson(self::ORDERS."/{$order->id}/lines/99999/deliver", ['license_keys' => ['PP-1']])->assertNotFound()
            ->assertJsonPath('message', 'There is no such digital product or service on the order.');
        $this->act($order, 'Photo Pack', 'deliver', ['license_keys' => ['PP-1']])->assertOk()->assertJsonPath('data.status', 'completed');
    }
}
