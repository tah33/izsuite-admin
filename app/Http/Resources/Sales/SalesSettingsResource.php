<?php

namespace App\Http\Resources\Sales;

use App\Enums\Edition;
use App\Services\User\Apps\WorkspaceApps;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Settings > Sales: what new sales documents start with. */
class SalesSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'payment_terms'      => $this->payment_terms,
            'quotation_validity' => $this->quotation_validity,
            'proforma_validity'  => $this->proforma_validity,
            'line_discounts'     => $this->line_discounts,
            'document_discounts' => $this->document_discounts,
            // The full Sales app's (Doc 16 Phase B2): how its sales orders are invoiced.
            'invoice_policy'     => $this->when(WorkspaceApps::requestHas('sales', Edition::Full), fn () => $this->invoice_policy),
            // Doc 16 Phase D1: whether advances are taken, overpayments included.
            'customer_advances'  => $this->when(WorkspaceApps::requestHas('sales', Edition::Full), fn () => $this->customer_advances),
            'notes'              => $this->notes,
            'terms'              => $this->terms,
        ];
    }
}
