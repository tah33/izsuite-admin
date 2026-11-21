<?php

namespace App\Services\Api\Ecommerce;

use App\Enums\FileCollection;
use App\Models\User\CatalogProduct;
use App\Models\User\CatalogProductVariant;
use App\Models\User\Item;
use App\Models\User\Workspace;
use App\Repositories\User\CatalogProductRepository;
use App\Services\Api\Files\RecordFileService;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\BusinessCalendar;
use App\Support\RichText;
use App\Support\SettingChanges;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The catalog (Doc 19 sections 19.8 and 19.9 - Phase B1): the business's
 * items for sale, and each one's online listing - put in the catalog,
 * changed, taken out again, and its pictures.
 *
 * A listing's link is its own in the business: given, or made from its
 * title. Published or scheduled, its item is active and - sold at a fixed
 * price - priced; scheduled, it starts after today, the business's day, and
 * ends no earlier than it starts. A limited availability says how many are
 * left. It is on every storefront of the business, or on those chosen. Its
 * full description keeps the editor's formatting, nothing else. Each change
 * is logged. One on an online order (Phase D4) is archived rather than taken out.
 */
class CatalogService
{
    public function __construct(
        protected CatalogProductRepository $catalog,
        protected RecordFileService $recordFiles,
        protected BusinessCalendar $calendar,
        protected \App\Repositories\User\EcommerceOrderRepository $orders,
    ) {}

    /**
     * The items for sale, each with its listing - see
     * CatalogProductRepository::paginateFor() - and today, the business's day.
     *
     * @return array{items: LengthAwarePaginator, today: string}
     */
    public function list(Workspace $workspace, array $filters, int $perPage): array
    {
        $today = $this->today($workspace);

        return ['items' => $this->catalog->paginateFor($workspace, $filters, $perPage, $today), 'today' => $today];
    }

    /**
     * The item, its listing, and - from Phase B3 - the listing it is a
     * variant of, when that is another item's.
     *
     * @return array{item: Item, product: ?CatalogProduct, variant_of: ?CatalogProduct, storefronts: Collection, today: string}
     */
    public function show(Workspace $workspace, int $itemId): array
    {
        $item = $this->item($workspace, $itemId);

        return [
            'item'        => $item,
            'product'     => $this->catalog->forItem($item),
            'variant_of'  => $this->variantOf($item),
            'storefronts' => $this->catalog->storefrontsOf($workspace),
            'today'       => $this->today($workspace),
        ];
    }

    /** $data is validated (see SaveCatalogProductRequest): the listing made, or changed. Another listing's variant has none of its own. */
    public function save(Workspace $workspace, int $itemId, array $data): array
    {
        $item = $this->item($workspace, $itemId);

        if ($listing = $this->variantOf($item)) {
            abort(409, "This item is a variant of {$this->quoted($listing->title)}: change it on that product's page.");
        }

        $product = $this->catalog->forItem($item);
        [$fields, $storefrontIds] = $this->checked($workspace, $item, $product, $data);

        if ($product === null) {
            $product = DB::transaction(fn () => $this->catalog->create($workspace, $item, $fields, $storefrontIds));

            ActivityLogService::record('created', "Put the item {$this->quoted($item->name)} in the catalog as {$this->quoted($product->title)}", $product, ['source' => 'api']);
        } elseif (DB::transaction(fn () => $this->catalog->update($product, $fields, $storefrontIds))) {
            ActivityLogService::record('updated', "Updated the online product {$this->quoted($product->title)}", $product, ['source' => 'api', 'changes' => SettingChanges::of($product)]);
        }

        return $this->show($workspace, $itemId);
    }

