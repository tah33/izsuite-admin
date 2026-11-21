<?php

namespace Tests\Feature;

use App\Jobs\SendStoreOrderEmail;
use App\Mail\StoreOrderMail;
use App\Models\User\EcommerceOrderEmail;
use App\Models\User\Storefront;
use App\Services\Api\Ecommerce\StoreOrderMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\ChecksOutAStore;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 19 Phase D5: an order's confirmation (sections 19.20 and 19.35) - the
 * order-received e-mail, through the queue, kept with the order as sent or
 * failed - and the order's own page, by the key its link carries.
 */
class EcommerceOrderConfirmationTest extends TestCase
{
    use ChecksOutAStore;
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsIntoBusinesses;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.frontend_url' => 'https://app.izsuite.test/']);
        $this->openStorefront(['Ceramic Mug' => ['sale_price' => '8.00']]);
        $this->listed('Ceramic Mug');
        $this->setUpCheckout();
        $this->store->update(['support_email' => 'help@rahman.test', 'support_phone' => '+8801700000000']);
    }

    public function test_an_order_queues_its_confirmation_once_with_its_own_link(): void
    {
        Queue::fake();
        $token = $this->cartWith(['ceramic-mug' => 2]);

        $key = $this->place($token, $this->order('21.00'))->assertCreated()->json('data.key');

        $email = EcommerceOrderEmail::sole();
        $this->assertSame([EcommerceOrderEmail::ORDER_RECEIVED, 'Karim@Example.test', EcommerceOrderEmail::QUEUED], [$email->kind, $email->to, $email->status]);
        Queue::assertPushed(SendStoreOrderEmail::class, fn (SendStoreOrderEmail $job) => $job->emailId === $email->id && $job->link === "https://app.izsuite.test/store/rahman-shop/orders/{$key}");

        // Placed again: the same order, no second e-mail.
        $this->place($token, $this->order('21.00'))->assertOk();
        Queue::assertPushed(SendStoreOrderEmail::class, 1);
        $this->assertSame(1, EcommerceOrderEmail::count());
    }

    public function test_the_confirmation_goes_from_the_store_and_is_kept_as_sent(): void
    {
        Mail::fake();
        $key = $this->place($this->cartWith(['ceramic-mug' => 2]), $this->order('21.00', ['payment_reference' => 'TRX-1001']))->json('data.key');

        Mail::assertSent(StoreOrderMail::class, function (StoreOrderMail $mail) use ($key) {
            $html = $mail->render();

            return $mail->hasTo('Karim@Example.test')
                && $mail->mailSubject === 'Your order EC-00001 at Rahman Shop'
                && $mail->hasFrom((string) config('mail.from.address'), 'Rahman Shop')
                && $mail->hasReplyTo('help@rahman.test')
                && str_contains($html, 'Dear Karim Rahman')
                && str_contains($html, '2 × Ceramic Mug')
                && str_contains($html, 'Delivery: Standard')
                && str_contains($html, 'We will match your payment, reference TRX-1001, and confirm it.')
                && str_contains($html, 'Pay into City Bank 0123456789.')
                && str_contains($html, "https://app.izsuite.test/store/rahman-shop/orders/{$key}")
                && str_contains($html, 'or call +8801700000000');
        });

        $email = EcommerceOrderEmail::sole();
        $this->assertSame(EcommerceOrderEmail::SENT, $email->status);
        $this->assertNotNull($email->sent_at);

        // Sent already, it is not sent again; one that failed says why.
        app(StoreOrderMailer::class)->deliver($email->id, 'https://app.izsuite.test/x');
        Mail::assertSentCount(1);
        $email->update(['status' => EcommerceOrderEmail::QUEUED]);
        app(StoreOrderMailer::class)->failed($email->id, new \RuntimeException('The mail server is away.'));
        $this->assertSame([EcommerceOrderEmail::FAILED, 'The mail server is away.'], [$email->fresh()->status, $email->fresh()->error]);
    }

    public function test_the_order_opens_at_its_own_link(): void
    {
        $key = $this->place($this->cartWith(['ceramic-mug' => 1]), $this->order('13.00'))->json('data.key');

        $this->getJson(self::STORE.'/orders/'.$key)->assertOk()
            ->assertJsonPath('data.number', 'EC-00001')
            ->assertJsonPath('data.key', null)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.lines.0.title', 'Ceramic Mug')
            ->assertJsonPath('data.totals.grand_total', '13.00');

        // Another key, or another store: none. A store closed for a while still shows its customer the order.
        $this->getJson(self::STORE.'/orders/'.str_repeat('a', 64))->assertNotFound()->assertJsonPath('message', 'There is no such order. Check the link you were sent.');
        $this->business->storefronts()->create(['name' => 'Outlet', 'code' => 'rahman-outlet', 'status' => 'live', 'default_language' => 'en', 'languages' => [], 'currency' => 'USD', 'currencies' => [], 'countries' => ['BD'], 'location_id' => $this->store->location_id]);
        $this->getJson('/api/v1/stores/rahman-outlet/orders/'.$key)->assertNotFound();
        $this->store->update(['status' => Storefront::MAINTENANCE]);
        $this->getJson(self::STORE.'/orders/'.$key)->assertOk();
        $this->getJson(self::STORE.'/orders/not-a-key')->assertNotFound();
    }
}
