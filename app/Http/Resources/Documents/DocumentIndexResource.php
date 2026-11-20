<?php

namespace App\Http\Resources\Documents;

use App\Enums\ExpenseStatus;
use App\Enums\PurchaseStatus;
use App\Http\Resources\Concerns\HidesValues;
use App\Models\User\Expense;
use App\Models\User\PosReturn;
use App\Models\User\Purchase;
use App\Services\Expenses\ExpenseRules;
use App\Services\Purchases\PurchaseRules;
use App\Services\Sales\SalesDocumentRules;
use App\Services\Shared\AllowedActions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the Documents page (Doc 06 section 4): what it is, where it came
 * from and where it stands - status and payment status apart (section 7) -
 * its total in its own currency, and its balance where it has one. `kind`
 * is the address of its source record: /sales/invoices/12 - or
 * "purchases", /purchases/7, or "expenses", /expenses/3. `actions` and
 * `delivery` are what its source record allows now (section 9) - the page
 * opens that record to do them. A purchase or an expense is sent nowhere;
 * an expense's contact is its payee. Expects contact, location and - for a
 * credit note - source loaded. Its total and balance are empty for a user
 * without its app's Values.
 */
class DocumentIndexResource extends JsonResource
{
    use HidesValues;

    /** What a user without the app's Values does not see (Doc 12 section 14). */
    private const VALUES = ['grand_total', 'balance_due'];

    /** @param  array<string, string>  $sources  the app each family comes from */
    public function __construct($resource, private string $today, private array $sources)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        if ($this->resource instanceof Purchase) {
            return $this->purchase();
        }

        if ($this->resource instanceof Expense) {
            return $this->expense();
        }

        if ($this->resource instanceof PosReturn) {
            return $this->refundReceipt();
        }

        $rules = app(SalesDocumentRules::class);

        return $this->hideValues('sales', [
            'id'             => $this->id,
            'kind'           => $this->type->kind(),
            'number'         => $this->number,
            'revision'       => $this->revision,
            'document_date'  => $this->document_date,
            'contact'        => $this->customer_snapshot['name'] ?? $this->contact?->name,
            'source'         => $this->pos() ? ($this->sources['pos'] ?? 'Point of Sale') : ($this->sources[$this->type->family()] ?? null),
            // Doc 18 Phase G3: what it is at POS, and its POS order, register and session.
            'pos'            => $this->pos(),
            'pos_reference'  => $this->posReference(),
            'status'         => $rules->status($this->resource, $this->today),
            'payment_status' => $this->payment_status,
            'overdue'        => $rules->overdue($this->resource, $this->today),
            'grand_total'    => $this->grand_total,
            'currency'       => $this->currency,
            'balance_due'    => $rules->holdsBalance($this->resource) ? $this->balance_due : null,
            'location'       => $this->location?->name,
            'actions'        => app(AllowedActions::class)->sales($this->type, $rules->actions($this->resource, $this->today)),
            'delivery'       => app(AllowedActions::class)->delivery($this->type, $rules->delivery($this->resource, $this->today)),
        ], self::VALUES);
    }

    /** What a sales document is at POS (Doc 18 Phase G3) - its sales receipt, its formal invoice, a return's credit note - or null. */
    private function pos(): ?string
    {
        return match (true) {
            $this->posOrder !== null  => $this->posOrder->invoiced_at ? 'invoice' : 'sales_receipt',
            $this->posReturn !== null => 'credit_note',
            default                  => null,
        };
    }

    /** Its POS order, register and session: "R1-00001 · Front Counter (R1) · S-00001" - a return's naming itself first. */
    private function posReference(): ?string
    {
        $pos = $this->posOrder ?? $this->posReturn;

        if ($pos === null) {
            return null;
        }

        return implode(' · ', array_filter([
            $pos === $this->posReturn ? $pos->number : null,
            $pos === $this->posReturn ? $pos->order?->number : ($this->posOrder->invoiced_at ? $pos->number : null),
            $pos->register ? "{$pos->register->name} ({$pos->register->code})" : null,
            $pos->session?->number,
        ]));
    }

    /** A POS return as its refund receipt (Doc 18 Phase G3): it opens on the Returns and Refunds page; it has no actions of its own. */
    private function refundReceipt(): array
    {
        return $this->hideValues('sales', [
            'id'             => $this->id,
            'kind'           => 'pos-refund-receipts',
            'number'         => $this->number,
            'revision'       => 0,
            'document_date'  => $this->creditNote?->document_date,
            'contact'        => $this->contact?->name,
            'source'         => $this->sources['pos'] ?? 'Point of Sale',
            'pos'            => 'refund_receipt',
            'pos_reference'  => implode(' · ', array_filter([$this->order?->number, $this->register ? "{$this->register->name} ({$this->register->code})" : null, $this->session?->number])),
            'status'         => $this->refund_status,
            'payment_status' => null,
            'overdue'        => false,
            'grand_total'    => (string) $this->total,
            'currency'       => $this->currency,
            'balance_due'    => null,
            'location'       => $this->location?->name,
            'actions'        => [],
            'delivery'       => [],
        ], self::VALUES);
    }

    private function purchase(): array
    {
        $rules = app(PurchaseRules::class);

        return $this->hideValues('purchases', [
            'id'             => $this->id,
            'kind'           => 'purchases',
            'number'         => $this->number,
            'revision'       => 0,
            'document_date'  => $this->document_date,
            'contact'        => $this->supplier_snapshot['name'] ?? $this->contact?->name,
            'source'         => $this->sources['purchases'] ?? null,
            'status'         => $this->status->value,
            'payment_status' => $this->payment_status,
            'overdue'        => $rules->overdue($this->resource, $this->today),
            'grand_total'    => $this->grand_total,
            'currency'       => $this->currency,
            'balance_due'    => $this->status === PurchaseStatus::Recorded ? $this->balance_due : null,
            'location'       => $this->location?->name,
            'actions'        => app(AllowedActions::class)->record('purchases.purchases', $rules->actions($this->resource)),
            'delivery'       => [],
        ], self::VALUES);
    }

    private function expense(): array
    {
        $rules = app(ExpenseRules::class);

        return $this->hideValues('expenses', [
            'id'             => $this->id,
            'kind'           => 'expenses',
            'number'         => $this->number,
            'revision'       => 0,
            'document_date'  => $this->document_date,
            'contact'        => $this->resource->payeeName(),
            'source'         => $this->sources['expenses'] ?? null,
            'status'         => $this->status->value,
            'payment_status' => $this->payment_status,
            'overdue'        => $rules->overdue($this->resource, $this->today),
            'grand_total'    => $this->grand_total,
            'currency'       => $this->currency,
            'balance_due'    => $this->status === ExpenseStatus::Recorded ? $this->balance_due : null,
            'location'       => $this->location?->name,
            'actions'        => app(AllowedActions::class)->record('expenses.expenses', $rules->actions($this->resource)),
            'delivery'       => [],
        ], self::VALUES);
    }
}
