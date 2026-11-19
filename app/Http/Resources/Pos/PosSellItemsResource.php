<?php

namespace App\Http\Resources\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** The items a POS search found (Doc 18 Phase C1), and the one whose barcode or SKU it was exactly: what a scanner typed. */
class PosSellItemsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'items' => PosSellItemResource::collection($this->resource['items']),
            'exact' => $this->resource['exact'] ? new PosSellItemResource($this->resource['exact']) : null,
        ];
    }
}
