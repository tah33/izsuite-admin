<?php

namespace Tests\Feature;

use App\Mail\StoreCartRecoveryMail;
use App\Models\User\EcommerceCart;
use App\Models\User\User;
use App\Models\User\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\ChecksOutAStore;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsInStoreCustomers;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * An abandoned cart's recovery (Doc 19 section 19.32 - Phase H3b): its
 * recovery e-mail, sent by hand under Confirm Orders, once, while the cart is
 * abandoned and its customer agreed to marketing by e-mail - their latest
 * word; its link opening the cart in the customer's browser while it is open;
 * and a cart placed after it Recovered, its order linked.
 */
class EcommerceCartRecoveryTest extends TestCase
{
    use ChecksOutAStore;
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsInStoreCustomers;
    use SignsIntoBusinesses;

    private const CARTS = '/api/v1/ecommerce/abandoned-carts';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo('2026-11-28 10:00:00');
        $this->openStorefront(['Ceramic Mug' => ['sale_price' => '8.00'], 'Tea Towel' => ['sale_price' => '3.00']]);
        $this->listed('Ceramic Mug');
        $this->listed('Tea Towel');
        $this->setUpCheckout();
    }

    /** A cart of two mugs and a towel ($19), its checkout asked with $choices - as a guest, or signed in by $customer's token - then left 25 hours and abandoned: its token. */
    private function abandoned(array $choices, ?string $customer = null): string
    {
        $this->asCustomer($customer);
        $token = $this->cartWith(['ceramic-mug' => 2, 'tea-towel' => 1]);
        $this->quote($token, $choices)->assertOk();
        $this->travel(25)->hours();
        $this->artisan('ecommerce:abandon-carts')->assertSuccessful();
        Sanctum::actingAs($this->user);

        return $token;
    }

    private function cartOf(string $token): EcommerceCart
    {
        return EcommerceCart::where('token_hash', hash('sha256', $token))->sole();
    }

    private function send(EcommerceCart $cart)
    {
        return $this->asCustomer(null)->postJson(self::CARTS."/{$cart->id}/recovery");
    }

    /** The token in the link of the recovery e-mail last sent to $email. */
    private function linkSentTo(string $email): string
    {
        return Str::after(Mail::sent(StoreCartRecoveryMail::class, fn (StoreCartRecoveryMail $mail) => $mail->hasTo($email))->last()->cart['link'], 'recover=');
    }

    private function recover(string $token)
    {
        return $this->asCustomer(null)->postJson(self::STORE.'/cart/recover', ['token' => $token]);
    }

    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    public function test_an_abandoned_carts_recovery_email_is_sent_once_where_its_customer_agreed(): void
    {
        $karim = $this->cartOf($this->abandoned(['name' => 'Karim Rahman', 'email' => 'karim@example.test', 'marketing' => true]));

        $this->send($karim)->assertOk()
            ->assertJsonPath('message', 'Recovery e-mail sent to karim@example.test.')
            ->assertJsonPath('data.recovery.sent_at', now()->toIso8601String())
            ->assertJsonPath('data.recovery.opened_at', null)
            ->assertJsonPath('data.recovered', false);

        Mail::assertSent(StoreCartRecoveryMail::class, function (StoreCartRecoveryMail $mail) {
            $mail->assertSeeInHtml('Return to Your Cart');
            $mail->assertSeeInText('2 × Ceramic Mug');
            $mail->assertSeeInText('To stop them, reply to this e-mail.');

            return $mail->hasTo('karim@example.test') && $mail->hasReplyTo('help@rahman.test')
                && $mail->mailSubject === 'Your cart at Rahman Shop is waiting for you'
                && array_column($mail->cart['lines'], 'title') === ['Ceramic Mug', 'Tea Towel'] && str_contains($mail->cart['lines'][0]['amount'], '16.00')
                && str_contains($mail->cart['total'], '19.00') && $mail->cart['account'] === null
                && str_contains($mail->cart['link'], '/store/rahman-shop/cart?recover=');
        });
        $this->assertSame($this->user->id, $karim->refresh()->recovery_sent_by);
        $this->assertNotNull($karim->recovery_token_hash);
        $this->assertDatabaseHas('activity_logs', ['description' => 'Sent a recovery e-mail for the abandoned cart of Karim Rahman (karim@example.test) on Rahman Shop']);

        // Once a cart.
        $this->send($karim)->assertUnprocessable()->assertJsonPath('errors.cart.0', 'Its recovery e-mail was sent already: a cart is e-mailed once.');
        Mail::assertSentCount(1);

        // Not where its customer did not agree, nor once it is in use again.
        $rafi = $this->cartOf($this->abandoned(['name' => 'Rafi Ahmed', 'email' => 'rafi@example.test']));
        $this->send($rafi)->assertUnprocessable()->assertJsonPath('errors.cart.0', 'Its customer has not agreed to marketing by e-mail, so it is not e-mailed.');
        $sumi = $this->abandoned(['email' => 'sumi@example.test', 'marketing' => true]);
        $this->asCustomer(null)->withHeaders(['X-Cart-Token' => $sumi])->getJson(self::STORE.'/cart')->assertOk();
        $this->send($this->cartOf($sumi))->assertUnprocessable()->assertJsonPath('errors.cart.0', 'Only an abandoned cart is e-mailed: this one is in use again.');
        Mail::assertSentCount(1);

        // Under Confirm Orders.
        Sanctum::actingAs($this->member(['ecommerce.orders.view']));
        $this->send($rafi)->assertForbidden();
        $this->postJson(self::CARTS.'/999/recovery')->assertForbidden();
        Sanctum::actingAs($this->member(['ecommerce.orders.confirm']));
        $this->postJson(self::CARTS.'/999/recovery')->assertNotFound();
    }

    public function test_a_signed_in_customers_latest_word_decides(): void
    {
        $karim = $this->verifiedCustomer();
        $this->asCustomer($karim)->putJson(self::STORE.'/account', ['name' => 'Karim Rahman', 'marketing' => true])->assertOk();

        // The box ticked, then their consent withdrawn from their account: not e-mailed.
        $cart = $this->cartOf($this->abandoned(['marketing' => true], $karim));
        $this->asCustomer($karim)->putJson(self::STORE.'/account', ['name' => 'Karim Rahman', 'marketing' => false])->assertOk();
        Sanctum::actingAs($this->user);
        $this->send($cart)->assertUnprocessable()->assertJsonPath('errors.cart.0', 'Its customer has not agreed to marketing by e-mail, so it is not e-mailed.');

        // Their consent given again: e-mailed, with their account's link to change it.
        $this->asCustomer($karim)->putJson(self::STORE.'/account', ['name' => 'Karim Rahman', 'marketing' => true])->assertOk();
        Sanctum::actingAs($this->user);
        $this->send($cart)->assertOk();
        Mail::assertSent(StoreCartRecoveryMail::class, fn (StoreCartRecoveryMail $mail) => $mail->hasTo('karim@example.test') && str_ends_with((string) $mail->cart['account'], '/store/rahman-shop/account'));

        // Withdrawn before a checkout where the box is ticked: the box is their latest word.
        $nadia = $this->verifiedCustomer('nadia@example.test', 'Nadia Islam');
        $this->asCustomer($nadia)->putJson(self::STORE.'/account', ['name' => 'Nadia Islam', 'marketing' => true])->assertOk();
        $this->asCustomer($nadia)->putJson(self::STORE.'/account', ['name' => 'Nadia Islam', 'marketing' => false])->assertOk();
        $this->travel(1)->hours();
        $this->send($this->cartOf($this->abandoned(['marketing' => true], $nadia)))->assertOk();
    }

    public function test_its_link_opens_the_cart_and_a_cart_placed_after_it_is_recovered(): void
    {
        $token = $this->abandoned(['name' => 'Karim Rahman', 'email' => 'karim@example.test', 'marketing' => true]);
        $cart  = $this->cartOf($token);
        $this->send($cart)->assertOk();
        $link = $this->linkSentTo('karim@example.test');

        // Opened: the cart, with a new token for this browser - its old one no longer works.
        $this->travel(2)->hours();
        $fresh = $this->recover($link)->assertOk()
            ->assertJsonPath('message', 'Your cart is as you left it.')
            ->assertJsonPath('data.count', 3)
            ->json('data.token');
        $this->assertNotNull($fresh);
        $this->asCustomer(null)->withHeaders(['X-Cart-Token' => $token])->getJson(self::STORE.'/cart')->assertJsonPath('data.lines', []);
        $this->asCustomer(null)->withHeaders(['X-Cart-Token' => $fresh])->getJson(self::STORE.'/cart')->assertJsonPath('data.count', 3);
        $this->assertSame([EcommerceCart::ACTIVE, now()->toDateTimeString()], [$cart->refresh()->status, $cart->recovery_opened_at->toDateTimeString()]);

        // Placed: Recovered, its order linked.
        $this->asCustomer(null);
        $this->place($fresh, $this->order('24.00'))->assertCreated();
        Sanctum::actingAs($this->user);
        $this->getJson(self::CARTS)->assertOk()
            ->assertJsonPath('data.0.status', 'converted')
            ->assertJsonPath('data.0.recovered', true)
            ->assertJsonPath('data.0.order.number', 'EC-00001')
            ->assertJsonPath('data.0.recovery.opened_at', now()->toIso8601String());

        // Its link once its cart is an order; a link of no cart; one malformed.
        $this->recover($link)->assertNotFound()->assertJsonPath('message', 'This cart became an order already: its e-mail has the order.');
        $this->recover(Str::random(48))->assertNotFound()->assertJsonPath('message', 'This link is not for a cart of this store. Add the products again.');
        $this->recover('not-a-token!')->assertUnprocessable();

        // A cart e-mailed, then past its days: expired.
        $late = $this->cartOf($this->abandoned(['email' => 'rafi@example.test', 'marketing' => true]));
        $this->send($late)->assertOk();
        $this->travel(31)->days();
        $this->artisan('ecommerce:expire-carts')->assertSuccessful();
        $this->recover($this->linkSentTo('rafi@example.test'))->assertNotFound()->assertJsonPath('message', 'This cart has expired. Add the products again.');
    }
}
