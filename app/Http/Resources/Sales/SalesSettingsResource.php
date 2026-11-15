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
            // Doc 16 Phase G1: whether confirmed orders count toward a customer's credit exposure.
            'credit_counts_orders' => $this->when(WorkspaceApps::requestHas('sales', Edition::Full), fn () => $this->credit_counts_orders),
            // Doc 16 Phase G2: the customer's link - the days a new one lasts, null for never, and whether they comment through it.
            'link_expiry_days'   => $this->when(WorkspaceApps::requestHas('sales', Edition::Full), fn () => $this->link_expiry_days),
            'link_comments'      => $this->when(WorkspaceApps::requestHas('sales', Edition::Full), fn () => $this->link_comments),
            // Doc 16 Phase H1: the rest of the full Sales app's settings; the default price list with its name, as its list may be out of reach.
            'new_sales_start_as'     => $this->when(WorkspaceApps::requestHas('sales', Edition::Full), fn () => $this->new_sales_start_as),
            'default_price_list'     => $this->when(WorkspaceApps::requestHas('sales', Edition::Full), fn () => $this->defaultPriceList ? ['id' => $this->defaultPriceList->id, 'name' => $this->defaultPriceList->name] : null),
            'deposit_percent'        => $this->when(WorkspaceApps::requestHas('sales', Edition::Full), fn () => $this->deposit_percent),
            'backorders'             => $this->when(WorkspaceApps::requestHas('sales', Edition::Full), fn () => $this->backorders),
            'default_shipping_terms' => $this->when(WorkspaceApps::requestHas('sales', Edition::Full), fn () => $this->default_shipping_terms),
            'salesperson_is_creator' => $this->when(WorkspaceApps::requestHas('sales', Edition::Full), fn () => $this->salesperson_is_creator),
            'notes'              => $this->notes,
            'terms'              => $this->terms,
        ];
    }
}
