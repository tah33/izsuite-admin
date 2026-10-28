<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A document's working secure link: its address, and how often and when it
 * was last opened. Wraps ['link' => DocumentLink, 'url' => string].
 */
class DocumentLinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $link = $this->resource['link'];

        return [
            'url'            => $this->resource['url'],
            'views'          => $link->views,
            'last_viewed_at' => $link->last_viewed_at?->toIso8601String(),
        ];
    }
}
