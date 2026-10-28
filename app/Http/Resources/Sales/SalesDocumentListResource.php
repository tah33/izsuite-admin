<?php

namespace App\Http\Resources\Sales;

use App\Enums\DocumentType;
use App\Services\Sales\SalesDocumentRules;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of a quotations, proforma invoices or invoices list (Doc 08 sections
 * 6, 9 and 10): what the list shows, no more. Its status is as the business's
 * $today makes it - expired, past an expiry date - and overdue says whether an
 * issued invoice is past its due date with something left to pay. Amounts go
 * out as strings to the cent. Expects contact and location loaded.
 */
class SalesDocumentListResource extends JsonResource
{
    public function __construct($resource, private string $today)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $rules = app(SalesDocumentRules::class);

        return [
            'id'             => $this->id,
            'number'         => $this->number,
            'revision'       => $this->revision,
            'status'         => $rules->status($this->resource, $this->today),
            'payment_status' => $this->payment_status,
            'overdue'        => $rules->overdue($this->resource, $this->today),
            'document_date'  => $this->document_date,
            'due_date'       => $this->due_date,
            'expiry_date'    => $this->expiry_date,
            'customer'       => ['id' => $this->contact_id, 'name' => $this->customer_snapshot['name'] ?? $this->contact?->name],
            'location'       => $this->location ? ['id' => $this->location->id, 'name' => $this->location->name] : null,
            'currency'       => $this->currency,
            'grand_total'    => $this->grand_total,
            'amount_paid'    => $this->amount_paid,
            'balance_due'    => $this->balance_due,
            'invoice'        => $this->when($this->type === DocumentType::CreditNote, fn () => ['id' => $this->source_id, 'number' => $this->source?->number]),
        ];
    }
}
