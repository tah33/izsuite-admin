<?php

namespace App\Http\Resources\Sales;

use App\Http\Resources\Concerns\HidesValues;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An earlier version of a quotation or proforma: its revision number, who
 * changed it and when - and, read on its own, the version whole (snapshot:
 * the header, lines and totals as they were), its amounts empty for a user
 * without Sales Values.
 */
class SalesDocumentRevisionResource extends JsonResource
{
    use HidesValues;

    public function toArray(Request $request): array
    {
        return [
            'revision'   => $this->revision,
            'changed_by' => $this->creator?->name,
            'changed_at' => $this->created_at?->toIso8601String(),
            'snapshot'   => $this->whenHas('snapshot', fn (array $snapshot) => [
                ...$this->hideValues('sales', $snapshot, ['discount_value', 'subtotal', 'discount_total', 'tax_total', 'grand_total']),
                'lines' => array_map(fn (array $line) => $this->hideValues('sales', $line, SalesDocumentLineResource::VALUES), $snapshot['lines'] ?? []),
            ]),
        ];
    }
}
