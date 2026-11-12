<?php

namespace App\Http\Resources\Files;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a business may attach to its records (Doc 14 section 14.12), as the
 * admin set it: the types by extension and the largest file in bytes - for
 * the page's file picker and its words. Expects FileLimits::attachments().
 */
class FileLimitsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'attachments' => [
                'extensions' => $this->resource['extensions'],
                'max_bytes'  => $this->resource['max_bytes'],
            ],
        ];
    }
}
