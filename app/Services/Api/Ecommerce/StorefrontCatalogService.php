<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\CatalogCategory;
use App\Models\User\CatalogCollection;
use App\Models\User\CatalogProduct;
use App\Models\User\Storefront;
use App\Models\User\StorefrontPage;
use App\Models\User\User;
use App\Repositories\User\EcommerceSettingRepository;
use App\Repositories\User\StorefrontCatalogRepository;
use App\Repositories\User\StorefrontPageRepository;
use App\Repositories\User\StorefrontTaxonomyRepository;
use App\Repositories\User\StorefrontThemeRepository;
use App\Services\Shared\BusinessCalendar;
use App\Services\Shared\WorkspaceAccess;
use App\Services\User\Apps\WorkspaceApps;
use App\Support\BusinessStatus;
use App\Support\StorefrontTheme;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * A storefront as its customers see it (Doc 19 sections 19.5, 19.6 and
 * 19.12 - Phase C1), by its code and without signing in.
 *
 * It is open while it is Live, its business has eCommerce on and is not
 * suspended or closed; otherwise it says only its name and that it is not
 * open - its products are not found. Open, it shows the listings published
 * (or scheduled and begun) and not over, of active items it sells, on every
 * storefront or on this one: for anyone in its lists and search, and those
 * kept for their link at their link too. Listings for signed-in customers
 * wait for customer accounts (Phase E). A listing's variants are those shown,
 * of active items. Prices are StorefrontPricing's, on the business's day.
 *
 * Phase C4a: it wears its published theme. A $previewer - signed in, who may
 * manage its themes and pages - sees it in its draft theme, and sees it open
 * before it is. Phase C4b: its home page is its theme's sections
 * (StorefrontHome). Phase C5a: its pages - contact, about and its policies -
 * published, the footer linking to those it should.
 */
class StorefrontCatalogService
{
    /** How many products, and how many categories and collections, the search suggests (Phase C2). */
    private const SUGGESTED_PRODUCTS = 6;

    private const SUGGESTED_NAMES = 3;

    /** Phase C3: how many listings the filters by price, options and attributes look through. */
    private const FACETED = 2000;

    /** Phase C6a: how many products its sitemap lists - within a sitemap's 50,000 links. */
    private const SITEMAP_PRODUCTS = 45000;

    public function __construct(
        protected StorefrontCatalogRepository $catalog,
        protected StorefrontTaxonomyRepository $taxonomy,
        protected StorefrontThemeRepository $themes,
        protected StorefrontPricing $pricing,
        protected StorefrontFacets $facets,
        protected StorefrontHome $home,
        protected StorefrontPageRepository $pages,
        protected EcommerceSettingRepository $settings,
        protected WorkspaceAccess $access,
        protected BusinessCalendar $calendar,
    ) {}

    /**
     * The store: whether it is open, and - open - its price terms, its menu,
     * the brands of what it shows (while the business keeps brands), and its
     * theme (Phase C4a): the draft for a $previewer.
     *
     * @return array{storefront: Storefront, open: bool, preview?: bool, terms?: array, menu?: \Illuminate\Support\Collection, brands?: ?\Illuminate\Support\Collection, theme?: array}
     */
    public function store(string $code, ?User $previewer = null): array
    {
        $storefront = $this->catalog->findStore($code) ?? abort(404, 'There is no such store.');
        $previewing = $this->previews($storefront, $previewer);

        if (! $previewing && ! $this->isOpen($storefront)) {
            return ['storefront' => $storefront, 'open' => false];
        }

        $today = $this->today($storefront);

        return [
            'storefront' => $storefront,
            'open'       => true,
            'preview'    => $previewing,
            // Whether its customers see it open too: a preview may open it before it is.
            'customers'  => ! $previewing || $this->isOpen($storefront),
            'terms'      => $this->pricing->terms($storefront->workspace, $storefront, $today),
            'menu'       => $this->catalog->menuOf($storefront),
            'brands'     => $storefront->workspace->brands_enabled ? $this->catalog->brandsOf($storefront, $today) : null,
            // Phase C5a: the pages its footer links to, in the footer's order.
            'pages'      => $this->footerPages($storefront),
            // Phase C6b: whether its visitors are shown a cookie notice, its words, and whether it has a privacy policy published to link to.
            'settings'   => $this->settings->forWorkspace($storefront->workspace),
            'privacy'    => $this->pages->atLink($storefront, StorefrontPage::STANDARD['privacy'][1], false) !== null,
            'theme'      => [
                'theme'      => $this->themeOf($storefront, $previewing),
                'pictures'   => $this->themes->pictures($storefront),
                'powered_by' => ! $storefront->workspace->terms()?->removes_branding,
            ],
        ];
    }

