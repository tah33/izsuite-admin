<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\Storefront;
use App\Repositories\User\StorefrontCatalogRepository;
use App\Repositories\User\StorefrontTaxonomyRepository;
use App\Repositories\User\StorefrontThemeRepository;
use Illuminate\Support\Collection;

/**
 * A storefront's home page as its theme lays it out (Doc 19 section 19.7 -
 * Phase C4b): each of its sections with what it shows today.
 *
 * A banner: its picture, its heading - the store's name when it has none -
 * its line, and its button's link to the shop, a category or a collection,
 * the shop's once that is no longer shown. Collections: those chosen that it
 * shows, in the order chosen, or every one it shows. Categories: its menu's.
 * Products: its newest, or a collection's in its order. Text: its words. A
 * section with nothing to show is left out.
 */
class StorefrontHome
{
    public function __construct(
        protected StorefrontCatalogRepository $catalog,
        protected StorefrontTaxonomyRepository $taxonomy,
        protected StorefrontThemeRepository $themes,
    ) {}

    /** @return list<array> its sections, each with what it shows */
    public function sections(Storefront $storefront, array $theme, string $today): array
    {
        $pictures = null;
        $shown    = null;

        // Its pictures, and the collections it shows today, by id: each looked up once, if a section asks.
        $picture = function (?int $id) use (&$pictures, $storefront) {
            $pictures ??= $this->themes->pictures($storefront)->keyBy('id');

            return $id ? $pictures->get($id) : null;
        };
        $collections = function () use (&$shown, $storefront, $today): Collection {
            return $shown ??= $this->taxonomy->collections($storefront, $today)->keyBy('id');
        };

        $sections = array_map(fn (array $section) => match ($section['type']) {
            'banner'      => [...$section, 'heading' => $section['heading'] ?? $storefront->name, 'picture' => $picture($section['picture']), 'link' => $this->link($storefront, $section['link'], $collections)],
            'collections' => $this->collections($section, $collections()),
            'categories'  => ($menu = $this->catalog->menuOf($storefront))->isEmpty() ? null : [...$section, 'categories' => $menu],
            'products'    => $this->products($storefront, $section, $today, $collections),
            'text'        => $section,
        }, $theme['home']);

        return array_values(array_filter($sections));
    }

    /** Those chosen that it shows, in the order chosen - or, none chosen, every one it shows. */
    private function collections(array $section, Collection $shown): ?array
    {
        $chosen = $section['ids'] ? collect($section['ids'])->map(fn (int $id) => $shown->get($id))->filter()->values() : $shown->values();

        return $chosen->isEmpty() ? null : [...$section, 'collections' => $chosen];
    }

    /** Its newest products, or those of a collection it shows, in its order. */
    private function products(Storefront $storefront, array $section, string $today, \Closure $collections): ?array
    {
        $collection = $section['source'] === 'collection' ? $collections()->get($section['collection']) : null;

        if ($section['source'] === 'collection' && ! $collection) {
            return null;
        }

        $products = $this->catalog->products($storefront, $collection ? [] : ['sort' => 'newest'], $today, $section['count'], $collection)->items();

        return $products ? [...$section, 'collection' => $collection, 'products' => $products] : null;
    }

    /**
     * Where a banner's button goes: the shop, unless the category or
     * collection it names is shown.
     *
     * @return array{to: string, slug: ?string}
     */
    private function link(Storefront $storefront, array $link, \Closure $collections): array
    {
        $slug = match ($link['to']) {
            'category'   => $this->taxonomy->categorySlug($storefront, (int) $link['id']),
            'collection' => $collections()->get($link['id'])?->slug,
            default      => null,
        };

        return $slug === null ? ['to' => 'shop', 'slug' => null] : ['to' => $link['to'], 'slug' => $slug];
    }
}
