<?php

namespace App\Services\Api\Ecommerce;

use App\Enums\FileCollection;
use App\Models\User\CatalogCollection;
use App\Models\User\Workspace;
use App\Repositories\User\CatalogCollectionRepository;
use App\Services\Api\Files\RecordFileService;
use App\Services\Shared\ActivityLogService;
use App\Support\RichText;
use App\Support\SettingChanges;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The business's collections of products (Doc 19 section 19.11 - Phase B2):
 * listed, made, changed and deleted, with their pictures.
 *
 * A collection's name and link are its own in the business - the link given,
 * or made from its name. A brand's collection is every listed product of one
 * of the business's brands, while its brands are on; any other holds the
 * products chosen, in their order. It ends no earlier than it starts. Its
 * words keep the editor's formatting. Each change is logged; deleting one
 * leaves its products as they are.
 */
class CatalogCollectionService
{
    public function __construct(
        protected CatalogCollectionRepository $collections,
        protected RecordFileService $recordFiles,
    ) {}

    /** @return array{collections: Collection, brand_listings: array<int, int>} */
    public function list(Workspace $workspace): array
    {
        $collections = $this->collections->listFor($workspace);

        return ['collections' => $collections, 'brand_listings' => $this->collections->listingsByBrand($workspace, $collections->pluck('item_brand_id')->filter()->unique()->values()->all())];
    }

    /** What a collection's form chooses from: the listings, and the brands while the business keeps them. */
    public function options(Workspace $workspace): array
    {
        return ['products' => $this->collections->listingsOf($workspace), 'brands' => $workspace->brands_enabled ? $this->collections->brandsOf($workspace) : new Collection];
    }

    /** $data is validated (see SaveCatalogCollectionRequest). */
    public function create(Workspace $workspace, array $data): CatalogCollection
    {
        [$fields, $productIds] = $this->checked($workspace, $data);

        $collection = DB::transaction(fn () => $this->collections->create($workspace, $fields, $productIds));

        ActivityLogService::record('created', "Added the collection {$this->quoted($collection)}", $collection, ['source' => 'api']);

        return $collection;
    }

    public function update(Workspace $workspace, int $id, array $data): CatalogCollection
    {
        $collection = $this->find($workspace, $id);
        [$fields, $productIds] = $this->checked($workspace, $data, $collection);

        if (DB::transaction(fn () => $this->collections->update($collection, $fields, $productIds))) {
            ActivityLogService::record('updated', "Updated the collection {$this->quoted($collection)}", $collection, ['source' => 'api', 'changes' => SettingChanges::of($collection)]);
        }

        return $collection;
    }

    public function delete(Workspace $workspace, int $id): CatalogCollection
    {
        $collection = $this->find($workspace, $id);

        $this->collections->delete($collection);

        ActivityLogService::record('deleted', "Deleted the collection {$this->quoted($collection)}", $collection, ['source' => 'api']);

        return $collection;
    }

    /** @param list<UploadedFile> $uploads */
    public function addImage(Workspace $workspace, int $id, array $uploads, string $field): CatalogCollection
    {
        $collection = $this->find($workspace, $id);

        $this->recordFiles->add($workspace, $collection, FileCollection::Image, $uploads, $field, 'collection '.$this->quoted($collection));

        return $this->find($workspace, $id);
    }

    public function removeImage(Workspace $workspace, int $id): CatalogCollection
    {
        $collection = $this->find($workspace, $id);

        $this->recordFiles->remove($collection, FileCollection::Image, $collection->image ?? abort(404, 'This collection has no picture.'), 'collection '.$this->quoted($collection));

        return $this->find($workspace, $id);
    }

    /**
     * $data as a collection keeps it, with the products it holds by hand -
     * each part checked as the class says.
     *
     * @return array{0: array, 1: list<int>}
     */
    private function checked(Workspace $workspace, array $data, ?CatalogCollection $collection = null): array
    {
        $name = trim($data['name']);
        $slug = Str::lower(trim((string) ($data['slug'] ?? '')));

        if ($this->collections->taken($workspace, 'name', $name, $collection?->id)) {
            throw ValidationException::withMessages(['name' => 'Another collection has this name.']);
        }

        if ($slug === '') {
            $slug = $collection?->slug ?? $this->freeSlug($workspace, Str::slug($name) ?: 'collection');
        } elseif ($this->collections->taken($workspace, 'slug', $slug, $collection?->id)) {
            throw ValidationException::withMessages(['slug' => 'Another collection has this link.']);
        }

        $brandId    = null;
        $productIds = [];

        if ($data['kind'] === CatalogCollection::BRAND) {
            $brandId = (int) ($data['item_brand_id'] ?? 0);

            if (! $workspace->brands_enabled || ! $this->collections->brandsOf($workspace)->contains('id', $brandId)) {
                throw ValidationException::withMessages(['item_brand_id' => $workspace->brands_enabled ? 'Choose one of your brands.' : 'Brands are off: switch them on in Settings → Items.']);
            }
        } else {
            $productIds = array_values(array_unique(array_map('intval', $data['product_ids'] ?? [])));

            if (array_diff($productIds, $this->collections->listingsOf($workspace)->pluck('id')->all()) !== []) {
                throw ValidationException::withMessages(['product_ids' => 'Choose among the products in your catalog.']);
            }
        }

        $from  = $data['available_from'] ?? null;
        $until = $data['available_until'] ?? null;

        if ($from !== null && $until !== null && $until < $from) {
            throw ValidationException::withMessages(['available_until' => 'It cannot end before it starts.']);
        }

        $text = fn (?string $value) => ($value = trim((string) $value)) === '' ? null : $value;

        return [[
            'name'            => $name,
            'slug'            => $slug,
            'kind'            => $data['kind'],
            'item_brand_id'   => $brandId,
            'description'     => RichText::clean($data['description'] ?? null),
            'status'          => $data['status'],
            'available_from'  => $from,
            'available_until' => $until,
            'seo_title'       => $text($data['seo_title'] ?? null),
            'seo_description' => $text($data['seo_description'] ?? null),
            'position'        => (int) ($data['position'] ?? 0),
        ], $productIds];
    }

    private function find(Workspace $workspace, int $id): CatalogCollection
    {
        return $this->collections->find($workspace, $id) ?? abort(404, 'There is no such collection.');
    }

    /** $base, or $base-2, $base-3… - the first no other collection of the business has. */
    private function freeSlug(Workspace $workspace, string $base): string
    {
        $base = Str::limit($base, 190, '');

        for ($slug = $base, $n = 2; $this->collections->taken($workspace, 'slug', $slug); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }

    private function quoted(CatalogCollection $collection): string
    {
        return '"'.Str::limit($collection->name, 100).'"';
    }
}
