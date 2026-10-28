<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An earlier version of a quotation or proforma: its revision number, who
 * changed it and when - and, read on its own, the version whole (snapshot:
 * the header, lines and totals as they were).
 */
class SalesDocumentRevisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'revision'   => $this->revision,
            'changed_by' => $this->creator?->name,
            'changed_at' => $this->created_at?->toIso8601String(),
            'snapshot'   => $this->whenHas('snapshot'),
        ];
    }
}
