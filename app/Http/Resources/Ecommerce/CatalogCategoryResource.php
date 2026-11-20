<?php

namespace App\Http\Resources\Ecommerce;

use App\Services\Files\FileStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An item category as the Catalog page's Categories show it (Doc 19 Phase
 * B2): its name, how many of its items are in the catalog, and its online
 * side - null until it is given one.
 */
class CatalogCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $online = $this->catalogCategory;

        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'listed_count' => (int) $this->listed_count,
            'online'       => $online ? [
                'slug'            => $online->slug,
                'description'     => $online->description,
                'seo_title'       => $online->seo_title,
                'seo_description' => $online->seo_description,
                'in_menu'         => $online->in_menu,
                'position'        => $online->position,
                'image_url'       => $online->image ? app(FileStorage::class)->url($online->image, true) : null,
            ] : null,
        ];
    }
}
