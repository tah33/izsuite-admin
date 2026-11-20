<?php

namespace App\Http\Resources\Ecommerce;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a storefront's sitemap lists (Doc 19 Phase C6a): whether search
 * engines may list it at all, and each of its pages' paths - under the
 * store's address - with the day it last changed, where it has one.
 */
class StoreSitemapResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'index' => $this->resource['index'],
            'urls'  => array_map(fn (array $url) => ['path' => $url['path'], 'updated_at' => $url['updated_at']], $this->resource['urls']),
        ];
    }
}
