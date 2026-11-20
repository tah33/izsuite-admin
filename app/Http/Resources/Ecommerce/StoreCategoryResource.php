<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\CatalogCategory;
use App\Services\Files\FileStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A category's page on a storefront (Doc 19 Phase C2): its name and link, its
 * description, its picture, and its SEO title and description - else its name.
 *
 * @property CatalogCategory $resource
 */
class StoreCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $storage = app(FileStorage::class);

        return [
            'name'        => $this->category->name,
            'slug'        => $this->slug,
            'description' => $this->description,
            'image'       => $this->image ? ['url' => $storage->url($this->image), 'thumbnail_url' => $storage->url($this->image, true)] : null,
            'seo'         => ['title' => $this->seo_title ?? $this->category->name, 'description' => $this->seo_description],
        ];
    }
}