    /**
     * Phase C4b: its home page - its theme's sections, the draft's for a
     * $previewer - with what each shows today.
     *
     * @return array{sections: list<array>, terms: array}
     */
    public function home(string $code, ?User $previewer = null): array
    {
        $storefront = $this->open($code, $previewer);
        $today      = $this->today($storefront);

        return [
            'sections' => $this->home->sections($storefront, $this->themeOf($storefront, $this->previews($storefront, $previewer)), $today),
            'terms'    => $this->pricing->terms($storefront->workspace, $storefront, $today),
        ];
    }

    /**
     * Its products as $filters ask - within a collection it shows (Phase C2),
     * by its link, when they name one; by price, options and attributes
     * (Phase C3) as StorefrontFacets passes them.
     *
     * @return array{products: LengthAwarePaginator, terms: array}
     */
    public function products(string $code, array $filters, int $perPage, ?User $previewer = null): array
    {
        $storefront = $this->open($code, $previewer);
        $today      = $this->today($storefront);
        $collection = isset($filters['collection']) ? $this->shownCollection($storefront, $filters['collection'], $today) : null;
        $terms      = $this->pricing->terms($storefront->workspace, $storefront, $today);
        $ids        = null;

        if (isset($filters['price_min']) || isset($filters['price_max']) || ! empty($filters['option']) || ! empty($filters['attribute'])) {
            $ids = $this->facets->passing($this->catalog->listingsWithin($storefront, $filters, $today, $collection, self::FACETED), $filters, $terms);
        }

        return ['products' => $this->catalog->products($storefront, $filters, $today, $perPage, $collection, $ids), 'terms' => $terms];
    }

    /** Phase C3: what its products within $filters - search, category, collection, brand, stock - offer to filter by. */
    public function facets(string $code, array $filters, ?User $previewer = null): array
    {
        $storefront = $this->open($code, $previewer);
        $today      = $this->today($storefront);
        $collection = isset($filters['collection']) ? $this->shownCollection($storefront, $filters['collection'], $today) : null;

        return $this->facets->of(
            $this->catalog->listingsWithin($storefront, $filters, $today, $collection, self::FACETED),
            $this->pricing->terms($storefront->workspace, $storefront, $today),
        );
    }

    /** Phase C2: an online category's page, by its link. */
    public function category(string $code, string $slug, ?User $previewer = null): CatalogCategory
    {
        return $this->taxonomy->category($this->open($code, $previewer), $slug) ?? abort(404, 'There is no such category in this store.');
    }

    /** Phase C2: the collections it shows - active, within their days - in their places. */
    public function collections(string $code, ?User $previewer = null): Collection
    {
        $storefront = $this->open($code, $previewer);

        return $this->taxonomy->collections($storefront, $this->today($storefront));
    }

    /** Phase C2: a collection's page, by its link, while it shows it. */
    public function collection(string $code, string $slug, ?User $previewer = null): CatalogCollection
    {
        $storefront = $this->open($code, $previewer);

        return $this->shownCollection($storefront, $slug, $this->today($storefront));
    }

    /**
     * Phase C2: what its search suggests for $search - its first products to
     * match, as the shop finds them, and the categories and collections by
     * that name.
     *
     * @return array{products: list<CatalogProduct>, categories: Collection, collections: Collection}
     */
    public function suggestions(string $code, string $search, ?User $previewer = null): array
    {
        $storefront = $this->open($code, $previewer);
        $today      = $this->today($storefront);

        return [
            'products'    => $this->catalog->products($storefront, ['search' => $search], $today, self::SUGGESTED_PRODUCTS)->items(),
            'categories'  => $this->taxonomy->categoriesMatching($storefront, $search, self::SUGGESTED_NAMES),
            'collections' => $this->taxonomy->collectionsMatching($storefront, $search, $today, self::SUGGESTED_NAMES),
        ];
    }

    private function shownCollection(Storefront $storefront, string $slug, string $today): CatalogCollection
    {
        return $this->taxonomy->collection($storefront, $slug, $today) ?? abort(404, 'There is no such collection in this store.');
    }

