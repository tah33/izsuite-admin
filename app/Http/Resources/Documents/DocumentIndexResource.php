<?php

namespace App\Http\Resources\Documents;

use App\Enums\ExpenseStatus;
use App\Enums\PurchaseStatus;
use App\Models\User\Expense;
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
 * credit note - source loaded.
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
        if ($this->resource instanceof Purchase) {
            return $this->purchase();
        }

        if ($this->resource instanceof Expense) {
            return $this->expense();
        }

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
            'actions'        => app(AllowedActions::class)->sales($this->type, $rules->actions($this->resource, $this->today)),
            'delivery'       => app(AllowedActions::class)->delivery($this->type, $rules->delivery($this->resource, $this->today)),
        ];
    }

    private function purchase(): array
    {
        $rules = app(PurchaseRules::class);

        return [
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
        ];
    }

    private function expense(): array
    {
        $rules = app(ExpenseRules::class);

        return [
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
        ];
    }
}
