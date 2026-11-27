<?php

namespace Tests\Feature;

use App\Mail\StoreOrderMail;
use App\Models\User\CatalogProduct;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderEmail;
use App\Models\User\PaymentAccount;
use App\Models\User\SalesDocument;
use App\Models\User\SalesPayment;
use App\Models\User\User;
use App\Models\User\WorkspaceMember;
use App\Services\Documents\DocumentFormat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\ChecksOutAStore;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * An online order's payment, received or failed (Doc 19 sections 19.20 and
 * 19.22 - Phase F3), under Confirm Orders: recorded as a customer payment of
 * its invoice into its payment method's account - a pending order confirmed
 * first - or marked failed with why; the order's payment status following
 * its invoice, wherever the invoice is paid or a payment reversed; and the
 * customer e-mailed either way.
 */
class EcommerceOrderPaymentsTest extends TestCase
{
    use ChecksOutAStore;
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsIntoBusinesses;

    private const ORDERS = '/api/v1/ecommerce/orders';

    private const TODAY = '2026-11-24';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo(self::TODAY.' 10:00:00');
        $this->openStorefront(['Ceramic Mug' => ['sale_price' => '8.00']]);
        $this->listed('Ceramic Mug', ['availability' => CatalogProduct::LIMITED, 'stock_quantity' => '10']);
        $this->setUpCheckout();
    }

    /** An order of two mugs ($21.00) placed by a guest with checkout's $fields - by bank transfer with TRX-1001 unless they say otherwise - and the owner signed in again. */
    private function placed(array $fields = ['payment_reference' => 'TRX-1001']): EcommerceOrder
    {
        $this->place($this->cartWith(['ceramic-mug' => 2]), $this->order('21.00', $fields))->assertCreated();
        Sanctum::actingAs($this->user);

        return EcommerceOrder::query()->latest('id')->firstOrFail();
    }

    private function pay(EcommerceOrder $order, string $amount, array $fields = [])
    {
        return $this->postJson(self::ORDERS."/{$order->id}/payments", ['amount' => $amount, 'payment_date' => self::TODAY, 'reference' => 'TRX-1001', ...$fields]);
    }

    /** $amount of USD as the order's e-mails write it - with the currency's symbol where the platform's currencies give one. */
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

    public function test_a_payment_recorded_pays_the_invoice_into_the_method_s_account_and_the_order_follows_it(): void
    {
        $order = $this->placed();
        $this->postJson(self::ORDERS."/{$order->id}/confirm")->assertOk();
        $bank  = PaymentAccount::where('name', 'City Bank')->sole();

        $this->getJson(self::ORDERS."/{$order->id}/payment")->assertOk()
            ->assertJsonPath('data', [
                'ready' => true, 'problems' => [], 'balance' => '21.00', 'amount' => '21.00', 'currency' => 'USD', 'account' => 'City Bank', 'method' => 'Bank Transfer',
                'reference' => 'TRX-1001', 'report' => null, 'today' => self::TODAY, 'from' => self::TODAY, 'confirmation' => null,
            ]);

        // Not before its invoice, nor more than it owes.
        $this->pay($order, '10.00', ['payment_date' => '2026-11-23'])->assertUnprocessable()->assertJsonValidationErrors('payment_date');

        $this->pay($order, '10.00')->assertOk()
            ->assertJsonPath('message', 'Payment RCT-00001 recorded for order EC-00001.')
            ->assertJsonPath('data.payment_status', 'partially_paid')
            ->assertJsonPath('data.invoice.balance_due', '11.00')
            ->assertJsonPath('data.payments', [['id' => SalesPayment::sole()->id, 'number' => 'RCT-00001', 'date' => self::TODAY, 'amount' => '10.00', 'currency' => 'USD', 'account' => 'City Bank', 'reference' => 'TRX-1001', 'reversed' => false]]);
        $this->assertSame([$bank->id, 'bank_transfer', $order->fresh()->sales_document_id], [SalesPayment::sole()->payment_account_id, SalesPayment::sole()->method, SalesPayment::sole()->sales_document_id]);

        $this->pay($order, '12.00')->assertUnprocessable()->assertJsonPath('errors.amount.0', 'Order EC-00001 owes USD 11.00: record that much at most.');

        $this->pay($order, '11.00')->assertOk()->assertJsonPath('data.payment_status', 'paid')->assertJsonPath('data.invoice.balance_due', '0.00');
        $this->assertSame('paid', SalesDocument::sole()->payment_status);
        $this->getJson(self::ORDERS."/{$order->id}/activity")->assertJsonPath('data.0.description', 'Recorded payment RCT-00002 of USD 11.00 for online order EC-00001');

        // Paid: nothing more is taken, or marked failed.
        $this->getJson(self::ORDERS."/{$order->id}/payment")->assertStatus(409)->assertJsonPath('message', 'Order EC-00001 is paid already.');
        $this->pay($order, '1.00')->assertStatus(409)->assertJsonPath('message', 'Order EC-00001 is paid already.');
        $this->postJson(self::ORDERS."/{$order->id}/payment-failed", ['reason' => 'Bounced.'])->assertStatus(409)->assertJsonPath('message', 'Order EC-00001 is paid already.');
        $this->assertSame(2, SalesPayment::count());
    }

    public function test_a_pending_order_is_confirmed_first_and_its_customer_told_both(): void
    {
        $order = $this->placed();

        $this->getJson(self::ORDERS."/{$order->id}/payment")->assertOk()
            ->assertJsonPath('data.ready', true)
            ->assertJsonPath('data.from', self::TODAY)
            ->assertJsonPath('data.confirmation.stock', [['title' => 'Ceramic Mug', 'left' => '10.000', 'after' => '8.000']])
            ->assertJsonPath('data.confirmation.invoice.total', '21.00');

        $this->pay($order, '21.00')->assertOk()
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.invoice.number', 'INV-00001');
        $this->assertSame(['paid', '8.000'], [SalesDocument::sole()->payment_status, (string) CatalogProduct::sole()->stock_quantity]);
        $this->assertSame(['order_received', 'order_confirmed', 'payment_received'], EcommerceOrderEmail::orderBy('id')->pluck('kind')->all());

        $link = rtrim((string) config('app.url'), '/').'/d/'.SalesDocument::sole()->activeLink->token;
        Mail::assertSent(StoreOrderMail::class, function (StoreOrderMail $mail) use ($link) {
            $html = $mail->render();

            return $mail->mailSubject === 'Payment received for your order EC-00001 at Rahman Shop'
                && str_contains($html, "We have received your payment of <strong>{$this->money('21.00')}</strong> for your order <strong>EC-00001</strong>, reference TRX-1001.")
                && str_contains($html, 'Nothing is left to pay.')
                && ! str_contains($html, 'Pay into City Bank')
                && str_contains($html, $link);
        });
    }

    public function test_a_pending_order_that_cannot_be_confirmed_takes_no_payment(): void
    {
        $order = $this->placed();
        $this->items['Ceramic Mug']->update(['is_active' => false]);

        $this->getJson(self::ORDERS."/{$order->id}/payment")->assertOk()
            ->assertJsonPath('data.ready', false)
            ->assertJsonPath('data.problems', ['Ceramic Mug is inactive in Items: make it active, then confirm the order.']);
        $this->pay($order, '21.00')->assertStatus(409)->assertJsonPath('message', 'Ceramic Mug is inactive in Items: make it active, then confirm the order.');

        $this->assertSame([0, 0, 'pending', 'pending'], [SalesDocument::count(), SalesPayment::count(), $order->fresh()->status, $order->fresh()->payment_status]);
    }

    public function test_a_payment_marked_failed_is_counted_and_its_customer_told_without_the_why(): void
    {
        $order = $this->placed();

        $this->postJson(self::ORDERS."/{$order->id}/payment-failed", [])->assertUnprocessable()->assertJsonValidationErrors('reason');

        $this->postJson(self::ORDERS."/{$order->id}/payment-failed", ['reason' => 'No transfer with TRX-1001 reached City Bank.'])->assertOk()
            ->assertJsonPath('message', 'The payment of order EC-00001 is marked failed, and the customer told.')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payment_status', 'failed')
            ->assertJsonPath('data.payment_failures', 1)
            ->assertJsonPath('data.payment_failure', 'No transfer with TRX-1001 reached City Bank.');
        $this->getJson(self::ORDERS."/{$order->id}/activity")->assertJsonPath('data.0.description', 'Marked the payment of online order EC-00001 as failed: No transfer with TRX-1001 reached City Bank.');

        Mail::assertSent(StoreOrderMail::class, function (StoreOrderMail $mail) {
            $html = $mail->render();

            return $mail->mailSubject === 'Your payment for order EC-00001 at Rahman Shop was not received'
                && str_contains($html, 'We could not confirm your payment for your order <strong>EC-00001</strong>')
                && str_contains($html, e("Pay {$this->money('21.00')}, with your order number, EC-00001, as the payment's reference."))
                && str_contains($html, 'Pay into City Bank 0123456789.')
                && ! str_contains($html, 'reached City Bank')
                && ! str_contains($html, 'See your');
        });

        // Failed again, then paid: counted twice, and paid in the end.
        $this->postJson(self::ORDERS."/{$order->id}/payment-failed", ['reason' => 'Still nothing.'])->assertOk()->assertJsonPath('data.payment_failures', 2);
        $this->pay($order, '21.00')->assertOk()->assertJsonPath('data.payment_status', 'paid')->assertJsonPath('data.payment_failures', 2);
    }

    public function test_the_order_follows_its_invoice_paid_or_reversed_in_sales(): void
    {
        $order = $this->placed();
        $this->postJson(self::ORDERS."/{$order->id}/confirm")->assertOk();
        $invoice = SalesDocument::sole();
        $bank    = PaymentAccount::where('name', 'City Bank')->sole();

        $this->postJson("/api/v1/sales/invoices/{$invoice->id}/payments", ['payment_date' => self::TODAY, 'payment_account_id' => $bank->id, 'amount' => '5.00'])->assertCreated();
        $this->assertSame('partially_paid', $order->fresh()->payment_status);

        $this->postJson('/api/v1/sales/payments/'.SalesPayment::sole()->id.'/reverse', ['reason' => 'Recorded twice.'])->assertOk();
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->getJson(self::ORDERS."/{$order->id}")->assertJsonPath('data.payments.0.reversed', true)->assertJsonPath('data.invoice.balance_due', '21.00');

        $this->postJson("/api/v1/sales/invoices/{$invoice->id}/payments", ['payment_date' => self::TODAY, 'payment_account_id' => $bank->id, 'amount' => '21.00'])->assertCreated();
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_payments_need_confirm_orders_at_the_order_s_location_and_go_into_the_method_s_account(): void
    {
        $order     = $this->placed(['payment_method_id' => $this->ids['Cash on Delivery']]);
        $elsewhere = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Chattogram', 'code' => 'CTG']);

        Sanctum::actingAs($this->member(['ecommerce.orders.view']));
        $this->getJson(self::ORDERS."/{$order->id}/payment")->assertForbidden();
        $this->pay($order, '21.00')->assertForbidden();
        $this->postJson(self::ORDERS."/{$order->id}/payment-failed", ['reason' => 'Refused at the door.'])->assertForbidden();

        Sanctum::actingAs($this->member(['ecommerce.orders.confirm'], [$elsewhere->id]));
        $this->getJson(self::ORDERS."/{$order->id}/payment")->assertNotFound();
        $this->pay($order, '21.00')->assertNotFound();

        // At the store's location: cash on delivery, into Cash Box.
        Sanctum::actingAs($this->member(['ecommerce.orders.confirm'], [$this->store->location_id]));
        $this->getJson(self::ORDERS."/{$order->id}/payment")->assertOk()->assertJsonPath('data.account', 'Cash Box')->assertJsonPath('data.reference', null);
        $this->pay($order, '21.00', ['reference' => null])->assertOk()->assertJsonPath('data.payment_status', 'paid');
        $this->assertSame([PaymentAccount::where('name', 'Cash Box')->value('id'), 'cash'], [SalesPayment::sole()->payment_account_id, SalesPayment::sole()->method]);
    }
}
