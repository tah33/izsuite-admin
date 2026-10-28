<?php

namespace App\Http\Resources\Documents;

use App\Services\Sales\SalesDocumentRules;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the Documents page (Doc 06 section 4): what it is, where it came
 * from and where it stands - status and payment status apart (section 7) -
 * its total in its own currency, and its balance where it has one. `kind`
 * is the address of its source record: /sales/invoices/12. Expects contact
 * and location loaded.
 */
class DocumentIndexResource extends JsonResource
{
    /** @param  array<string, string>  $sources  the app each family comes from */
    public function __construct($resource, private string $today, private array $sources)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $rules = app(SalesDocumentRules::class);

        return [
            'id'             => $this->id,
            'kind'           => $this->type->kind(),
            'number'         => $this->number,
            'revision'       => $this->revision,
            'document_date'  => $this->document_date,
            'contact'        => $this->customer_snapshot['name'] ?? $this->contact?->name,
            'source'         => $this->sources[$this->type->family()] ?? null,
            'status'         => $rules->status($this->resource, $this->today),
            'payment_status' => $this->payment_status,
            'overdue'        => $rules->overdue($this->resource, $this->today),
            'grand_total'    => $this->grand_total,
            'currency'       => $this->currency,
            'balance_due'    => $rules->holdsBalance($this->resource) ? $this->balance_due : null,
            'location'       => $this->location?->name,
        ];
    }
}
