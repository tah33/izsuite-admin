<?php

namespace Tests\Feature;

use App\Mail\StoreOrderMail;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderDelivery;
use App\Models\User\EcommerceOrderDownload;
use App\Models\User\EcommerceOrderEmail;
use App\Models\User\EcommerceOrderLine;
use App\Models\User\StorefrontCustomer;
use App\Models\User\WorkspaceFile;
use App\Services\Shared\BusinessCalendar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\ChecksOutAStore;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsInStoreCustomers;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Secure downloads of an online order's digital products (Doc 19 section
 * 19.28 - Phase G3b): a delivered download's files fetched from the order's
 * own page - or, where Settings → eCommerce asks it, signed in to the
 * customer's account - each download recorded and counted, refused past its
 * days or its count, and given more by the business.
 */
class EcommerceOrderDownloadsTest extends TestCase
{
    use ChecksOutAStore;
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsInStoreCustomers;
    use SignsIntoBusinesses;

    private const ORDERS = '/api/v1/ecommerce/orders';

    private const TODAY = '2026-11-27';

    private array $keys = [];

    private int $recipe;

    private int $extras;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('local');
        Storage::fake('public');
        $this->travelTo(self::TODAY.' 10:00:00');
        $this->openStorefront([
            'Recipe E-Book' => ['sale_price' => '4.00', 'type' => 'digital', 'delivery_method' => 'download'],
            'Photo Pack'    => ['sale_price' => '5.00', 'type' => 'digital', 'delivery_method' => 'license_key'],
        ]);
        foreach (array_keys($this->items) as $name) {
            $this->listed($name);
        }
        $this->setUpCheckout();

