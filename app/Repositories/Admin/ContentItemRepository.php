<?php

namespace App\Repositories\Admin;

use App\Models\Frontend\ContentItem;
use Illuminate\Database\Eloquent\Collection;

class ContentItemRepository
{
    /** A section's items, in the order the landing page shows them. */
    public function forSection(string $section): Collection
    {
        return ContentItem::where('section', $section)->orderBy('sort_order')->get();
    }

    /** The highest sort order in $section, so a new item goes last. */
    public function maxSortOrder(string $section): int
    {
        return (int) ContentItem::where('section', $section)->max('sort_order');
    }

    public function findOrFail(int $id): ContentItem
    {
        return ContentItem::findOrFail($id);
    }

    public function create(array $data): ContentItem
    {
        return ContentItem::create($data);
    }

    public function update(ContentItem $item, array $data): ContentItem
    {
        $item->update($data);

        return $item;
    }

    public function delete(ContentItem $item): ?bool
    {
        return $item->delete();
    }

    /** Moves item $id to $sortOrder - a query update, so no model events run. */
    public function setSortOrder(int $id, int $sortOrder): void
    {
        ContentItem::whereKey($id)->update(['sort_order' => $sortOrder]);
    }
}
