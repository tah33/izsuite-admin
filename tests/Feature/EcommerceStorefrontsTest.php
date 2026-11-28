<?php

namespace Tests\Feature;

use App\Models\Admin\Language;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\Location;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Support\Permissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 19 Phase A1: eCommerce comes on, with Settings → eCommerce (section
 * 19.37) and its storefronts (section 19.4) - each with a code that is its
 * address across izSuite, its languages, currencies and countries, and the
 * location it sells from - kept under Manage Storefront, and seen where
 * their location may be used.
 */
class EcommerceStorefrontsTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const STOREFRONTS = '/api/v1/ecommerce/storefronts';

    private const SETTINGS = '/api/v1/business-settings/ecommerce';

    private User $user;

    private Workspace $business;

    private Location $dhaka;

    private Location $chattogram;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $ecommerce = Application::create(['app_key' => 'ecommerce', 'name' => 'eCommerce', 'family_key' => 'ecommerce', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 40, 'is_active' => true, 'status' => 'available']);
        $ecommerce->packages()->attach(Package::pluck('id'));

        Language::create(['name' => 'English', 'code' => 'en', 'native_name' => 'English', 'is_default' => true, 'is_active' => true]);
        Language::create(['name' => 'Bengali', 'code' => 'bn', 'native_name' => 'Bangla', 'is_default' => false, 'is_active' => true]);
        Language::create(['name' => 'French', 'code' => 'fr', 'native_name' => 'Francais', 'is_default' => false, 'is_active' => false]);

        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->business->forceFill(['base_currency' => 'USD', 'additional_currencies' => ['EUR', 'BDT']])->save();
        // On a plan with no limit of storefronts (Phase A2).
        $this->business->package()->associate(Package::where('key', 'premium')->sole())->save();
        $this->dhaka      = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka', 'code' => 'DHK']);
        $this->chattogram = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Chattogram', 'code' => 'CTG']);
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

    /** A Dhaka storefront in dollars and English, selling to Bangladesh, unless $fields say otherwise. */
    private function storefront(array $fields = []): TestResponse
    {
        return $this->postJson(self::STOREFRONTS, [
            'name' => 'Main Shop', 'code' => 'Rahman-Shop', 'default_language' => 'en', 'currency' => 'USD', 'countries' => ['BD'], 'location_id' => $this->dhaka->id, ...$fields,
        ]);
    }

    public function test_settings_come_with_ecommerce_with_their_defaults_and_are_saved(): void
    {
        // Not there until eCommerce is on.
        $this->getJson(self::SETTINGS)->assertForbidden();
        $this->postJson('/api/v1/apps/ecommerce/activate')->assertOk();

        $this->getJson(self::SETTINGS)->assertOk()
            ->assertExactJson(['data' => [
                'guest_checkout' => true, 'verify_customer_email' => true, 'price_display' => 'with_tax', 'order_prefix' => 'EC-', 'cookie_notice' => true, 'cookie_notice_text' => null, 'cart_days' => 30,
                'checkout_phone' => 'required', 'checkout_notes' => true, 'review_above' => null, 'hold_for_review' => false, 'payment_link_days' => 7, 'partial_payments' => false,
                // Phase G3b: downloads work 30 days, each file 5 times, without an account.
                'download_days' => 30, 'download_limit' => 5, 'downloads_need_account' => false,
                // Phase H3a: a cart whose customer is known is abandoned after 24 hours unused.
                'abandoned_hours' => 24,
                // Phase H4b: a request for a quotation also becomes a lead while CRM is on.
                'quote_requests_to_leads' => true,
            ]]);

        $this->putJson(self::SETTINGS, ['guest_checkout' => false, 'verify_customer_email' => true, 'price_display' => 'without_tax', 'order_prefix' => ' web/ '])->assertOk()
            ->assertJsonPath('message', 'eCommerce settings saved.')
            ->assertJsonPath('data.guest_checkout', false)
            ->assertJsonPath('data.price_display', 'without_tax')
            ->assertJsonPath('data.order_prefix', 'WEB/');
        $this->assertTrue(ActivityLog::query()->where('description', 'Updated the eCommerce settings')->exists());

        $this->putJson(self::SETTINGS, ['guest_checkout' => true, 'verify_customer_email' => true, 'price_display' => 'gross', 'order_prefix' => 'EC #'])->assertUnprocessable()
            ->assertJsonValidationErrors(['price_display', 'order_prefix' => 'Use letters, digits, dashes and slashes.']);

        // Turned off, they can be read but not changed.
        $this->postJson('/api/v1/apps/ecommerce/deactivate')->assertOk();
        $this->getJson(self::SETTINGS)->assertOk()->assertJsonPath('data.order_prefix', 'WEB/');
        $this->putJson(self::SETTINGS, ['guest_checkout' => true, 'verify_customer_email' => true, 'price_display' => 'with_tax', 'order_prefix' => 'EC-'])->assertForbidden();
    }

    public function test_storefronts_are_kept_with_their_languages_currencies_and_countries(): void
    {
        $this->postJson('/api/v1/apps/ecommerce/activate')->assertOk();
        $this->getJson(self::STOREFRONTS)->assertOk()->assertExactJson(['data' => []]);
        $this->getJson(self::STOREFRONTS.'/options')->assertOk()->assertExactJson(['data' => ['currencies' => ['USD', 'EUR', 'BDT'], 'country' => null, 'price_lists' => null]]);
        // A new storefront's first country is the business's own.
        $this->business->businessSetting()->create([
            'user_id' => $this->user->id, 'business_name' => 'Rahman Traders', 'language' => 'en', 'country' => 'BD', 'state' => 'Dhaka',
            'city'    => 'Dhaka', 'zip_code' => '1207', 'address_line_1' => 'Gulshan Avenue', 'timezone' => 'Asia/Dhaka', 'stock_accounting_method' => 'fifo',
        ]);
        $this->getJson(self::STOREFRONTS.'/options')->assertOk()->assertJsonPath('data.country', 'BD');

        $id = $this->storefront(['currencies' => ['BDT', 'EUR'], 'languages' => ['bn'], 'support_email' => 'help@rahman.test', 'support_phone' => ' +880 1711 000000 '])->assertCreated()
            ->assertJsonPath('message', 'Storefront added.')
            ->assertJsonPath('data.code', 'rahman-shop')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.location', ['id' => $this->dhaka->id, 'name' => 'Dhaka'])
            // Its defaults among the others: the business's currencies in their order, the default language first.
            ->assertJsonPath('data.currencies', ['USD', 'EUR', 'BDT'])
            ->assertJsonPath('data.languages', ['en', 'bn'])
            ->assertJsonPath('data.countries', ['BD'])
            ->assertJsonPath('data.support_email', 'help@rahman.test')
            ->assertJsonPath('data.support_phone', '+880 1711 000000')
            ->json('data.id');
        $this->assertTrue(ActivityLog::query()->where('description', 'Added the storefront "Main Shop" (rahman-shop)')->exists());

        $this->putJson(self::STOREFRONTS."/{$id}", [
            'name' => 'Rahman Online', 'code' => 'rahman-shop', 'status' => 'live', 'default_language' => 'bn', 'languages' => ['en'], 'currency' => 'BDT', 'currencies' => [],
            'countries' => ['BD', 'IN', 'BD'], 'location_id' => $this->chattogram->id,
        ])->assertOk()
            ->assertJsonPath('message', 'Storefront saved.')
            ->assertJsonPath('data.status', 'live')
            ->assertJsonPath('data.default_language', 'bn')
            ->assertJsonPath('data.languages', ['bn', 'en'])
            ->assertJsonPath('data.currency', 'BDT')
            ->assertJsonPath('data.currencies', ['BDT'])
            ->assertJsonPath('data.countries', ['BD', 'IN'])
            ->assertJsonPath('data.location.name', 'Chattogram')
            ->assertJsonPath('data.support_email', null);
        $this->assertTrue(ActivityLog::query()->where('description', 'Updated the storefront "Rahman Online" (rahman-shop)')->exists());
        $this->assertSame(['Rahman Online'], array_column($this->getJson(self::STOREFRONTS)->assertOk()->json('data'), 'name'));

        // A location a storefront sells from is kept.
        $this->deleteJson("/api/v1/locations/{$this->chattogram->id}")->assertStatus(409)
            ->assertJsonPath('message', 'Storefronts sell from this location, so it cannot be deleted. Deactivate it instead.');

        $this->deleteJson(self::STOREFRONTS."/{$id}")->assertOk()->assertJsonPath('message', 'Storefront deleted.');
        $this->assertDatabaseCount('storefronts', 0);
        $this->assertTrue(ActivityLog::query()->where('description', 'Deleted the storefront "Rahman Online" (rahman-shop)')->exists());
    }

    public function test_a_code_is_an_address_no_other_storefront_of_izsuite_has(): void
    {
        $this->postJson('/api/v1/apps/ecommerce/activate')->assertOk();
        $this->storefront()->assertCreated();

        $theirs   = $this->businessOf(User::factory()->create());
        $sylhet   = $theirs->locations()->create(['user_id' => $theirs->user_id, 'name' => 'Sylhet', 'code' => 'SYL']);
        $theirs->storefronts()->create(['name' => 'Their Shop', 'code' => 'their-shop', 'default_language' => 'en', 'languages' => ['en'], 'currency' => 'USD', 'currencies' => ['USD'], 'countries' => ['BD'], 'location_id' => $sylhet->id]);

        // Never a code izSuite keeps, another storefront's - another business's too - or one badly written.
        $this->storefront(['name' => 'Second', 'code' => 'www'])->assertUnprocessable()->assertJsonValidationErrors(['code' => 'izSuite keeps this code for itself: choose another.']);
        $this->storefront(['name' => 'Second', 'code' => 'RAHMAN-SHOP'])->assertUnprocessable()->assertJsonValidationErrors(['code' => 'Another storefront has this code: choose another.']);
        $this->storefront(['name' => 'Second', 'code' => 'their-shop'])->assertUnprocessable()->assertJsonValidationErrors(['code' => 'Another storefront has this code: choose another.']);
        $this->storefront(['name' => 'Second', 'code' => '-shop'])->assertUnprocessable()->assertJsonValidationErrors(['code' => 'Use letters, digits and dashes, starting and ending with a letter or digit.']);
        $this->storefront(['name' => 'Second', 'code' => 'ab'])->assertUnprocessable()->assertJsonValidationErrors(['code' => 'Use 3 characters at least.']);
        // Its name is its own in the business: another business's may be the same.
        $this->storefront(['code' => 'other-shop'])->assertUnprocessable()->assertJsonValidationErrors(['name' => 'Another storefront has this name.']);
        $this->storefront(['name' => 'Their Shop', 'code' => 'our-shop'])->assertCreated();

        // The business's currencies, the platform's active languages, countries it knows, a location switched on.
        $this->storefront(['name' => 'Third', 'code' => 'third-shop', 'currency' => 'GBP'])->assertUnprocessable()->assertJsonValidationErrors(['currency' => "Choose one of the business's currencies."]);
        $this->storefront(['name' => 'Third', 'code' => 'third-shop', 'currencies' => ['GBP']])->assertUnprocessable()->assertJsonValidationErrors(['currencies' => "Choose among the business's currencies."]);
        $this->storefront(['name' => 'Third', 'code' => 'third-shop', 'default_language' => 'fr'])->assertUnprocessable()->assertJsonValidationErrors(['default_language' => 'Choose one of the available languages.']);
        $this->storefront(['name' => 'Third', 'code' => 'third-shop', 'languages' => ['fr']])->assertUnprocessable()->assertJsonValidationErrors(['languages.0' => 'Choose among the available languages.']);
        $this->storefront(['name' => 'Third', 'code' => 'third-shop', 'countries' => []])->assertUnprocessable()->assertJsonValidationErrors(['countries' => 'Choose the countries it sells to.']);
        $this->storefront(['name' => 'Third', 'code' => 'third-shop', 'countries' => ['XX']])->assertUnprocessable()->assertJsonValidationErrors(['countries.0' => 'Choose among the listed countries.']);
        $this->storefront(['name' => 'Third', 'code' => 'third-shop', 'status' => 'open'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->chattogram->update(['is_active' => false]);
        $this->storefront(['name' => 'Third', 'code' => 'third-shop', 'location_id' => $this->chattogram->id])->assertUnprocessable()->assertJsonValidationErrors(['location_id' => 'Choose one of your locations that is switched on.']);
        $this->storefront(['name' => 'Third', 'code' => 'third-shop', 'location_id' => $sylhet->id])->assertUnprocessable()->assertJsonValidationErrors('location_id');
    }

    public function test_storefronts_need_their_permissions_and_are_seen_where_their_location_may_be_used(): void
    {
        $this->postJson('/api/v1/apps/ecommerce/activate')->assertOk();
        $inDhaka = $this->storefront()->assertCreated()->json('data');
        $this->storefront(['name' => 'Port Shop', 'code' => 'port-shop', 'location_id' => $this->chattogram->id])->assertCreated();

        // Managing storefronts is not managing eCommerce's settings, which the Manager template leaves out as it does every app's.
        $this->assertEqualsCanonicalizing(['ecommerce.store.manage', 'ecommerce.store.view'], Permissions::expand(['ecommerce.store.manage']));
        $this->assertEqualsCanonicalizing(['ecommerce.settings.manage', 'settings.view'], Permissions::expand(['ecommerce.settings.manage']));
        $manager = Permissions::forTemplate('manager', ['ecommerce']);
        $this->assertContains('ecommerce.store.manage', $manager);
        $this->assertNotContains('ecommerce.settings.manage', $manager);

        // Viewing: the storefronts of one's own locations, without changes or the settings.
        Sanctum::actingAs($this->member(['ecommerce.store.view'], [$this->chattogram->id]));
        $this->assertSame(['Port Shop'], array_column($this->getJson(self::STOREFRONTS)->assertOk()->json('data'), 'name'));
        $this->storefront(['name' => 'Third', 'code' => 'third-shop'])->assertForbidden();
        $this->getJson(self::STOREFRONTS.'/options')->assertForbidden();
        $this->getJson(self::SETTINGS)->assertForbidden();

        // Managing: within one's own locations - another's storefront is not found, nor a location outside them taken.
        Sanctum::actingAs($this->member(['ecommerce.store.view', 'ecommerce.store.manage'], [$this->chattogram->id]));
        $this->putJson(self::STOREFRONTS."/{$inDhaka['id']}", [...$inDhaka, 'location_id' => $this->dhaka->id])->assertNotFound();
        $this->deleteJson(self::STOREFRONTS."/{$inDhaka['id']}")->assertNotFound();
        $this->storefront(['name' => 'Third', 'code' => 'third-shop'])->assertUnprocessable()->assertJsonValidationErrors(['location_id' => 'Choose one of your locations that is switched on.']);
        $this->storefront(['name' => 'Third', 'code' => 'third-shop', 'location_id' => $this->chattogram->id])->assertCreated();
        $this->getJson(self::SETTINGS)->assertForbidden();

        // The settings: their own permission.
        Sanctum::actingAs($this->member(['ecommerce.settings.manage', 'settings.view']));
        $this->getJson(self::SETTINGS)->assertOk();
        $this->getJson(self::STOREFRONTS)->assertForbidden();

        // Nobody else.
        Sanctum::actingAs($this->member(['dashboard.view']));
        $this->getJson(self::STOREFRONTS)->assertForbidden();
    }
}