    /** @return array{product: CatalogProduct, terms: array} */
    public function product(string $code, string $slug, ?User $previewer = null): array
    {
        $storefront = $this->open($code, $previewer);
        $today      = $this->today($storefront);

        return [
            'product' => $this->catalog->product($storefront, $slug, $today) ?? abort(404, 'There is no such product in this store.'),
            'terms'   => $this->pricing->terms($storefront->workspace, $storefront, $today),
        ];
    }

    /** The store at $code while it is open - or previewed: else not found, as a customer cannot tell which. */
    private function open(string $code, ?User $previewer): Storefront
    {
        $storefront = $this->catalog->findStore($code);

        return $storefront && ($this->isOpen($storefront) || $this->previews($storefront, $previewer)) ? $storefront : abort(404, 'This store is not open.');
    }

    /**
     * Phase C5a: its page at $slug - published, or a draft too for a
     * $previewer - and, for its contact page, its contact details: its support
     * e-mail and phone, and its theme's address and opening hours.
     *
     * @return array{page: StorefrontPage, contact: ?array}
     */
    public function page(string $code, string $slug, ?User $previewer = null): array
    {
        $storefront = $this->open($code, $previewer);
        $previewing = $this->previews($storefront, $previewer);
        $page       = $this->pages->atLink($storefront, $slug, $previewing) ?? abort(404, 'There is no such page in this store.');
        $theme      = $page->kind === StorefrontPage::CONTACT ? $this->themeOf($storefront, $previewing) : null;

        return [
            'page'    => $page,
            'contact' => $theme ? ['email' => $storefront->support_email, 'phone' => $storefront->support_phone, ...$theme['contact']] : null,
        ];
    }

    /**
     * Phase C6a: what its sitemap lists for search engines - while it is open
     * to its customers and its published theme lets them list it: its home
     * and shop, its categories' and collections' pages, the products it lists
     * for anyone (not those kept for their link), and its published pages -
     * each with the day it last changed, where it has one.
     *
     * @return array{index: bool, urls: list<array{path: string, updated_at: ?string}>}
     */
    public function sitemap(string $code): array
    {
        $storefront = $this->open($code, null);
        $today      = $this->today($storefront);

        if (! $this->themeOf($storefront, false)['seo']['index']) {
            return ['index' => false, 'urls' => []];
        }

        $at = fn (string $path, $model) => ['path' => $path, 'updated_at' => $model->updated_at?->toDateString()];

        return ['index' => true, 'urls' => [
            ['path' => '', 'updated_at' => $storefront->theme_published_at?->toDateString()],
            ['path' => '/shop', 'updated_at' => null],
            ...$this->taxonomy->sitemapCategories($storefront)->map(fn (CatalogCategory $online) => $at("/categories/{$online->slug}", $online))->all(),
            ...$this->taxonomy->collections($storefront, $today)->map(fn (CatalogCollection $collection) => $at("/collections/{$collection->slug}", $collection))->all(),
            ...$this->catalog->sitemapProducts($storefront, $today, self::SITEMAP_PRODUCTS)->map(fn (CatalogProduct $product) => $at("/products/{$product->slug}", $product))->all(),
            ...$this->pages->publishedOf($storefront)->map(fn (StorefrontPage $page) => $at("/pages/{$page->slug}", $page))->all(),
        ]];
    }

    /** Phase C5a: the published pages its footer links to - its contact and about pages and policies in their order. */
    private function footerPages(Storefront $storefront): Collection
    {
        $order = array_flip(array_keys(StorefrontPage::STANDARD));

        return $this->pages->footerOf($storefront)->sortBy(fn (StorefrontPage $page) => [$order[$page->kind] ?? count($order), $page->position, $page->id])->values();
    }

    /** Phase C4a: the theme it wears - its draft, for its team previewing it. */
    private function themeOf(Storefront $storefront, bool $previewing): array
    {
        return StorefrontTheme::of($previewing ? ($storefront->theme_draft ?? $storefront->theme) : $storefront->theme);
    }

    /** Phase C4a: whether $previewer may preview it - who may manage its themes and pages. */
    private function previews(Storefront $storefront, ?User $previewer): bool
    {
        return $previewer !== null && $this->access->allows($previewer, $storefront->workspace, 'ecommerce.themes.manage');
    }

    private function isOpen(Storefront $storefront): bool
    {
        $workspace = $storefront->workspace;

        return $storefront->status === Storefront::LIVE
            && ! in_array($workspace->status, BusinessStatus::SHUT, true)
            && WorkspaceApps::for($workspace)->has('ecommerce');
    }

    private function today(Storefront $storefront): string
    {
        return $this->calendar->today($storefront->workspace)->toDateString();
    }
}
