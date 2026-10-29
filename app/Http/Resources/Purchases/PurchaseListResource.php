<?php

namespace App\Http\Resources\Purchases;

use App\Services\Purchases\PurchaseRules;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the purchases list (Doc 09 section 8): what the list shows, no
 * more. Overdue says whether a recorded purchase is past its due date with
 * something left to pay; `actions` are what it allows now, for its row's
 * Actions, done on its own page. Amounts go out as strings to the cent.
 * Expects contact and location loaded.
 */
class PurchaseListResource extends JsonResource
{
    public function __construct($resource, private string $today)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $rules = app(PurchaseRules::class);

        return [
            'id'                      => $this->id,
            'number'                  => $this->number,
            'status'                  => $this->status->value,
            'payment_status'          => $this->payment_status,
            'overdue'                 => $rules->overdue($this->resource, $this->today),
            'document_date'           => $this->document_date,
            'due_date'                => $this->due_date,
            'supplier'                => $this->supplier_snapshot['name'] ?? $this->contact?->name,
            'supplier_invoice_number' => $this->supplier_invoice_number,
            'location'                => $this->location?->name,
            'currency'                => $this->currency,
            'grand_total'             => $this->grand_total,
            'balance_due'             => $this->balance_due,
            'actions'                 => $rules->actions($this->resource),
        ];
    }
}
