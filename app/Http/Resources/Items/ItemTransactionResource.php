<?php

namespace App\Http\Resources\Items;

use App\Http\Resources\Concerns\HidesValues;
use App\Models\User\PurchaseLine;
use App\Services\Sales\SalesDocumentRules;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of an item's related transactions (Doc 04 section 16): the line that
 * holds it - quantity, price and amount as the line kept them, in its
 * document's currency - and which document that is, for or from whom, and
 * where it stands. `kind` is the document's address: /sales/invoices/12, or
 * "purchases" for /purchases/7. Expects document.contact - a purchase line's
 * purchase.contact - loaded. Its price and amount are empty for a user
 * without its app's Values.
 */
class ItemTransactionResource extends JsonResource
{
    use HidesValues;

    public function __construct($resource, private string $today)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $bought   = $this->resource instanceof PurchaseLine;
        $document = $bought ? $this->purchase : $this->document;
        $snapshot = $bought ? $document->supplier_snapshot : $document->customer_snapshot;

        return $this->hideValues($bought ? 'purchases' : 'sales', [
            'id'            => $this->id,
            'document_id'   => $document->id,
            'kind'          => $bought ? 'purchases' : $document->type->kind(),
            'number'        => $document->number,
            'revision'      => $bought ? 0 : $document->revision,
            'document_date' => $document->document_date,
            'contact'       => $snapshot['name'] ?? $document->contact?->name,
            'status'        => $bought ? $document->status->value : app(SalesDocumentRules::class)->status($document, $this->today),
            'quantity'      => $this->quantity,
            'unit'          => $this->unit,
            'unit_price'    => $this->unit_price,
            'amount'        => $this->amount,
            'currency'      => $document->currency,
        ], ['unit_price', 'amount']);
    }
}
