<?php

namespace Tests\Feature;

use App\Models\User\CatalogCategory;
use App\Models\User\CatalogCollection;
use App\Models\User\CatalogProduct;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceFile;
use App\Support\StorefrontTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 19 Phase C4b: a storefront's home page (sections 19.6 and 19.7) as its
 * theme lays it out - banners with pictures of its own, collections, the
 * menu's categories, products and words of its own - drafted, previewed and
 * published with the rest of its theme.
 */
class EcommerceStorefrontHomeTest extends TestCase
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
            'Tea Pot'     => ['sale_price' => '20.00', 'in_kitchen' => true],
            'Black Tea'   => ['sale_price' => '3.50'],
        ]);

        foreach (array_keys($this->items) as $name) {
            $this->listed($name);
        }

        $this->theme = '/api/v1/ecommerce/storefronts/'.$this->store->id.'/theme';
    }

    private function collection(string $name, array $products = [], array $fields = [], ?Workspace $business = null): CatalogCollection
    {
        $collection = (new CatalogCollection(['name' => $name, 'slug' => Str::slug($name), 'kind' => 'promotion', ...$fields]))->forceFill(['workspace_id' => ($business ?? $this->business)->id]);
        $collection->save();

        foreach (array_values($products) as $position => $title) {
            $collection->products()->attach(CatalogProduct::where('title', $title)->sole()->id, ['position' => $position]);
        }

        return $collection;
    }

    /** The default theme with $home as its home's sections, saved as the draft. */
    private function draftHome(array $home): TestResponse
    {
        return $this->putJson($this->theme, [...StorefrontTheme::DEFAULTS, 'home' => $home]);
    }

    /** The store's home page's sections, as its customers - or, with ?preview=1, its team - see them. */
    private function sections(string $query = ''): array
    {
        return $this->getJson(self::STORE.'/home'.$query)->assertOk()->json('data.sections');
    }

    public function test_the_home_is_the_default_until_its_sections_are_chosen(): void
    {
        $this->collection('Summer Sale', ['Tea Pot']);
        $this->collection('Hidden One', [], ['status' => 'hidden']);

        $sections = $this->sections();

        $this->assertSame(['banner', 'collections', 'categories', 'products'], array_column($sections, 'type'));
        $this->assertSame(['type' => 'banner', 'image_url' => null, 'heading' => 'Rahman Shop', 'text' => null, 'button' => 'Shop all', 'link' => ['to' => 'shop', 'slug' => null]], $sections[0]);
        $this->assertSame(['Summer Sale'], array_column($sections[1]['collections'], 'name'));
        $this->assertSame([['name' => 'Kitchen', 'slug' => 'kitchen']], $sections[2]['categories']);
        $this->assertSame(['New in', null], [$sections[3]['heading'], $sections[3]['collection']]);
        $this->assertSame(['Black Tea', 'Tea Pot', 'Ceramic Mug'], array_column($sections[3]['products'], 'title'));

        // A section with nothing to show is left out.
        CatalogCollection::query()->update(['status' => 'hidden']);
        $this->assertSame(['banner', 'categories', 'products'], array_column($this->sections(), 'type'));
    }

    public function test_its_sections_are_drafted_previewed_and_published(): void
    {
        $sale    = $this->collection('Summer Sale', ['Tea Pot', 'Ceramic Mug']);
        $teas    = $this->collection('Teas', ['Black Tea']);
        $kitchen = CatalogCategory::where('item_category_id', $this->kitchen->id)->sole();
        $picture = $this->post($this->theme.'/pictures', ['files' => [UploadedFile::fake()->image('banner.png', 1600, 600)]], ['Accept' => 'application/json'])->assertCreated()->json('data.0.id');

        $this->draftHome([
            ['type' => 'banner', 'picture' => $picture, 'heading' => 'Summer is here', 'text' => 'Up to 20% off', 'button' => 'See the sale', 'link' => ['to' => 'collection', 'id' => $sale->id], 'ids' => [99], 'body' => 'Not a banner\'s'],
            ['type' => 'products', 'heading' => 'On sale', 'source' => 'collection', 'collection' => $sale->id, 'count' => 4],
            ['type' => 'collections', 'heading' => null, 'ids' => [$teas->id, $sale->id]],
            ['type' => 'banner', 'heading' => 'Kitchen things', 'button' => 'Browse', 'link' => ['to' => 'category', 'id' => $kitchen->id]],
            ['type' => 'banner', 'heading' => 'No button', 'button' => '', 'link' => ['to' => 'collection', 'id' => null]],
            ['type' => 'text', 'heading' => 'About us', 'body' => "Since 1990.\nFamily run."],
        ])->assertOk()
            ->assertJsonPath('message', 'Draft saved.')
            ->assertJsonPath('data.draft.home.0', ['type' => 'banner', 'heading' => 'Summer is here', 'picture' => $picture, 'text' => 'Up to 20% off', 'button' => 'See the sale', 'link' => ['to' => 'collection', 'id' => $sale->id]])
            ->assertJsonPath('data.draft.home.4.link', ['to' => 'shop', 'id' => null])
            ->assertJsonPath('data.choices.collections', [['id' => $sale->id, 'name' => 'Summer Sale'], ['id' => $teas->id, 'name' => 'Teas']])
            ->assertJsonPath('data.choices.categories', [['id' => $kitchen->id, 'name' => 'Kitchen']]);

        // Its customers see the home published - the default still; its team, previewing, the draft's.
        $this->assertSame('Rahman Shop', $this->sections()[0]['heading']);

        $preview = $this->sections('?preview=1');
        $this->assertSame(['banner', 'products', 'collections', 'banner', 'banner', 'text'], array_column($preview, 'type'));
        $this->assertSame(['heading' => 'Summer is here', 'text' => 'Up to 20% off', 'button' => 'See the sale', 'link' => ['to' => 'collection', 'slug' => 'summer-sale']], Arr::only($preview[0], ['heading', 'text', 'button', 'link']));
        $this->assertNotNull($preview[0]['image_url']);
        $this->assertSame(['On sale', 'summer-sale', ['Tea Pot', 'Ceramic Mug']], [$preview[1]['heading'], $preview[1]['collection'], array_column($preview[1]['products'], 'title')]);
        $this->assertSame([null, ['Teas', 'Summer Sale']], [$preview[2]['heading'], array_column($preview[2]['collections'], 'name')]);
        $this->assertSame(['to' => 'category', 'slug' => 'kitchen'], $preview[3]['link']);
        $this->assertSame([null, ['to' => 'shop', 'slug' => null]], [$preview[4]['button'], $preview[4]['link']]);
        $this->assertSame(['type' => 'text', 'heading' => 'About us', 'body' => "Since 1990.\nFamily run."], $preview[5]);

        $this->postJson($this->theme.'/publish')->assertOk();
        $this->assertSame(array_column($preview, 'type'), array_column($this->sections(), 'type'));
        $this->assertNotNull(WorkspaceFile::find($picture));

        // A collection no longer shown: the button that named it goes to the shop, and its products and tile leave.
        $sale->forceFill(['status' => 'hidden'])->save();
        $now = $this->sections();
        $this->assertSame(['banner', 'collections', 'banner', 'banner', 'text'], array_column($now, 'type'));
        $this->assertSame(['to' => 'shop', 'slug' => null], $now[0]['link']);
        $this->assertSame(['Teas'], array_column($now[1]['collections'], 'name'));

        // The banner taken out and published: its picture goes.
        $this->draftHome([['type' => 'categories', 'heading' => 'Shop by category']])->assertOk();
        $this->postJson($this->theme.'/publish')->assertOk();
        $this->assertNull(WorkspaceFile::find($picture));
        $this->assertSame([['type' => 'categories', 'heading' => 'Shop by category', 'categories' => [['name' => 'Kitchen', 'slug' => 'kitchen']]]], $this->sections());
    }

    public function test_its_sections_are_checked(): void
    {
        $theirs = $this->collection('Theirs', [], [], $this->businessOf(User::factory()->create()));

        $this->draftHome([
            ['type' => 'banner', 'picture' => 999999, 'button' => 'Go', 'link' => ['to' => 'collection', 'id' => null]],
            ['type' => 'banner', 'button' => 'Go', 'link' => ['to' => 'category', 'id' => 999999]],
            ['type' => 'collections', 'ids' => [$theirs->id]],
            ['type' => 'products', 'source' => 'collection', 'collection' => $theirs->id, 'count' => 8],
        ])->assertJsonValidationErrors([
            'home.0.picture'    => 'Choose a picture uploaded for this storefront.',
            'home.0.link.id'    => 'Choose where it goes.',
            'home.1.link.id'    => 'Choose a category of this business.',
            'home.2.ids'        => 'Choose collections of this business.',
            'home.3.collection' => 'Choose a collection of this business.',
        ]);

        $this->draftHome([['type' => 'text', 'body' => ' ']])->assertJsonValidationErrors(['home.0.body' => 'Write its words.']);
        $this->draftHome([['type' => 'products', 'source' => 'collection', 'count' => 8]])->assertJsonValidationErrors(['home.0.collection' => 'Choose its collection.']);
        $this->draftHome([['type' => 'products', 'source' => 'newest', 'count' => 5]])->assertJsonValidationErrors('home.0.count');
        $this->draftHome([['type' => 'slider']])->assertJsonValidationErrors('home.0.type');
        $this->draftHome(array_fill(0, 11, ['type' => 'categories']))->assertJsonValidationErrors(['home' => 'A home page keeps up to 10 sections.']);

        // No sections at all: a home of none, kept as such.
        $this->draftHome([])->assertOk()->assertJsonPath('data.draft.home', []);
        $this->assertSame([], $this->sections('?preview=1'));
    }
}
