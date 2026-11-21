<?php

namespace Tests\Feature;

use App\Models\Admin\Language;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\Contact;
use App\Models\User\EcommerceOrderLine;
use App\Models\User\MarketingConsent;
use App\Models\User\PriceList;
use App\Models\User\StorefrontCustomer;
use App\Repositories\User\EcommerceSettingRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\ChecksOutAStore;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsInStoreCustomers;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 19 Phase E3: what a signed-in customer is offered (sections 19.8, 19.13
 * and 19.16) - the listings kept for signed-in customers, their own price
 * lists before the store's, and on their account their marketing by e-mail
 * and their language.
 */
class EcommerceSignedInCustomersTest extends TestCase
{
    use ChecksOutAStore;
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsInStoreCustomers;
    use SignsIntoBusinesses;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->openStorefront(['Ceramic Mug' => ['sale_price' => '8.00'], 'Gold Mug' => ['sale_price' => '30.00']]);
        $this->listed('Ceramic Mug');
        $this->listed('Gold Mug', ['visibility' => 'customers']);
        $this->setUpCheckout();
    }

    public function test_listings_kept_for_signed_in_customers_show_to_them_alone(): void
    {
        $this->asCustomer(null);
        $this->assertSame(['Ceramic Mug'], $this->shown());
        $this->getJson(self::STORE.'/products/gold-mug')->assertNotFound();
        $this->postJson(self::STORE.'/cart/lines', ['product' => 'gold-mug', 'quantity' => 1])->assertJsonValidationErrors(['product' => 'This product is not sold in this store.']);
        $this->getJson(self::STORE.'/suggestions?search=mug')->assertOk()->assertJsonMissing(['title' => 'Gold Mug']);

        $token = $this->verifiedCustomer();
        $this->asCustomer($token);
        $this->assertEqualsCanonicalizing(['Ceramic Mug', 'Gold Mug'], $this->shown());
        $this->getJson(self::STORE.'/products/gold-mug')->assertOk()->assertJsonPath('data.price', '30.00');
        $cart = $this->asCustomer($token)->cartWith(['gold-mug' => 1]);
        $this->asCustomer($token)->place($cart, $this->order('35.00'))->assertCreated();

        // Signed out, a cart holding one is told it is no longer sold here; the sitemap never lists it.
        $later = $this->asCustomer($token)->cartWith(['gold-mug' => 1, 'ceramic-mug' => 1]);
        $this->asCustomer(null)->withHeaders(['X-Cart-Token' => $later])->getJson(self::STORE.'/cart')->assertOk()
            ->assertJsonPath('data.lines.0.problem', 'unavailable')->assertJsonPath('data.ready', false);
        $this->asCustomer(null)->getJson(self::STORE.'/sitemap')->assertOk()->assertJsonMissing(['slug' => 'gold-mug']);
    }

    public function test_what_the_shop_offers_to_filter_by_follows_its_visitor(): void
    {
        $this->asCustomer(null)->getJson(self::STORE.'/facets')->assertOk()->assertJsonPath('data.price', ['min' => '8.00', 'max' => '8.00']);

        $token = $this->verifiedCustomer();
        $this->asCustomer($token)->getJson(self::STORE.'/facets')->assertOk()->assertJsonPath('data.price', ['min' => '8.00', 'max' => '30.00']);
        $this->asCustomer($token)->getJson(self::STORE.'/products?price_min=20')->assertOk()->assertJsonPath('data.0.title', 'Gold Mug');
    }

    public function test_checkout_says_whether_a_signed_in_customer_takes_marketing_already_and_records_it_once(): void
    {
        $guest = $this->asCustomer(null)->cartWith(['ceramic-mug' => 1]);
        $this->asCustomer(null)->quote($guest)->assertOk()->assertJsonPath('data.checkout.marketing', null);

        $token = $this->verifiedCustomer();
        $cart  = $this->asCustomer($token)->cartWith(['ceramic-mug' => 1]);
        $this->asCustomer($token)->quote($cart)->assertOk()->assertJsonPath('data.checkout.marketing', false);

        $this->asCustomer($token)->putJson(self::STORE.'/account', ['name' => 'Karim Rahman', 'marketing' => true])->assertOk();
        $this->asCustomer($token)->quote($cart)->assertJsonPath('data.checkout.marketing', true);

        // Ticked at checkout as well, it stands already: nothing more is recorded.
        $this->asCustomer($token)->place($cart, $this->order('13.00', ['marketing' => true]))->assertCreated();
        $this->assertSame(1, MarketingConsent::count());
    }

    public function test_a_customer_s_own_price_list_comes_before_the_store_s(): void
    {
        $sales = Application::create(['app_key' => 'sales', 'name' => 'Sales', 'family_key' => 'sales', 'edition' => 'full', 'category_id' => Application::first()->category_id, 'sort_order' => 10, 'is_active' => true, 'status' => 'available']);
        $sales->packages()->attach(Package::pluck('id'));
        $this->postJson('/api/v1/apps/sales/activate')->assertOk();

        $mug   = $this->items['Ceramic Mug']->id;
        $store = $this->priceList('Online Prices', true, [[$mug, 1, '7.00']]);
        $this->store->update(['price_list_id' => $store->id]);

        $token   = $this->verifiedCustomer();
        $contact = StorefrontCustomer::sole()->contact_id;
        $karims  = $this->priceList("Karim's Prices", false, [[$mug, 1, '6.00'], [$mug, 3, '5.50']]);
        $karims->contacts()->attach($contact);
        $others  = $this->priceList("Nadia's Prices", false, [[$mug, 1, '4.00']]);
        $others->contacts()->attach(Contact::query()->forceCreate(['workspace_id' => $this->business->id, 'contact_type' => 'individual', 'role' => 'customer', 'name' => 'Nadia', 'is_active' => true])->id);

        $this->asCustomer(null)->getJson(self::STORE.'/products/ceramic-mug')->assertJsonPath('data.price', '7.00');
        $this->asCustomer($token)->getJson(self::STORE.'/products/ceramic-mug')->assertJsonPath('data.price', '6.00');

        // In the cart too, with the list's quantity breaks - and kept with the order's line.
        $cart = $this->asCustomer($token)->cartWith(['ceramic-mug' => 3]);
        $this->asCustomer($token)->withHeaders(['X-Cart-Token' => $cart])->getJson(self::STORE.'/cart')->assertJsonPath('data.lines.0.unit_price', '5.50');
        $this->asCustomer($token)->place($cart, $this->order('21.50'))->assertCreated();
        $this->assertSame($karims->id, EcommerceOrderLine::where('kind', 'product')->sole()->price_list_id);

        // An account not verified has no contact yet, so the store's list.
        app(EcommerceSettingRepository::class)->save($this->business, ['verify_customer_email' => false]);
        $unverified = $this->signUpCustomer('nadia@example.test', 'Nadia Islam')->json('data.token');
        $this->asCustomer($unverified)->getJson(self::STORE.'/products/ceramic-mug')->assertJsonPath('data.price', '7.00');
    }

    public function test_an_account_gives_and_withdraws_marketing_by_e_mail_as_its_contact_s(): void
    {
        $token   = $this->verifiedCustomer();
        $contact = StorefrontCustomer::sole()->contact_id;
        $save    = fn (?bool $marketing) => $this->asCustomer($token)->putJson(self::STORE.'/account', ['name' => 'Karim Rahman', 'marketing' => $marketing]);

        $this->asCustomer($token)->getJson(self::STORE.'/account')->assertJsonPath('data.marketing', false);
        $save(true)->assertOk()->assertJsonPath('data.marketing', true);
        $save(true)->assertOk();

        $consent = MarketingConsent::sole();
        $this->assertSame([$contact, MarketingConsent::GIVEN, 'email', 'web_form', 'Store account on Rahman Shop'], [$consent->contact_id, $consent->status, $consent->channel, $consent->source, $consent->evidence]);

        $save(false)->assertOk()->assertJsonPath('data.marketing', false);
        $this->assertSame([MarketingConsent::WITHDRAWN, 'Withdrawn by the customer from their store account on Rahman Shop.'], [$consent->fresh()->status, $consent->fresh()->withdrawal_note]);
        $this->assertSame(1, MarketingConsent::count());
        $this->assertSame([
            'Recorded marketing consent given for Promotions and Offers by e-mail, from the store account on Rahman Shop',
            'Recorded marketing consent withdrawn for Promotions and Offers by e-mail, from the store account on Rahman Shop',
        ], ActivityLog::where('model_type', Contact::class)->where('description', 'like', 'Recorded%')->orderBy('id')->pluck('description')->all());

        // An account not verified has no contact to record it for: refused, and nothing else saved.
        app(EcommerceSettingRepository::class)->save($this->business, ['verify_customer_email' => false]);
        $unverified = $this->signUpCustomer('nadia@example.test', 'Nadia Islam')->json('data.token');
        $this->asCustomer($unverified)->getJson(self::STORE.'/account')->assertJsonPath('data.marketing', null);
        $this->asCustomer($unverified)->putJson(self::STORE.'/account', ['name' => 'Nadia I.', 'marketing' => true])->assertUnprocessable()
            ->assertJsonPath('errors.marketing.0', 'Verify your e-mail first: then choose what we send you.');
        $this->assertSame('Nadia Islam', StorefrontCustomer::where('email', 'nadia@example.test')->sole()->name);
    }

    public function test_an_account_chooses_one_of_the_store_s_languages_its_contact_s_too(): void
    {
        Language::create(['name' => 'Bangla', 'code' => 'bn', 'native_name' => 'বাংলা', 'is_default' => false, 'is_active' => true]);
        $this->store->update(['languages' => ['en', 'bn']]);
        $token = $this->verifiedCustomer();

        $this->asCustomer($token)->getJson(self::STORE.'/account')->assertJsonPath('data.languages', [['code' => 'en', 'name' => 'English'], ['code' => 'bn', 'name' => 'Bangla']]);
        $this->asCustomer($token)->putJson(self::STORE.'/account', ['name' => 'Karim Rahman', 'language' => 'fr'])->assertUnprocessable()
            ->assertJsonPath('errors.language.0', 'Choose one of the languages of Rahman Shop.');
        $this->asCustomer($token)->putJson(self::STORE.'/account', ['name' => 'Karim Rahman', 'language' => 'bn'])->assertOk()->assertJsonPath('data.language', 'bn');

        $account = StorefrontCustomer::sole();
        $this->assertSame(['bn', 'bn'], [$account->language, Contact::find($account->contact_id)->preferred_language]);

        // Saved again without it, it stays.
        $this->asCustomer($token)->putJson(self::STORE.'/account', ['name' => 'Karim R.'])->assertOk()->assertJsonPath('data.language', 'bn');
    }

    public function test_an_account_keeps_its_language_where_the_store_does_not_offer_it(): void
    {
        Language::create(['name' => 'Bangla', 'code' => 'bn', 'native_name' => 'বাংলা', 'is_default' => false, 'is_active' => true]);
        $this->store->update(['languages' => ['en', 'bn']]);
        $token = $this->verifiedCustomer();
        $this->asCustomer($token)->putJson(self::STORE.'/account', ['name' => 'Karim Rahman', 'language' => 'bn'])->assertOk();

        // The account is the business's: another of its stores - or this one, later - may not offer Bangla.
        $this->store->update(['languages' => ['en']]);
        $this->asCustomer($token)->getJson(self::STORE.'/account')->assertJsonPath('data.language', 'bn')
            ->assertJsonPath('data.languages', [['code' => 'en', 'name' => 'English'], ['code' => 'bn', 'name' => 'Bangla']]);
        $this->asCustomer($token)->putJson(self::STORE.'/account', ['name' => 'Karim R.', 'language' => 'bn'])->assertOk()->assertJsonPath('data.language', 'bn');

        // Changed to English, Bangla is no longer offered.
        $this->asCustomer($token)->putJson(self::STORE.'/account', ['name' => 'Karim R.', 'language' => 'en'])->assertOk()->assertJsonPath('data.languages', [['code' => 'en', 'name' => 'English']]);
        $this->asCustomer($token)->putJson(self::STORE.'/account', ['name' => 'Karim R.', 'language' => 'bn'])->assertUnprocessable()
            ->assertJsonPath('errors.language.0', 'Choose one of the languages of Rahman Shop.');
    }

    public function test_a_language_chosen_before_the_e_mail_is_verified_is_the_contact_s_once_linked(): void
    {
        Language::create(['name' => 'Bangla', 'code' => 'bn', 'native_name' => 'বাংলা', 'is_default' => false, 'is_active' => true]);
        $this->store->update(['languages' => ['en', 'bn']]);
        app(EcommerceSettingRepository::class)->save($this->business, ['verify_customer_email' => false]);
        $karim = Contact::query()->forceCreate(['workspace_id' => $this->business->id, 'contact_type' => 'individual', 'role' => 'customer', 'name' => 'Karim', 'email' => 'karim@example.test', 'preferred_language' => 'en', 'is_active' => true]);

        // Karim's e-mail is a contact's of the business already; Nadia's is no one's.
        foreach (['karim@example.test' => 'Karim Rahman', 'nadia@example.test' => 'Nadia Islam'] as $email => $name) {
            $token = $this->signUpCustomer($email, $name)->json('data.token');
            $this->asCustomer($token)->putJson(self::STORE.'/account', ['name' => $name, 'language' => 'bn'])->assertOk()->assertJsonPath('data.marketing', null);
            $this->asCustomer(null)->postJson(self::STORE.'/account/verify', ['email' => $email, 'code' => $this->codeSentTo($email)])->assertOk();
        }

        $this->assertSame([$karim->id, 'bn'], [StorefrontCustomer::where('email', 'karim@example.test')->sole()->contact_id, $karim->fresh()->preferred_language]);
        $made = Contact::find(StorefrontCustomer::where('email', 'nadia@example.test')->sole()->contact_id);
        $this->assertSame(['nadia@example.test', 'bn'], [$made->email, $made->preferred_language]);
    }

    /** A USD list of the business's - for every customer, or for those it names - with rules of [item, from quantity, price]. */
    private function priceList(string $name, bool $everyone, array $rules): PriceList
    {
        $list = PriceList::query()->forceCreate(['workspace_id' => $this->business->id, 'name' => $name, 'currency' => 'USD', 'is_active' => true, 'all_customers' => $everyone, 'all_locations' => true]);

        foreach ($rules as $at => [$item, $from, $price]) {
            $list->rules()->create(['target' => 'item', 'item_id' => $item, 'min_quantity' => $from, 'method' => 'fixed', 'value' => $price, 'position' => $at + 1]);
        }

        return $list;
    }
}
