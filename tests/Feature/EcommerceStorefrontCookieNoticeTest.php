<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 19 Phase C6b: Settings → eCommerce's cookie and consent settings
 * (section 19.37) - whether the storefronts show their visitors a cookie
 * notice, on to start with, and its words, izSuite's own when none are
 * given - as each store says them.
 */
class EcommerceStorefrontCookieNoticeTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsIntoBusinesses;

    private const SETTINGS = '/api/v1/business-settings/ecommerce';

    protected function setUp(): void
    {
        parent::setUp();

        $this->openStorefront(['Ceramic Mug' => ['sale_price' => '8.00']]);
    }

    private function settings(array $fields = []): array
    {
        return ['guest_checkout' => true, 'verify_customer_email' => true, 'price_display' => 'with_tax', 'order_prefix' => 'EC-', ...$fields];
    }

    public function test_the_storefronts_show_a_cookie_notice_as_the_settings_say(): void
    {
        // On to start with, in izSuite's own words.
        $this->getJson(self::SETTINGS)->assertOk()->assertJsonPath('data.cookie_notice', true)->assertJsonPath('data.cookie_notice_text', null);
        $this->getJson(self::STORE)->assertOk()->assertJsonPath('data.cookie_notice', ['text' => null, 'privacy_policy' => false]);

        $this->putJson(self::SETTINGS, $this->settings(['cookie_notice' => true, 'cookie_notice_text' => '  We use cookies to keep your cart.  ']))->assertOk()
            ->assertJsonPath('data.cookie_notice_text', 'We use cookies to keep your cart.');
        $this->getJson(self::STORE)->assertJsonPath('data.cookie_notice', ['text' => 'We use cookies to keep your cart.', 'privacy_policy' => false]);

        // Its privacy policy published - in its footer or not - the notice links to it.
        $this->putJson('/api/v1/ecommerce/storefronts/'.$this->store->id.'/pages/privacy', ['title' => 'Privacy Policy', 'body' => '<p>What we keep.</p>', 'status' => 'published', 'in_footer' => false])->assertOk();
        $this->getJson(self::STORE)->assertJsonPath('data.cookie_notice.privacy_policy', true)->assertJsonPath('data.pages', []);

        // Saved without them, as a form from before this phase would, the notice's settings are kept.
        $this->putJson(self::SETTINGS, $this->settings(['order_prefix' => 'web-']))->assertOk()
            ->assertJsonPath('data.order_prefix', 'WEB-')
            ->assertJsonPath('data.cookie_notice', true)
            ->assertJsonPath('data.cookie_notice_text', 'We use cookies to keep your cart.');

        // Off: no notice on the storefronts. Blank words: izSuite's own.
        $this->putJson(self::SETTINGS, $this->settings(['cookie_notice' => false, 'cookie_notice_text' => ' ']))->assertOk()
            ->assertJsonPath('data.cookie_notice', false)
            ->assertJsonPath('data.cookie_notice_text', null);
        $this->getJson(self::STORE)->assertJsonPath('data.cookie_notice', null);

        $this->putJson(self::SETTINGS, $this->settings(['cookie_notice_text' => str_repeat('a', 301)]))
            ->assertJsonValidationErrors(['cookie_notice_text' => 'Keep the notice to 300 characters.']);
    }
}