    /** Taken out of the catalog, with its pictures; its item stays as it is. */
    /**
     * Its listing taken out of the catalog, with its pictures - or, once it
     * is on an online order (Phase D4), archived instead, kept for the order:
     * whether it was archived.
     */
    public function remove(Workspace $workspace, int $itemId): array
    {
        $product = $this->product($workspace, $itemId);

        if ($this->orders->usesListing($product->id)) {
            $this->catalog->update($product, ['status' => CatalogProduct::ARCHIVED], $product->storefronts->pluck('id')->all());

            ActivityLogService::record('updated', "Archived {$this->quoted($product->title)}, as it is on online orders", $product, ['source' => 'api']);

            return ['product' => $product, 'archived' => true];
        }

        $this->catalog->delete($product);

        ActivityLogService::record('deleted', "Took {$this->quoted($product->title)} out of the catalog", $product, ['source' => 'api']);

        return ['product' => $product, 'archived' => false];
    }

    /**
     * @param  list<UploadedFile>  $uploads
     * @return Collection the pictures added
     */
    public function addPictures(Workspace $workspace, int $itemId, array $uploads, string $field): Collection
    {
        $product = $this->product($workspace, $itemId);

        if ($this->catalog->pictureCount($product) + count($uploads) > CatalogProduct::MAX_PICTURES) {
            throw ValidationException::withMessages([$field => 'A product keeps up to '.CatalogProduct::MAX_PICTURES.' pictures: remove some first.']);
        }

        return $this->recordFiles->add($workspace, $product, FileCollection::Gallery, $uploads, $field, $this->record($product));
    }

    public function removePicture(Workspace $workspace, int $itemId, int $fileId): void
    {
        $product = $this->product($workspace, $itemId);

        $this->recordFiles->remove($product, FileCollection::Gallery, $this->recordFiles->find($product, FileCollection::Gallery, $fileId), $this->record($product));
    }

