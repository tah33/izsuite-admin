<?php

namespace Tests\Feature;

use App\Models\User\CatalogProduct;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderEmail;
use App\Models\User\EcommercePaymentLink;
use App\Models\User\PaymentAccount;
use App\Models\User\SalesPayment;
use App\Mail\StoreOrderMail;
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
 * A payment link's customer saying they paid (Doc 19 section 19.23 - Phase
 * F6b): on the link's page, the way they paid among its methods and their
 * reference, as that method asks - once; the order's payment Pending with it
 * unless part is paid; the link reported, neither revoked nor replaced, nor
 * the order edited, until the business records the payment - from the
 * report, into the account of the way they paid, the link then Paid - or
 * marks it not received, which clears the report for another.
 */
class EcommercePaymentLinkReportsTest extends TestCase
{
    use ChecksOutAStore;
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsIntoBusinesses;

    private const ORDERS = '/api/v1/ecommerce/orders';

    private const TODAY = '2026-11-27';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo(self::TODAY.' 10:00:00');
        $this->openStorefront(['Ceramic Mug' => ['sale_price' => '8.00']]);
        $this->listed('Ceramic Mug', ['availability' => CatalogProduct::LIMITED, 'stock_quantity' => '10']);
        $this->setUpCheckout();
    }

    /** An order of two mugs ($21.00), shipped and paid by bank transfer - checkout's $fields over that - and the owner signed in again. */
    private function placed(array $fields = []): EcommerceOrder
    {
        $this->place($this->cartWith(['ceramic-mug' => 2]), $this->order('21.00', $fields))->assertCreated();
        Sanctum::actingAs($this->user);

        return EcommerceOrder::query()->latest('id')->firstOrFail();
    }

    /** Order $order sent a link of $amount by Bank Transfer and bKash: its page's path on the store. */
    private function linked(EcommerceOrder $order, string $amount = '21.00'): string
    {
        $this->postJson(self::ORDERS."/{$order->id}/payment-links", [
            'amount' => $amount, 'days' => 7, 'payment_method_ids' => [$this->ids['Bank Transfer'], $this->ids['bKash']],
        ])->assertOk();

        $mail = Mail::sent(StoreOrderMail::class, fn (StoreOrderMail $mail) => $mail->order['kind'] === EcommerceOrderEmail::PAYMENT_LINK)->last();

        return self::STORE.'/pay/'.Str::afterLast($mail->order['link'], '/');
    }

    /** The customer, on the link's page $page, saying they paid by $method with $reference. */
    private function report(string $page, ?string $method, ?string $reference = null)
    {
        return $this->postJson($page, ['payment_method_id' => $method === null ? null : $this->ids[$method], 'reference' => $reference]);
    }

    private function link(EcommerceOrder $order): array
    {
        return $this->getJson(self::ORDERS."/{$order->id}")->assertOk()->json('data.payment_links.0');
    }

    public function test_the_customer_reports_their_payment_once_and_the_order_waits_for_the_business(): void
    {
        $order = $this->placed();
        $page  = $this->linked($order);

        $this->report($page, null)->assertUnprocessable()->assertJsonPath('errors.payment_method_id.0', 'Choose the way you paid.');
        $this->report($page, 'Cash on Delivery')->assertUnprocessable()->assertJsonPath('errors.payment_method_id.0', 'Choose one of the ways to pay this link offers.');
        $this->report($page, 'bKash')->assertUnprocessable()->assertJsonPath('errors.reference.0', "Give your payment's reference (its transaction ID).");

        $this->report($page, 'bKash', ' BK-555 ')->assertOk()
            ->assertJsonPath('message', 'Thank you: we have your payment, and will e-mail you once it is confirmed.')
            ->assertJsonPath('data.state', 'reported')
            ->assertJsonPath('data.reported', ['method' => 'bKash', 'reference' => 'BK-555'])
            ->assertJsonPath('data.methods', []);
        $this->getJson($page)->assertJsonPath('data.state', 'reported');

        // Waiting for the business, with their reference - nothing recorded as paid.
        $this->assertSame(['pending', 'BK-555', 0], [$order->fresh()->payment_status, $order->fresh()->payment_reference, SalesPayment::count()]);
        $this->assertSame(['state' => 'reported', 'reported' => ['at' => '2026-11-27T10:00:00+00:00', 'method' => 'bKash', 'reference' => 'BK-555']], array_intersect_key($this->link($order), ['state' => 0, 'reported' => 0]));
        $this->getJson(self::ORDERS."/{$order->id}/activity")
            ->assertJsonPath('data.0.description', 'Reported paying USD 21.00 for online order EC-00001 by bKash, reference BK-555, through its payment link')
            ->assertJsonPath('data.0.by', 'The customer');

        // Once - and the report stands until the business settles it: no revoking, no new link, no edits.
        $this->report($page, 'Bank Transfer', 'TRX-2')->assertStatus(409)->assertJsonPath('message', 'We have your payment already: we will confirm it and e-mail you.');
        $waits = 'The customer reported paying through the link of USD 21.00 for order EC-00001: record their payment, or mark it not received, first.';
        $this->postJson(self::ORDERS."/{$order->id}/payment-links/".EcommercePaymentLink::sole()->id.'/revoke')->assertStatus(409)->assertJsonPath('message', $waits);
        $this->getJson(self::ORDERS."/{$order->id}/payment-link")->assertStatus(409)->assertJsonPath('message', $waits);
        $this->postJson(self::ORDERS."/{$order->id}/payment-links", ['amount' => '21.00', 'days' => 7, 'payment_method_ids' => [$this->ids['bKash']]])->assertStatus(409);
        $this->putJson(self::ORDERS."/{$order->id}/discount", ['type' => 'amount', 'value' => '1.00', 'reason' => 'Loyal.'])->assertStatus(409)
            ->assertJsonPath('message', 'The customer of order EC-00001 reported paying through its payment link: record their payment, or mark it not received, before editing the order.');
        $this->assertSame(1, EcommercePaymentLink::count());
    }

    public function test_record_payment_starts_from_the_report_into_the_account_of_the_way_they_paid(): void
    {
        $order = $this->placed(['payment_reference' => 'TRX-1001']);
        $page  = $this->linked($order);
        $this->report($page, 'bKash', 'BK-555')->assertOk();

        $this->getJson(self::ORDERS."/{$order->id}/payment")->assertOk()
            ->assertJsonPath('data.ready', true)
            ->assertJsonPath('data.amount', '21.00')
            ->assertJsonPath('data.account', 'Cash Box')
            ->assertJsonPath('data.method', 'bKash')
            ->assertJsonPath('data.reference', 'BK-555')
            ->assertJsonPath('data.report', ['at' => '2026-11-27T10:00:00+00:00', 'method' => 'bKash', 'reference' => 'BK-555']);

        $this->postJson(self::ORDERS."/{$order->id}/payments", ['amount' => '21.00', 'payment_date' => self::TODAY, 'reference' => 'BK-555'])->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.payment_links.0.state', 'paid')
            ->assertJsonPath('data.payment_links.0.reported.reference', 'BK-555');
        $this->assertSame([PaymentAccount::where('name', 'Cash Box')->value('id'), 'mobile_money', 'BK-555'], [SalesPayment::sole()->payment_account_id, SalesPayment::sole()->method, SalesPayment::sole()->reference]);
        $this->getJson(self::ORDERS."/{$order->id}/activity")->assertJsonPath('data.0.description', 'Recorded payment RCT-00001 of USD 21.00 for online order EC-00001, reported through its payment link');

        // Paid: never paid again.
        $this->getJson($page)->assertJsonPath('data.state', 'paid');
        $this->report($page, 'bKash', 'BK-556')->assertStatus(409)->assertJsonPath('message', 'Order EC-00001 is paid: there is nothing more to pay through this link.');
    }

    public function test_payment_not_received_clears_the_report_and_the_link_takes_another(): void
    {
        $order = $this->placed();
        $page  = $this->linked($order);

        // Bank Transfer's reference is optional: none given, the order's is left as it was.
        $this->report($page, 'Bank Transfer')->assertOk()->assertJsonPath('data.reported', ['method' => 'Bank Transfer', 'reference' => null]);
        $this->assertSame(['pending', null], [$order->fresh()->payment_status, $order->fresh()->payment_reference]);

        $this->postJson(self::ORDERS."/{$order->id}/payment-failed", ['reason' => 'Nothing reached City Bank.'])->assertOk()
            ->assertJsonPath('data.payment_status', 'failed')
            ->assertJsonPath('data.payment_failures', 1)
            ->assertJsonPath('data.payment_links.0.state', 'active')
            ->assertJsonPath('data.payment_links.0.reported', null);
        $this->getJson(self::ORDERS."/{$order->id}/activity")->assertJsonPath('data.0.description', 'Marked the payment reported through the payment link of online order EC-00001 as failed: Nothing reached City Bank.');
        $this->assertSame(['order_received', 'payment_link', 'payment_failed'], EcommerceOrderEmail::orderBy('id')->pluck('kind')->all());

        // The link takes another report while it works.
        $this->getJson($page)->assertJsonPath('data.state', 'active')->assertJsonPath('data.methods.*.name', ['Bank Transfer', 'bKash']);
        $this->report($page, 'bKash', 'BK-777')->assertOk()->assertJsonPath('data.state', 'reported');
        $this->assertSame(['pending', 'BK-777'], [$order->fresh()->payment_status, $order->fresh()->payment_reference]);
    }

    public function test_a_part_paid_order_s_report_keeps_its_status_and_may_be_marked_not_received(): void
    {
        $order = $this->placed(['payment_reference' => 'TRX-1001']);
        $this->postJson(self::ORDERS."/{$order->id}/payments", ['amount' => '10.00', 'payment_date' => self::TODAY, 'reference' => 'TRX-1001'])->assertOk();
        // Without a report, a part-paid order's payment is not marked failed.
        $this->postJson(self::ORDERS."/{$order->id}/payment-failed", ['reason' => 'Short.'])->assertStatus(409)
            ->assertJsonPath('message', 'Part of order EC-00001 is paid: record the rest, or reverse its payment in Sales first.');

        $page = $this->linked($order, '11.00');
        $this->report($page, 'bKash', 'BK-900')->assertOk();
        $this->assertSame('partially_paid', $order->fresh()->payment_status);
        $this->getJson(self::ORDERS."/{$order->id}/payment")->assertJsonPath('data.amount', '11.00')->assertJsonPath('data.account', 'Cash Box');

        $this->postJson(self::ORDERS."/{$order->id}/payment-failed", ['reason' => 'BK-900 is not ours.'])->assertOk()
            ->assertJsonPath('data.payment_status', 'partially_paid')
            ->assertJsonPath('data.payment_failures', 1)
            ->assertJsonPath('data.payment_links.0.state', 'active');
    }

    public function test_a_link_that_no_longer_takes_payment_refuses_a_report(): void
    {
        $order = $this->placed();
        $page  = $this->linked($order);

        $this->postJson(self::ORDERS."/{$order->id}/payment-links/".EcommercePaymentLink::sole()->id.'/revoke')->assertOk();
        $this->report($page, 'bKash', 'BK-1')->assertStatus(409)->assertJsonPath('message', 'This link is no longer in use: ask us for a new one.');

        $page = $this->linked($order);
        $this->travelTo('2026-12-05 00:00:01');
        $this->report($page, 'bKash', 'BK-2')->assertStatus(409)->assertJsonPath('message', 'This link has expired: ask us for a new one.');
        $this->getJson(self::STORE.'/pay/'.str_repeat('b', 64))->assertNotFound();
        $this->postJson(self::STORE.'/pay/'.str_repeat('b', 64), ['payment_method_id' => $this->ids['bKash'], 'reference' => 'BK-3'])->assertNotFound();
    }

    public function test_a_report_waits_for_the_account_of_the_way_they_paid_and_goes_with_the_order_cancelled(): void
    {
        $order = $this->placed();
        $page  = $this->linked($order);
        $this->report($page, 'bKash', 'BK-555')->assertOk();

        // bKash takes money into Cash Box, no longer taking customer payments: recorded in Sales instead.
        PaymentAccount::where('name', 'Cash Box')->update(['is_active' => false]);
        $noAccount = 'The account bKash takes money into is no longer active for customer payments here: record this payment in Sales, into an account of your choice.';
        $this->getJson(self::ORDERS."/{$order->id}/payment")->assertJsonPath('data.ready', false)->assertJsonPath('data.problems', [$noAccount]);
        $this->postJson(self::ORDERS."/{$order->id}/payments", ['amount' => '21.00', 'payment_date' => self::TODAY, 'reference' => 'BK-555'])->assertStatus(409)->assertJsonPath('message', $noAccount);

        // Cancelled, its link with it - the report kept on it - and the customer told the business will be in touch.
        $this->postJson(self::ORDERS."/{$order->id}/cancel", ['reason' => 'The customer asked us to.'])->assertOk()
            ->assertJsonPath('data.payment_links.0.state', 'revoked')
            ->assertJsonPath('data.payment_links.0.revoked_why', 'cancelled')
            ->assertJsonPath('data.payment_links.0.reported.reference', 'BK-555');
        $this->getJson($page)->assertJsonPath('data.state', 'revoked');
        Mail::assertSent(StoreOrderMail::class, fn (StoreOrderMail $mail) => $mail->order['kind'] === EcommerceOrderEmail::ORDER_CANCELLED
            && str_contains($mail->render(), 'If you have paid for it, we will be in touch about your money.'));
    }
}
