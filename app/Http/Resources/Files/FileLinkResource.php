<?php

namespace App\Http\Resources\Files;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A link that downloads a file, and the name to save it under. */
class FileLinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'url'  => $this->resource['url'],
            'name' => $this->resource['name'],
        ];
    }
}
