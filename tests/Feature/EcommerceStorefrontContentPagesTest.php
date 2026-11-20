<?php

namespace Tests\Feature;

use App\Models\Shared\ActivityLog;
use App\Models\User\StorefrontPage;
use App\Models\User\User;
use App\Models\User\WorkspaceMember;
use App\Support\Permissions;
use App\Support\StorefrontTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 19 Phase C5a: a storefront's pages (section 19.6) - its contact and
 * about pages and its four policies - written, previewed and published under
 * Manage Themes and Pages, each at its link, the footer linking to those it
 * should, and the contact page with the store's contact details.
 */
class EcommerceStorefrontContentPagesTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsIntoBusinesses;

    private string $pages;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openStorefront(['Ceramic Mug' => ['sale_price' => '8.00']]);
        $this->pages = '/api/v1/ecommerce/storefronts/'.$this->store->id.'/pages';
    }

    private function page(array $fields = []): array
    {
        return [
            'title'           => 'Privacy Policy',
            'body'            => '<h2>What we keep</h2><p>Your <strong>name</strong> and address.</p><script>alert(1)</script>',
            'status'          => 'draft',
            'in_footer'       => true,
            'seo_title'       => null,
            'seo_description' => null,
            ...$fields,
        ];
    }

    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    private function logged(string $description): bool
    {
        return ActivityLog::query()->where('description', $description)->exists();
    }

    public function test_the_pages_start_unwritten_then_are_written_previewed_and_published(): void
    {
        $this->getJson($this->pages)->assertOk()
            ->assertJsonPath('data.pages.*.kind', ['about', 'contact', 'shipping', 'returns', 'privacy', 'terms'])
            ->assertJsonPath('data.pages.*.status', [null, null, null, null, null, null])
            ->assertJsonPath('data.pages.4', ['kind' => 'privacy', 'title' => 'Privacy Policy', 'slug' => 'privacy-policy', 'status' => null, 'in_footer' => true, 'updated_at' => null]);

        $this->putJson($this->pages.'/privacy', $this->page())->assertOk()
            ->assertJsonPath('message', 'Page saved as a draft.')
            ->assertJsonPath('data.page.status', 'draft')
            ->assertJsonPath('data.page.body', '<h2>What we keep</h2><p>Your <strong>name</strong> and address.</p>');
        $this->assertTrue($this->logged('Added the page "Privacy Policy" of the storefront "Rahman Shop"'));

        // A draft: its team previewing the store sees it; its customers do not, nor the footer's link to it.
        $this->getJson(self::STORE.'/pages/privacy-policy')->assertNotFound()->assertJsonPath('message', 'There is no such page in this store.');
        $this->getJson(self::STORE.'/pages/privacy-policy?preview=1')->assertOk()->assertJsonPath('data.title', 'Privacy Policy');
        $this->getJson(self::STORE)->assertJsonPath('data.pages', []);

        $this->putJson($this->pages.'/privacy', $this->page(['status' => 'published', 'seo_description' => 'How Rahman keeps your details.']))->assertOk()
            ->assertJsonPath('message', 'Page saved and published.');
        $this->assertTrue($this->logged('Published the page "Privacy Policy" of the storefront "Rahman Shop"'));
        $this->getJson(self::STORE.'/pages/Privacy-Policy')->assertOk()->assertJsonPath('data', [
            'kind'    => 'privacy',
            'title'   => 'Privacy Policy',
            'body'    => '<h2>What we keep</h2><p>Your <strong>name</strong> and address.</p>',
            'contact' => null,
            'seo'     => ['title' => 'Privacy Policy', 'description' => 'How Rahman keeps your details.'],
        ]);

        // The footer links to the pages published, in its order; one kept out of it is still at its link.
        $this->putJson($this->pages.'/terms', $this->page(['title' => 'Terms', 'status' => 'published', 'in_footer' => false]))->assertOk();
        $this->putJson($this->pages.'/about', $this->page(['title' => 'Our Story', 'status' => 'published']))->assertOk();
        $this->getJson(self::STORE)->assertJsonPath('data.pages', [['title' => 'Our Story', 'slug' => 'about'], ['title' => 'Privacy Policy', 'slug' => 'privacy-policy']]);
        $this->getJson(self::STORE.'/pages/terms-and-conditions')->assertOk()->assertJsonPath('data.title', 'Terms');
        $this->getJson($this->pages)->assertJsonPath('data.pages.*.status', ['published', null, null, null, 'published', 'published']);

        // Saved again unchanged: no change of the page's logged. Unpublished: gone from the store.
        $this->putJson($this->pages.'/about', $this->page(['title' => 'Our Story', 'status' => 'published']))->assertOk();
        $this->assertFalse($this->logged('Updated the page "Our Story" of the storefront "Rahman Shop"'));
        $this->putJson($this->pages.'/privacy', $this->page())->assertOk();
        $this->assertTrue($this->logged('Unpublished the page "Privacy Policy" of the storefront "Rahman Shop"'));
        $this->getJson(self::STORE.'/pages/privacy-policy')->assertNotFound();
    }

    public function test_the_contact_page_shows_the_stores_details(): void
    {
        $this->putJson($this->pages.'/contact', $this->page(['title' => 'Contact Us', 'body' => null, 'status' => 'published']))->assertOk();

        $this->getJson(self::STORE.'/pages/contact')->assertOk()
            ->assertJsonPath('data.body', null)
            ->assertJsonPath('data.contact', ['email' => 'help@rahman.test', 'phone' => null, 'address' => null, 'hours' => null]);

        // Its address and opening hours are the theme's: the draft's to its team previewing, the published one's to its customers.
        $theme = '/api/v1/ecommerce/storefronts/'.$this->store->id.'/theme';
        $this->putJson($theme, [...StorefrontTheme::DEFAULTS, 'contact' => ['address' => 'Road 7, Dhaka', 'hours' => 'Every day, 10 to 8']])->assertOk();
        $this->getJson(self::STORE.'/pages/contact')->assertJsonPath('data.contact.address', null);
        $this->getJson(self::STORE.'/pages/contact?preview=1')->assertJsonPath('data.contact.address', 'Road 7, Dhaka');

        $this->postJson($theme.'/publish')->assertOk();
        $this->getJson(self::STORE.'/pages/contact')->assertJsonPath('data.contact', ['email' => 'help@rahman.test', 'phone' => null, 'address' => 'Road 7, Dhaka', 'hours' => 'Every day, 10 to 8']);
    }

    public function test_pages_are_checked_and_kept_to_their_team(): void
    {
        $this->putJson($this->pages.'/privacy', $this->page(['body' => '<p> </p>', 'status' => 'published']))->assertJsonValidationErrors(['body' => 'Write the page before publishing it.']);
        $this->putJson($this->pages.'/privacy', $this->page(['title' => '']))->assertJsonValidationErrors(['title' => 'Give the page a title.']);
        $this->putJson($this->pages.'/privacy', $this->page(['status' => 'archived']))->assertJsonValidationErrors('status');
        $this->putJson($this->pages.'/careers', $this->page())->assertNotFound();
        $this->getJson(self::STORE.'/pages/nothing')->assertNotFound();

        // Plain text - as an API client may send it - is kept as paragraphs.
        $this->putJson($this->pages.'/shipping', $this->page(['title' => 'Shipping', 'body' => "We ship in 3 days.\n\nFree over \$50."]))->assertOk()
            ->assertJsonPath('data.page.body', '<p>We ship in 3 days.</p><p>Free over $50.</p>');

        // One who may only view eCommerce may not see its pages, nor preview them; one who may manage its themes and pages may.
        Sanctum::actingAs($this->member(Permissions::expand(['ecommerce.store.view'])));
        $this->getJson($this->pages)->assertForbidden();
        $this->putJson($this->pages.'/shipping', $this->page())->assertForbidden();
        $this->getJson(self::STORE.'/pages/shipping-policy?preview=1')->assertNotFound();

        Sanctum::actingAs($this->member(Permissions::expand(['ecommerce.themes.manage'])));
        $this->getJson($this->pages.'/shipping')->assertOk()->assertJsonPath('data.page.title', 'Shipping');
        $this->getJson(self::STORE.'/pages/shipping-policy?preview=1')->assertOk();

        // A storefront deleted takes its pages with it.
        Sanctum::actingAs($this->user);
        $this->deleteJson('/api/v1/ecommerce/storefronts/'.$this->store->id)->assertOk();
        $this->assertSame(0, StorefrontPage::count());
    }
}