    /**
     * $data as a listing keeps it, with the storefronts it is on - each part
     * checked as the class says.
     *
     * @return array{0: array, 1: list<int>}
     */
    private function checked(Workspace $workspace, Item $item, ?CatalogProduct $product, array $data): array
    {
        $title  = trim($data['title']);
        $slug   = Str::lower(trim((string) ($data['slug'] ?? '')));
        $status = $data['status'];
        $today  = $this->today($workspace);
        $from   = $data['available_from'] ?? null;
        $until  = $data['available_until'] ?? null;

        if ($slug === '') {
            $slug = $this->freeSlug($workspace, Str::slug($title) ?: 'product', $product?->id);
        } elseif ($this->catalog->slugTaken($workspace, $slug, $product?->id)) {
            throw ValidationException::withMessages(['slug' => 'Another product has this link.']);
        }

        if (in_array($status, [CatalogProduct::SCHEDULED, CatalogProduct::PUBLISHED], true)) {
            if (! $item->is_active) {
                throw ValidationException::withMessages(['status' => 'The item is switched off: switch it on in Items to publish it.']);
            }

            if ($data['sales_method'] === CatalogProduct::FIXED_PRICE && $item->sale_price === null) {
                throw ValidationException::withMessages(['sales_method' => 'The item has no sales price: give it one in Items, or sell it by quotation.']);
            }

            // Phase B3: and so is each other variant shown.
            foreach ($product?->variants ?? [] as $variant) {
                if ($variant->item_id === $item->id || $variant->status !== CatalogProductVariant::ACTIVE) {
                    continue;
                }

                if (! $variant->item->is_active) {
                    throw ValidationException::withMessages(['status' => "The variant {$this->quoted($variant->item->name)} is switched off: switch it on in Items, or hide the variant."]);
                }

                if ($data['sales_method'] === CatalogProduct::FIXED_PRICE && $variant->item->sale_price === null) {
                    throw ValidationException::withMessages(['sales_method' => "The variant {$this->quoted($variant->item->name)} has no sales price: give it one in Items, or hide the variant."]);
                }
            }
        }

        if ($status === CatalogProduct::SCHEDULED && ($from === null || $from <= $today)) {
            throw ValidationException::withMessages(['available_from' => 'A scheduled product starts on a later day: choose its first day.']);
        }

        if ($from !== null && $until !== null && $until < $from) {
            throw ValidationException::withMessages(['available_until' => 'It cannot end before it starts.']);
        }

        $limited = $data['availability'] === CatalogProduct::LIMITED;

        if ($limited && ($data['stock_quantity'] ?? null) === null) {
            throw ValidationException::withMessages(['stock_quantity' => 'Say how many are left to sell.']);
        }

        $everywhere    = (bool) ($data['all_storefronts'] ?? true);
        $storefrontIds = $everywhere ? [] : array_values(array_unique(array_map('intval', $data['storefront_ids'] ?? [])));
        $ours          = $this->catalog->storefrontsOf($workspace)->pluck('id')->all();

        if (! $everywhere && ($storefrontIds === [] || array_diff($storefrontIds, $ours) !== [])) {
            throw ValidationException::withMessages(['storefront_ids' => 'Choose the storefronts it is on.']);
        }

        $text = fn (?string $value) => ($value = trim((string) $value)) === '' ? null : $value;

        return [[
            'title'             => $title,
            'slug'              => $slug,
            'short_description' => $text($data['short_description'] ?? null),
            'description'       => RichText::clean($data['description'] ?? null),
            'keywords'          => $text($data['keywords'] ?? null),
            'status'            => $status,
            'visibility'        => $data['visibility'],
            'available_from'    => $from,
            'available_until'   => $until,
            'sales_method'      => $data['sales_method'],
            'availability'      => $data['availability'],
            'stock_quantity'    => $limited ? $data['stock_quantity'] : null,
            'weight'            => $data['weight'] ?? null,
            'weight_unit'       => $data['weight_unit'] ?? 'kg',
            'video_urls'        => array_values(array_unique(array_filter(array_map('trim', $data['video_urls'] ?? [])))),
            // Phase B2: each attribute once by its name, as given.
            'custom_attributes' => collect($data['custom_attributes'] ?? [])
                ->map(fn (array $attribute) => ['name' => trim($attribute['name']), 'value' => trim($attribute['value'])])
                ->filter(fn (array $attribute) => $attribute['name'] !== '' && $attribute['value'] !== '')
                ->unique(fn (array $attribute) => Str::lower($attribute['name']))
                ->values()
                ->all(),
            'seo_title'         => $text($data['seo_title'] ?? null),
            'seo_description'   => $text($data['seo_description'] ?? null),
            'all_storefronts'   => $everywhere,
        ], $storefrontIds];
    }

    /** $base, or $base-2, $base-3… - the first no other listing of the business has. */
    private function freeSlug(Workspace $workspace, string $base, ?int $exceptId): string
    {
        $base = Str::limit($base, 190, '');

        for ($slug = $base, $n = 2; $this->catalog->slugTaken($workspace, $slug, $exceptId); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }

    private function item(Workspace $workspace, int $itemId): Item
    {
        return $this->catalog->findItem($workspace, $itemId) ?? abort(404, 'There is no such item for sale.');
    }

    private function product(Workspace $workspace, int $itemId): CatalogProduct
    {
        return $this->catalog->forItem($this->item($workspace, $itemId)) ?? abort(404, 'This item is not in the catalog.');
    }

    /** Phase B3: the listing $item is a variant of, when that is another item's. */
    private function variantOf(Item $item): ?CatalogProduct
    {
        $listing = $item->catalogVariant?->product;

        return $listing !== null && $listing->item_id !== $item->id ? $listing : null;
    }

    private function today(Workspace $workspace): string
    {
        return $this->calendar->today($workspace)->toDateString();
    }

    /** 'online product "Ceramic Mug"', as the log names it. */
    private function record(CatalogProduct $product): string
    {
        return 'online product '.$this->quoted($product->title);
    }

    private function quoted(string $text): string
    {
        return '"'.Str::limit($text, 100).'"';
    }
}
