<?php

namespace App\Http\Resources\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a document's email form starts with: the customer's address, and the
 * business's subject and message for it with the placeholders known so far
 * filled in. Wraps DocumentEmailService's draft.
 */
class EmailDraftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'to'       => $this->resource['to'],
            'subject'  => $this->resource['subject'],
            'body'     => $this->resource['body'],
            'reminder' => $this->resource['reminder'],
        ];
    }
}
