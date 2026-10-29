<?php

namespace App\Http\Resources\Items;

use App\Services\Sales\SalesDocumentRules;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of an item's related transactions (Doc 04 section 16): the line that
 * holds it - quantity, price and amount as the line kept them, in its
 * document's currency - and which document that is, for whom, and where it
 * stands. `kind` is the document's address: /sales/invoices/12. Expects
 * document.contact loaded.
 */
class ItemTransactionResource extends JsonResource
{
    public function __construct($resource, private string $today)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $document = $this->document;

        return [
            'id'            => $this->id,
            'document_id'   => $document->id,
            'kind'          => $document->type->kind(),
            'number'        => $document->number,
            'revision'      => $document->revision,
            'document_date' => $document->document_date,
            'contact'       => $document->customer_snapshot['name'] ?? $document->contact?->name,
            'status'        => app(SalesDocumentRules::class)->status($document, $this->today),
            'quantity'      => $this->quantity,
            'unit'          => $this->unit,
            'unit_price'    => $this->unit_price,
            'amount'        => $this->amount,
            'currency'      => $document->currency,
        ];
    }
}