        $this->post("/api/v1/items/{$this->items['Recipe E-Book']->id}/files", ['files' => [
            UploadedFile::fake()->create('recipe.pdf', 120, 'application/pdf'), UploadedFile::fake()->createWithContent('extras.zip', 'the extras'),
        ]], ['Accept' => 'application/json'])->assertCreated();
        $this->recipe = WorkspaceFile::where('name', 'recipe.pdf')->value('id');
        $this->extras = WorkspaceFile::where('name', 'extras.zip')->value('id');
    }

    /** An order of $products - the e-book, to start - by bank transfer, confirmed and, unless $pay is false, paid. */
    private function placed(array $products = ['recipe-e-book' => 1], string $total = '4.00', bool $pay = true): EcommerceOrder
    {
        $key = $this->place($this->cartWith($products), $this->order($total, ['delivery' => null, 'shipping_method_id' => null]))->assertCreated()->json('data.key');
        Sanctum::actingAs($this->user);
        $order = EcommerceOrder::query()->latest('id')->firstOrFail();
        $this->keys[$order->id] = $key;
        $this->postJson(self::ORDERS."/{$order->id}/confirm")->assertOk();

        if ($pay) {
            $this->postJson(self::ORDERS."/{$order->id}/payments", ['amount' => $total, 'payment_date' => self::TODAY])->assertOk();
        }

        return $order;
    }

    private function settings(array $fields)
    {
        return $this->putJson('/api/v1/business-settings/ecommerce', ['guest_checkout' => true, 'verify_customer_email' => true, 'price_display' => 'with_tax', 'order_prefix' => 'EC-', ...$fields]);
    }

    private function delivery(EcommerceOrder $order): EcommerceOrderDelivery
    {
        return EcommerceOrderDelivery::where('ecommerce_order_id', $order->id)->sole();
    }

    private function download(EcommerceOrder $order, int $file, ?int $delivery = null)
    {
        return $this->postJson(self::STORE.'/orders/'.$this->keys[$order->id].'/downloads/'.($delivery ?? $this->delivery($order)->id)."/{$file}");
    }

    private function line(EcommerceOrder $order): int
    {
        return EcommerceOrderLine::where('ecommerce_order_id', $order->id)->value('id');
    }

    public function test_a_download_counts_each_file_until_the_business_gives_more(): void
    {
        $this->settings(['download_limit' => 2])->assertOk();
        $order = $this->placed();

        $this->getJson(self::STORE.'/orders/'.$this->keys[$order->id])->assertOk()
            ->assertJsonPath('data.deliverables.0.download.needs_account', false)
            ->assertJsonPath('data.deliverables.0.download.files', [
                ['id' => $this->recipe, 'name' => 'recipe.pdf', 'size' => 120 * 1024, 'left' => 2],
                ['id' => $this->extras, 'name' => 'extras.zip', 'size' => 10, 'left' => 2],
            ]);
        $this->assertSame('2026-12-27', $this->delivery($order)->expires_at->setTimezone(app(BusinessCalendar::class)->timezone($this->business))->toDateString());

        // Fetched through a link that works for a few minutes, each recorded with where it came from.
        $url = $this->download($order, $this->recipe)->assertOk()->assertJsonPath('data.left', 1)->json('data.url');
        $this->get($url)->assertOk()->assertDownload('recipe.pdf');
        $this->download($order, $this->recipe)->assertOk()->assertJsonPath('data.left', 0);
        $this->download($order, $this->recipe)->assertStatus(409)->assertJsonPath('message', 'You have downloaded recipe.pdf 2 times, as often as it allows: ask the store for more.');
        $this->download($order, $this->extras)->assertOk()->assertJsonPath('data.left', 1);
        $this->assertSame([2, '127.0.0.1', null], [EcommerceOrderDownload::where('workspace_file_id', $this->recipe)->count(), EcommerceOrderDownload::first()->ip_address, EcommerceOrderDownload::first()->storefront_customer_id]);

        // The business sees each file's count and the latest downloads, and gives more: the count afresh, the customer told.
        $this->getJson(self::ORDERS."/{$order->id}")->assertOk()
            ->assertJsonPath('data.deliverables.0.download.files', [['name' => 'recipe.pdf', 'used' => 2], ['name' => 'extras.zip', 'used' => 1]])
            ->assertJsonPath('data.deliverables.0.download.limit', 2)
            ->assertJsonPath('data.deliverables.0.download.total', 3)
            ->assertJsonPath('data.deliverables.0.download.latest.0.file', 'extras.zip')
            ->assertJsonPath('data.deliverables.0.download.latest.0.account', false);
        $this->travel(5)->minutes();
        $this->postJson(self::ORDERS."/{$order->id}/lines/{$this->line($order)}/renew")->assertOk()
            ->assertJsonPath('message', 'Recipe E-Book of order EC-00001 may be downloaded again, and the customer told.')
            ->assertJsonPath('data.deliverables.0.download.files.0.used', 0)
            ->assertJsonPath('data.deliverables.0.download.total', 3);
        $this->download($order, $this->recipe)->assertOk()->assertJsonPath('data.left', 1);
        $mail = Mail::sent(StoreOrderMail::class, fn (StoreOrderMail $mail) => $mail->order['kind'] === EcommerceOrderEmail::DIGITAL_DELIVERED)->last();
        $this->assertStringContainsString('until 27 Dec 2026, 2 times for each file.', $mail->render());
        $this->getJson(self::ORDERS."/{$order->id}/activity")->assertJsonPath('data.0.description', 'Gave more downloads of Recipe E-Book of online order EC-00001');
    }

    public function test_downloads_end_after_their_days_until_given_more(): void
    {
        $this->settings(['download_days' => 1, 'download_limit' => null])->assertOk();
        $order = $this->placed();
        $this->download($order, $this->recipe)->assertOk()->assertJsonPath('data.left', null);

        $this->travelTo('2026-11-29 12:00:00');
        $this->download($order, $this->recipe)->assertStatus(409)->assertJsonPath('message', 'Your downloads of Recipe E-Book ended on 28 Nov 2026: ask the store for more.');

        $this->postJson(self::ORDERS."/{$order->id}/lines/{$this->line($order)}/renew")->assertOk();
        $this->download($order, $this->recipe)->assertOk();
    }

    public function test_an_account_is_needed_where_the_settings_ask(): void
    {
        $this->settings(['downloads_need_account' => true])->assertOk();
        $order = $this->placed();

        $this->download($order, $this->recipe)->assertForbidden()->assertJsonPath('message', 'Sign in to your account to download it - with the e-mail you ordered with.');
        $this->getJson(self::STORE.'/orders/'.$this->keys[$order->id])->assertJsonPath('data.deliverables.0.download.needs_account', true);

        // Verified, the account takes its e-mail's guest order, and downloads it signed in.
        $token = $this->verifiedCustomer('karim@example.test');
        $this->asCustomer($token)->postJson(self::STORE."/account/orders/{$order->id}/downloads/{$this->delivery($order)->id}/{$this->recipe}")->assertOk()->assertJsonPath('data.left', 4);
        $this->assertSame(StorefrontCustomer::sole()->id, EcommerceOrderDownload::sole()->storefront_customer_id);
        $this->asCustomer(null);
        $this->getJson(self::ORDERS."/{$order->id}")->assertJsonPath('data.deliverables.0.download.latest.0.account', true);
    }

    public function test_only_a_delivered_download_s_own_files_come(): void
    {
        $unpaid = $this->placed(pay: false);
        $this->download($unpaid, $this->recipe, 99999)->assertNotFound()->assertJsonPath('message', 'There is no such download.');

        $order = $this->placed();
        $this->post("/api/v1/items/{$this->items['Photo Pack']->id}/files", ['files' => [UploadedFile::fake()->create('photos.zip', 10)]], ['Accept' => 'application/json'])->assertCreated();
        $this->download($order, WorkspaceFile::where('name', 'photos.zip')->value('id'))->assertNotFound()->assertJsonPath('message', 'Recipe E-Book has no such file.');
        $this->download($unpaid, $this->recipe, $this->delivery($order)->id)->assertNotFound()->assertJsonPath('message', 'There is no such download.');

        // A license key is no download; a revoked download is not there to fetch.
        $keys = $this->placed(['photo-pack' => 1], '5.00');
        $this->download($keys, $this->recipe)->assertNotFound();
        $this->postJson(self::ORDERS."/{$order->id}/lines/{$this->line($order)}/revoke", ['reason' => 'Charged back.'])->assertOk();
        $this->download($order, $this->recipe)->assertStatus(409)->assertJsonPath('message', 'Recipe E-Book is not available to download.');
        $this->postJson(self::ORDERS."/{$order->id}/lines/{$this->line($order)}/renew")->assertStatus(409);
    }

    public function test_the_download_settings_are_kept_or_emptied_for_no_limit(): void
    {
        $this->getJson('/api/v1/business-settings/ecommerce')->assertOk()
            ->assertJsonPath('data.download_days', 30)
            ->assertJsonPath('data.download_limit', 5)
            ->assertJsonPath('data.downloads_need_account', false);

        $this->settings(['download_days' => 0])->assertUnprocessable()->assertJsonPath('errors.download_days.0', 'Let downloads work for a day at least - or leave it empty for no limit.');
        $this->settings(['download_days' => null, 'download_limit' => null, 'downloads_need_account' => true])->assertOk()
            ->assertJsonPath('data.download_days', null)
            ->assertJsonPath('data.download_limit', null)
            ->assertJsonPath('data.downloads_need_account', true);

        // Delivered with no limits: neither a last day nor a count.
        $order = $this->placed();
        $this->assertSame([null, null], [$this->delivery($order)->expires_at, $this->delivery($order)->download_limit]);
    }
}
