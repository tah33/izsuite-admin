<?php

namespace App\Http\Resources\Ecommerce;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A digital product's file, ready to download (Doc 19 Phase G3b): the link
 * to the file itself, which works for a few minutes, and how many more times
 * it may be downloaded - null for no limit.
 *
 * @property array{url: string, left: ?int} $resource
 */
class StoreDownloadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'url'  => $this->resource['url'],
            'left' => $this->resource['left'],
        ];
    }
}
