<?php

namespace Tests\Feature;

use App\Models\User\CatalogCollection;
use App\Models\User\Storefront;
use App\Models\User\WorkspaceFile;
use App\Support\StorefrontTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 19 Phase C6a: a storefront's SEO (section 19.36) - its search and
 * sharing in its theme (the home's title and description, its sharing
 * picture, and whether search engines may list it), drafted and published
 * with the rest of it; whether a product is listed or kept for its link; and
 * what its sitemap lists, leaving out what is not listed for anyone.
 */
class EcommerceStorefrontSeoTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsIntoBusinesses;

    private string $theme;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->openStorefront([
            'Ceramic Mug' => ['sale_price' => '8.00', 'in_kitchen' => true],
            'Tea Pot'     => ['sale_price' => '20.00'],
            'Old Kettle'  => ['sale_price' => '15.00'],
        ]);

        $this->listed('Ceramic Mug');
        $this->listed('Tea Pot', ['visibility' => 'link']);
        $this->listed('Old Kettle', ['status' => 'hidden']);

        $this->theme = '/api/v1/ecommerce/storefronts/'.$this->store->id.'/theme';
    }

    public function test_its_search_and_sharing_are_drafted_and_published_with_its_theme(): void
    {
        $this->getJson(self::STORE)->assertJsonPath('data.theme.seo', ['title' => null, 'description' => null, 'image_url' => null, 'index' => true]);

        $picture = $this->post($this->theme.'/pictures', ['files' => [UploadedFile::fake()->image('sharing.png', 1200, 630)]], ['Accept' => 'application/json'])->assertCreated()->json('data.0.id');

        $this->putJson($this->theme, [...StorefrontTheme::DEFAULTS, 'seo' => ['title' => ' Rahman Shop: Tea and Kitchenware ', 'description' => 'Tea and mugs from Dhaka.', 'image' => $picture, 'index' => false]])->assertOk()
            ->assertJsonPath('data.draft.seo', ['title' => 'Rahman Shop: Tea and Kitchenware', 'description' => 'Tea and mugs from Dhaka.', 'image' => $picture, 'index' => false]);

        // Its customers see the published one's - listed still; its team previewing, the draft's.
        $this->getJson(self::STORE)->assertJsonPath('data.theme.seo.index', true)->assertJsonPath('data.theme.seo.title', null);
        $preview = $this->getJson(self::STORE.'?preview=1')->assertJsonPath('data.theme.seo.index', false)->assertJsonPath('data.theme.seo.title', 'Rahman Shop: Tea and Kitchenware');
        $this->assertNotNull($preview->json('data.theme.seo.image_url'));

        // Published: the sharing picture kept, as the theme shows it.
        $this->postJson($this->theme.'/publish')->assertOk();
        $this->getJson(self::STORE)->assertJsonPath('data.theme.seo.index', false)->assertJsonPath('data.theme.seo.description', 'Tea and mugs from Dhaka.');
        $this->assertNotNull(WorkspaceFile::find($picture));

        $this->putJson($this->theme, [...StorefrontTheme::DEFAULTS, 'seo' => ['image' => 999999]])
            ->assertJsonValidationErrors(['seo.image' => 'Choose a picture uploaded for this storefront.']);
        $this->putJson($this->theme, [...StorefrontTheme::DEFAULTS, 'seo' => ['description' => str_repeat('a', 321)]])
            ->assertJsonValidationErrors(['seo.description' => 'Keep the description to 320 characters.']);
    }

    public function test_the_sitemap_lists_what_search_engines_may_list(): void
    {
        foreach (['Summer Sale' => 'active', 'Hidden One' => 'hidden'] as $name => $status) {
            (new CatalogCollection(['name' => $name, 'slug' => \Illuminate\Support\Str::slug($name), 'kind' => 'promotion', 'status' => $status]))->forceFill(['workspace_id' => $this->business->id])->save();
        }

        $pages = '/api/v1/ecommerce/storefronts/'.$this->store->id.'/pages';
        $this->putJson($pages.'/privacy', ['title' => 'Privacy Policy', 'body' => '<p>What we keep.</p>', 'status' => 'published', 'in_footer' => true])->assertOk();
        $this->putJson($pages.'/about', ['title' => 'About Us', 'body' => '<p>Our story.</p>', 'status' => 'draft', 'in_footer' => true])->assertOk();

        $sitemap = $this->getJson(self::STORE.'/sitemap')->assertOk()->assertJsonPath('data.index', true);
        $this->assertSame(['', '/shop', '/categories/kitchen', '/collections/summer-sale', '/products/ceramic-mug', '/pages/privacy-policy'], array_column($sitemap->json('data.urls'), 'path'));
        $this->assertSame(now()->toDateString(), $sitemap->json('data.urls.4.updated_at'));

        // A product kept for its link opens at it, but is listed nowhere - no search engine is pointed at it.
        $this->getJson(self::STORE.'/products/tea-pot')->assertOk()->assertJsonPath('data.listed', false);
        $this->getJson(self::STORE.'/products/ceramic-mug')->assertOk()->assertJsonPath('data.listed', true);

        // Its published theme says not to list it: the sitemap lists nothing.
        $this->store->forceFill(['theme' => StorefrontTheme::of(['seo' => ['index' => false]])])->save();
        $this->getJson(self::STORE.'/sitemap')->assertOk()->assertJsonPath('data', ['index' => false, 'urls' => []]);

        // A store not open has no sitemap - its team previewing it neither.
        $this->store->update(['status' => Storefront::DRAFT]);
        $this->getJson(self::STORE.'/sitemap')->assertNotFound();
        $this->getJson(self::STORE.'/sitemap?preview=1')->assertNotFound();
    }
}
