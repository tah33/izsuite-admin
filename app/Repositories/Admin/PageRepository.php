<?php

namespace App\Repositories\Admin;

use App\Models\Frontend\Page;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PageRepository
{
    /**
     * Paginated list for admin.
     */
    public function getAllPaginated(int $perPage = 15): LengthAwarePaginator
    {
        return Page::orderBy('sort_order')
            ->orderBy('title')
            ->paginate(requested_per_page($perPage));
    }

    /**
     * Find page by ID.
     */
    public function find(int $id): ?Page
    {
        return Page::find($id);
    }

    /**
     * Find published page by slug.
     */
    public function findBySlug(string $slug): ?Page
    {
        return Page::published()->where('slug', $slug)->first();
    }

    /**
     * Get pages that should appear in the footer.
     */
    public function getFooterPages(): Collection
    {
        return Page::footer()->get(['title', 'slug']);
    }

    /**
     * Create a new page.
     */
    public function create(array $data): Page
    {
        return Page::create($data);
    }

    /**
     * Update a page.
     */
    public function update(Page $page, array $data): Page
    {
        $page->update($data);

        return $page->fresh();
    }

    /**
     * Delete a page.
     */
    public function delete(Page $page): void
    {
        $page->delete();
    }
}
