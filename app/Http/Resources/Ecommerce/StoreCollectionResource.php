<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\CatalogCollection;
use App\Services\Files\FileStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A collection on a storefront (Doc 19 Phase C2): its name, link, kind and
 * picture - and, on its own page ($whole), its description and its SEO title
 * and description, else its name.
 *
 * @property CatalogCollection $resource
 */
class StoreCollectionResource extends JsonResource
{
    public function __construct(CatalogCollection $collection, private bool $whole = false)
    {
        parent::__construct($collection);
    }

    public function toArray(Request $request): array
    {
        $storage = app(FileStorage::class);
        $image   = $this->image ? ['url' => $storage->url($this->image), 'thumbnail_url' => $storage->url($this->image, true)] : null;

        return [
            'name'  => $this->name,
            'slug'  => $this->slug,
            'kind'  => $this->kind,
            'image' => $image,
            ...($this->whole ? [
                'description' => $this->description,
                'seo'         => ['title' => $this->seo_title ?? $this->name, 'description' => $this->seo_description],
            ] : []),
        ];
    }
}
