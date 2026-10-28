<?php

namespace App\Http\Resources\Documents;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * How one kind of document is numbered, with the number the next one would
 * get today. Wraps NumberingService's ['scheme' => ..., 'next_number' => ...].
 */
class DocumentNumberingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $scheme = $this->resource['scheme'];

        return [
            'type'            => $scheme->document_type->value,
            'label'           => $scheme->document_type->label(),
            'prefix'          => $scheme->prefix,
            'starting_number' => $scheme->starting_number,
            'number_length'   => $scheme->number_length,
            'period'          => $scheme->period->value,
            'next_number'     => $this->resource['next_number'],
        ];
    }
}
