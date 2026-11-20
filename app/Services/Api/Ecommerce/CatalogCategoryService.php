<?php

namespace App\Services\Api\Ecommerce;

use App\Enums\FileCollection;
use App\Models\User\CatalogCategory;
use App\Models\User\ItemCategory;
use App\Models\User\Workspace;
use App\Repositories\User\CatalogCategoryRepository;
use App\Services\Api\Files\RecordFileService;
use App\Services\Shared\ActivityLogService;
use App\Support\RichText;
use App\Support\SettingChanges;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The item categories as the storefronts show them (Doc 19 section 19.11 -
 * Phase B2): each category's link - given, or made from its name, its own
 * in the business - its words (the editor's formatting kept), picture and
 * SEO, whether the storefront's menu lists it, and where. The categories
 * themselves stay the shared Items' own. Each change is logged.
 */
class CatalogCategoryService
{
    public function __construct(
        protected CatalogCategoryRepository $categories,
        protected RecordFileService $recordFiles,
    ) {}

    public function list(Workspace $workspace): Collection
    {
        return $this->categories->categoriesFor($workspace);
    }

    /** $data is validated (see SaveCatalogCategoryRequest). */
    public function save(Workspace $workspace, int $categoryId, array $data): ItemCategory
    {
        $category = $this->category($workspace, $categoryId);
        $online   = $category->catalogCategory;
        $slug     = Str::lower(trim((string) ($data['slug'] ?? '')));

        if ($slug === '') {
            $slug = $online?->slug ?? $this->freeSlug($workspace, Str::slug($category->name) ?: 'category', $online?->id);
        } elseif ($this->categories->slugTaken($workspace, $slug, $online?->id)) {
            throw ValidationException::withMessages(['slug' => 'Another category has this link.']);
        }

        $text = fn (?string $value) => ($value = trim((string) $value)) === '' ? null : $value;

        if ($this->categories->save($workspace, $category, [
            'slug'            => $slug,
            'description'     => RichText::clean($data['description'] ?? null),
            'seo_title'       => $text($data['seo_title'] ?? null),
            'seo_description' => $text($data['seo_description'] ?? null),
            'in_menu'         => (bool) $data['in_menu'],
            'position'        => (int) ($data['position'] ?? 0),
        ])) {
            ActivityLogService::record('updated', "Updated the online category {$this->quoted($category)}", $category->catalogCategory, ['source' => 'api', 'changes' => SettingChanges::of($category->catalogCategory)]);
        }

        return $this->category($workspace, $categoryId);
    }

    /** @param list<UploadedFile> $uploads */
    public function addImage(Workspace $workspace, int $categoryId, array $uploads, string $field): ItemCategory
    {
        $category = $this->category($workspace, $categoryId);
        $online   = $this->online($workspace, $category);

        $this->recordFiles->add($workspace, $online, FileCollection::Image, $uploads, $field, 'online category '.$this->quoted($category));

        return $this->category($workspace, $categoryId);
    }

    public function removeImage(Workspace $workspace, int $categoryId): ItemCategory
    {
        $category = $this->category($workspace, $categoryId);
        $image    = $category->catalogCategory?->image ?? abort(404, 'This category has no picture.');

        $this->recordFiles->remove($category->catalogCategory, FileCollection::Image, $image, 'online category '.$this->quoted($category));

        return $this->category($workspace, $categoryId);
    }

    /** Its online side - made, with its link from its name, the first time it is needed. */
    private function online(Workspace $workspace, ItemCategory $category): CatalogCategory
    {
        if (! $category->catalogCategory) {
            $this->categories->save($workspace, $category, ['slug' => $this->freeSlug($workspace, Str::slug($category->name) ?: 'category', null)]);
        }

        return $category->catalogCategory;
    }

    private function category(Workspace $workspace, int $id): ItemCategory
    {
        return $this->categories->findCategory($workspace, $id) ?? abort(404, 'There is no such category.');
    }

    /** $base, or $base-2, $base-3… - the first no other category of the business has. */
    private function freeSlug(Workspace $workspace, string $base, ?int $exceptId): string
    {
        $base = Str::limit($base, 190, '');

        for ($slug = $base, $n = 2; $this->categories->slugTaken($workspace, $slug, $exceptId); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }

    private function quoted(ItemCategory $category): string
    {
        return '"'.Str::limit($category->name, 100).'"';
    }
}
