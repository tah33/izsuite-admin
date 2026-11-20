<?php

namespace App\Http\Resources\Ecommerce;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** What a collection's form chooses from (Doc 19 Phase B2): the catalog's products, and the brands while the business keeps them. */
class CatalogCollectionOptionsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'products' => $this->resource['products']->map(fn ($product) => ['id' => $product->id, 'title' => $product->title, 'status' => $product->status])->values(),
            'brands'   => $this->resource['brands']->map(fn ($brand) => ['id' => $brand->id, 'name' => $brand->name])->values(),
        ];
    }
}
