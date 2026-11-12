<?php

namespace App\Http\Resources\Expenses;

use App\Services\Shared\EditConflicts;
use App\Http\Resources\Concerns\HidesValues;
use App\Services\Api\Sales\SalesSnapshots;
use App\Services\Expenses\ExpenseRules;
use App\Services\Shared\AllowedActions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * An expense whole - for its page and its edit form (Doc 10): the header with
 * the receipt number, the lines, the totals and tax breakdown, what it still
 * owes and the payments made to it, and the actions its status allows now
 * (see ExpenseRules). net_total is its amount before tax, which tax
 * withheld from a payment is worked out on. The payee
 * - a contact's name, billing address and tax number, or the name typed in -
 * is as they were when it was recorded; a draft shows them as they are.
 * payee_name is the name typed in, for the edit form. Expects lines, contact
 * and location loaded, and payments with their accounts for the payment
 * history.
 */
class ExpenseResource extends JsonResource
{
    use HidesValues;

    /** What a user without Expense Values does not see (Doc 12 section 14). */
    private const VALUES = ['subtotal', 'net_total', 'grand_total', 'base_grand_total', 'taxes' => [], 'amount_paid', 'withholding_amount', 'balance_due'];

    public function __construct($resource, private string $today)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $rules = app(ExpenseRules::class);
        $payee = $this->payee_snapshot
            ?? ($this->contact ? app(SalesSnapshots::class)->customer($this->contact) : ($this->payee_name ? ['name' => $this->payee_name] : null));

        return $this->hideValues('expenses', [
            'id'                 => $this->id,
            // When it was last changed: sent back as If-Match when it is saved (Doc 14 section 22).
            'version' => EditConflicts::version($this->resource),
            'number'             => $this->number,
            'status'             => $this->status->value,

            // The app that made it, and who (Doc 14 section 14.5), where it was read with them
            'made_in' => $this->whenLoaded('sourceApp', fn () => $this->sourceApp?->name),
            'made_by' => $this->whenLoaded('creator', fn () => $this->creator?->name),

            // Where its exchange rate came from (Doc 14 section 14.9): the business's own rates of that date, or entered on it
            'exchange_rate_date'   => $this->exchange_rate_date,
            'exchange_rate_source' => $this->exchange_rate_source,

            'payment_status'     => $this->payment_status,
            'overdue'            => $rules->overdue($this->resource, $this->today),
            'actions'            => app(AllowedActions::class)->record('expenses.expenses', $rules->actions($this->resource)),
            'contact_id'         => $this->contact_id,
            'payee_name'         => $this->payee_name,
            'payee'              => $payee ? $this->hideWithout('taxes.view', Arr::only($payee, ['name', 'billing_address', 'tax_number']), ['tax_number']) : null,
            'location_id'        => $this->location_id,
            'location'           => $this->location?->name,
            'document_date'      => $this->document_date,
            'due_date'           => $this->due_date,
            'payment_terms'      => $this->payment_terms,
            'currency'           => $this->currency,
            'exchange_rate'      => $this->exchange_rate,
            'price_tax_mode'     => $this->price_tax_mode,
            'tax_rounding'       => $this->tax_rounding,
            'receipt_number'     => $this->receipt_number,
            'reference'          => $this->reference,
            'lines'              => ExpenseLineResource::collection($this->lines),
            'subtotal'           => $this->subtotal,
            'net_total'          => $this->net_total,
            'grand_total'        => $this->grand_total,
            'base_grand_total'   => $this->base_grand_total,
            'taxes'              => $this->taxes ?? [],
            'amount_paid'        => $this->amount_paid,
            'withholding_amount' => $this->withholding_amount,
            'balance_due'        => $this->balance_due,
            'notes'              => $this->notes,
            'internal_notes'     => $this->internal_notes,
            'status_reason'      => $this->status_reason,
            'duplicate_reason'   => $this->duplicate_reason,
            'payments'           => ExpensePaymentListResource::collection($this->whenLoaded('payments')),
        ], self::VALUES);
    }
}
