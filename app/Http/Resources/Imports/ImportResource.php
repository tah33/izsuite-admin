<?php

namespace App\Http\Resources\Imports;

use App\Models\User\Import;
use App\Services\Api\Transfer\ImportService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An import as its screens need it: where it has got to, the file's columns
 * and the fields they can fill (with the matching so far), the first rows of
 * the file while it is being matched, and the check's and the result's
 * counts.
 */
class ImportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $imports = app(ImportService::class);
        $fields  = $imports->transfer($this->kind)->fields($this->workspace);

        return [
            'id'         => $this->id,
            'kind'       => $this->kind,
            'status'     => $this->status,
            'file_name'  => $this->file_name,
            'row_count'  => $this->row_count,
            'processed'  => $this->processed,
            'headers'    => $this->headers,
            // While the columns can still be matched - again, after a check.
            'samples'    => in_array($this->status, [Import::MAPPING, Import::READY], true) ? $imports->samples($this->resource) : [],
            'fields'     => array_map(fn (string $key, array $field) => ['key' => $key, ...$field], array_keys($fields), $fields),
            'mapping'    => (object) ($this->mapping ?? []),
            'summary'    => $this->summary,
            'result'     => $this->result,
            'error'      => $this->error,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
