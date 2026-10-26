<?php

namespace App\Http\Resources\Files;

use App\Services\Files\FileStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One file of a record, as its card lists it: the name it was uploaded with,
 * its type and size in bytes, who added it and when. A public file has its
 * address; a private one is fetched through GET /files/{id}/link instead.
 */
class WorkspaceFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $storage = app(FileStorage::class);

        return [
            'id'            => $this->id,
            'name'          => $this->name,
            'mime_type'     => $this->mime_type,
            'size'          => $this->size,
            'url'           => $storage->url($this->resource),
            'thumbnail_url' => $this->thumbnail_path ? $storage->url($this->resource, true) : null,
            'uploaded_by'   => $this->uploader ? ['id' => $this->uploader->id, 'name' => $this->uploader->name] : null,
            'created_at'    => $this->created_at?->toIso8601String(),
        ];
    }
}
