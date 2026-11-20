<?php

namespace App\Repositories\User;

use App\Models\User\Storefront;
use App\Models\User\StorefrontPage;
use Illuminate\Database\Eloquent\Collection;

/**
 * A storefront's pages (Doc 19 Phase C5a): its contact and about pages and
 * its four policies - and, from Phase C5b, pages of the business's own - as
 * its team writes them and as its customers see them.
 */
class StorefrontPageRepository
{
    /** Its pages kept so far, by kind. */
    public function standardOf(Storefront $storefront): Collection
    {
        return $storefront->pages()->whereIn('kind', array_keys(StorefrontPage::STANDARD))->get()->keyBy('kind');
    }

    /** Its page of $kind, if it is kept. */
    public function ofKind(Storefront $storefront, string $kind): ?StorefrontPage
    {
        return $storefront->pages()->where('kind', $kind)->first();
    }

    /** $page - or, none yet, its new page of $kind at its link - with $fields saved. */
    public function save(Storefront $storefront, ?StorefrontPage $page, string $kind, array $fields): StorefrontPage
    {
        $page ??= (new StorefrontPage(['kind' => $kind, 'slug' => StorefrontPage::STANDARD[$kind][1] ?? null]))
            ->forceFill(['workspace_id' => $storefront->workspace_id, 'storefront_id' => $storefront->id]);

        $page->fill($fields)->save();

        return $page;
    }

    /** Phase C5b: its own pages, in their places in the footer. */
    public function own(Storefront $storefront): Collection
    {
        return $storefront->pages()->where('kind', StorefrontPage::OWN)->orderBy('position')->orderBy('id')->get();
    }

    /** Phase C5b: its own page $id, if it has one. */
    public function ownPage(Storefront $storefront, int $id): ?StorefrontPage
    {
        return $storefront->pages()->where('kind', StorefrontPage::OWN)->whereKey($id)->first();
    }

    /** Phase C5b: how many pages of its own it has, for the plan's limit. */
    public function ownCount(Storefront $storefront): int
    {
        return $storefront->pages()->where('kind', StorefrontPage::OWN)->count();
    }

    /** Phase C5b: whether another of its pages - not $except - is at $slug. */
    public function slugTaken(Storefront $storefront, string $slug, ?int $except = null): bool
    {
        return $storefront->pages()->where('slug', $slug)->when($except, fn ($query) => $query->whereKeyNot($except))->exists();
    }

    /** Phase C5b: where a new page of its own goes in the footer - after the others. */
    public function nextPosition(Storefront $storefront): int
    {
        return (int) $storefront->pages()->where('kind', StorefrontPage::OWN)->max('position') + 1;
    }

    public function delete(StorefrontPage $page): void
    {
        $page->delete();
    }

    /** The published pages its footer links to: their kinds, titles and links. */
    public function footerOf(Storefront $storefront): Collection
    {
        return $storefront->pages()
            ->where('status', StorefrontPage::PUBLISHED)
            ->where('in_footer', true)
            ->get(['id', 'kind', 'title', 'slug', 'position']);
    }

    /** Phase C6a: its published pages' links, for its sitemap, with when each last changed. */
    public function publishedOf(Storefront $storefront): Collection
    {
        return $storefront->pages()->where('status', StorefrontPage::PUBLISHED)->orderBy('id')->get(['slug', 'updated_at']);
    }

    /** Its page at $slug: published - or a draft too, for its team previewing it. */
    public function atLink(Storefront $storefront, string $slug, bool $drafts): ?StorefrontPage
    {
        return $storefront->pages()
            ->where('slug', strtolower($slug))
            ->when(! $drafts, fn ($query) => $query->where('status', StorefrontPage::PUBLISHED))
            ->first();
    }
}
