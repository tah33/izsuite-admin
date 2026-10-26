<?php

namespace App\Http\Resources\Content;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** An image just uploaded for a landing page item: where it is served from. */
class ContentImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'url' => $this->resource['url'],
        ];
    }
}
