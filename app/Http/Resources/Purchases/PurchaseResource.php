<?php

namespace App\Http\Resources\Purchases;

use App\Services\Shared\EditConflicts;
use App\Http\Resources\Concerns\HidesValues;
use App\Services\Api\Sales\SalesSnapshots;
use App\Services\Purchases\PurchaseRules;
use App\Services\Shared\AllowedActions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * A purchase whole - for its page and its edit form (Doc 09): the header with
 * the supplier's own invoice number and date, the lines, the totals and tax
 * breakdown, what it still owes and the payments made to it, and the actions
 * its status allows now (see PurchaseRules). The supplier - their name,
 * billing address and tax number - is as they were when it was recorded; a
 * draft shows them as they are. Expects lines, contact and location loaded,
 * and payments with their accounts for the payment history.
 */
class PurchaseResource extends JsonResource
{
    use HidesValues;

    /** What a user without Purchase Values does not see (Doc 12 section 14). */
    private const VALUES = ['discount_value', 'subtotal', 'discount_total', 'net_total', 'grand_total', 'base_grand_total', 'taxes' => [], 'amount_paid', 'withholding_amount', 'balance_due'];

    public function __construct($resource, private string $today)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $rules    = app(PurchaseRules::class);
        $supplier = $this->supplier_snapshot ?? ($this->contact ? app(SalesSnapshots::class)->customer($this->contact) : null);

        return $this->hideValues('purchases', [
            'id'                      => $this->id,
            // When it was last changed: sent back as If-Match when it is saved (Doc 14 section 22).
            'version' => EditConflicts::version($this->resource),
            'number'                  => $this->number,
            'status'                  => $this->status->value,
            'payment_status'          => $this->payment_status,
            'overdue'                 => $rules->overdue($this->resource, $this->today),
            'actions'                 => app(AllowedActions::class)->record('purchases.purchases', $rules->actions($this->resource)),
            'contact_id'              => $this->contact_id,
            'supplier'                => $supplier ? $this->hideWithout('taxes.view', Arr::only($supplier, ['name', 'billing_address', 'tax_number']), ['tax_number']) : null,
            'location_id'             => $this->location_id,
            'location'                => $this->location?->name,
            'document_date'           => $this->document_date,
            'due_date'                => $this->due_date,
            'payment_terms'           => $this->payment_terms,
            'currency'                => $this->currency,
            'exchange_rate'           => $this->exchange_rate,
            'price_tax_mode'          => $this->price_tax_mode,
            'tax_rounding'            => $this->tax_rounding,
            'supplier_invoice_number' => $this->supplier_invoice_number,
            'supplier_invoice_date'   => $this->supplier_invoice_date,
            'reference'               => $this->reference,
            'discount_type'           => $this->discount_type,
            'discount_value'          => $this->discount_value,
            'lines'                   => PurchaseLineResource::collection($this->lines),
            'subtotal'                => $this->subtotal,
            'discount_total'          => $this->discount_total,
            'net_total'               => $this->net_total,
            'grand_total'             => $this->grand_total,
            'base_grand_total'        => $this->base_grand_total,
            'taxes'                   => $this->taxes ?? [],
            'amount_paid'             => $this->amount_paid,
            'withholding_amount'      => $this->withholding_amount,
            'balance_due'             => $this->balance_due,
            'notes'                   => $this->notes,
            'status_reason'           => $this->status_reason,
            'duplicate_reason'        => $this->duplicate_reason,
            'payments'                => SupplierPaymentListResource::collection($this->whenLoaded('payments')),
        ], self::VALUES);
    }
}
