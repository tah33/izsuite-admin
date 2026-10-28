<?php

namespace App\Repositories\User;

use App\Enums\DocumentType;
use App\Enums\SalesDocumentStatus;
use App\Models\User\SalesDocument;
use App\Models\User\SalesDocumentLine;
use App\Models\User\SalesPayment;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * What Basic Reports and the Dashboard read of Sales Lite (Doc 07, Doc 02):
 * issued invoices and credit notes by their own dates, their lines, completed
 * payments and refunds by theirs, and invoices as they stood on a day. Never a
 * draft, cancelled or void document, nor a reversed payment (Doc 07 section
 * 19). Every query is the business's own.
 *
 * $filters narrow them: location_id, contact_id, currency - a document's own,
 * or a payment's account's - and document_currency for money moved against
 * documents in one currency.
 */
class SalesReportRepository
{
    private const FIGURES = [
        'id', 'type', 'number', 'status', 'document_date', 'due_date', 'contact_id', 'location_id', 'source_id', 'currency', 'exchange_rate',
        'price_tax_mode', 'subtotal', 'discount_total', 'tax_total', 'grand_total', 'net_total', 'base_grand_total', 'base_tax_total',
        'base_net_total', 'taxes', 'reason', 'amount_paid', 'withholding_amount', 'applied_amount', 'refunded_amount', 'balance_due',
        'payment_status', 'customer_snapshot',
    ];

    /** Issued invoices or credit notes dated from $from to $to, oldest first. */
    public function issued(Workspace $workspace, DocumentType $type, string $from, string $to, array $filters = []): Collection
    {
        return $this->documents($workspace, $type, $filters)
            ->whereBetween('document_date', [$from, $to])
            ->with(['contact:id,name', 'location:id,name', 'source:id,number'])
            ->orderBy('document_date')
            ->orderBy('id')
            ->get(self::FIGURES);
    }

    /** The lines of issued invoices or credit notes dated from $from to $to, with what their share of the totals needs. */
    public function issuedLines(Workspace $workspace, DocumentType $type, string $from, string $to, array $filters = []): Collection
    {
        return SalesDocumentLine::query()
            ->join('sales_documents', 'sales_documents.id', '=', 'sales_document_lines.sales_document_id')
            ->where('sales_documents.workspace_id', $workspace->id)
            ->where('sales_documents.type', $type->value)
            ->where('sales_documents.status', SalesDocumentStatus::Issued->value)
            ->whereBetween('sales_documents.document_date', [$from, $to])
            ->when($filters['location_id'] ?? null, fn (Builder $query, $id) => $query->where('sales_documents.location_id', $id))
            ->when($filters['contact_id'] ?? null, fn (Builder $query, $id) => $query->where('sales_documents.contact_id', $id))
            ->when($filters['currency'] ?? null, fn (Builder $query, string $currency) => $query->where('sales_documents.currency', $currency))
            ->get([
                'sales_document_lines.item_id', 'sales_document_lines.name', 'sales_document_lines.quantity', 'sales_document_lines.amount',
                'sales_document_lines.tax_amount', 'sales_document_lines.sales_document_id', 'sales_documents.subtotal',
                'sales_documents.discount_total', 'sales_documents.price_tax_mode', 'sales_documents.exchange_rate',
            ]);
    }

    /** Whether any issued document has a taxed line - zero-rated and exempt ones too: the Tax Summary's history. */
    public function hasTaxHistory(Workspace $workspace): bool
    {
        return SalesDocumentLine::query()
            ->whereNotNull('tax_name')
            ->whereHas('document', fn (Builder $query) => $query->where('workspace_id', $workspace->id)->where('status', SalesDocumentStatus::Issued->value))
            ->exists();
    }

    /** Completed payments and refunds dated from $from to $to, oldest first: money that came in or went back. */
    public function moneyMoved(Workspace $workspace, string $from, string $to, array $filters = []): Collection
    {
        return SalesPayment::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', SalesPayment::COMPLETED)
            ->whereBetween('payment_date', [$from, $to])
            ->when($filters['location_id'] ?? null, fn (Builder $query, $id) => $query->where('location_id', $id))
            ->when($filters['contact_id'] ?? null, fn (Builder $query, $id) => $query->where('contact_id', $id))
            ->when($filters['currency'] ?? null, fn (Builder $query, string $currency) => $query->whereHas('account', fn (Builder $query) => $query->where('currency', $currency)))
            ->when($filters['document_currency'] ?? null, fn (Builder $query, string $currency) => $query->whereHas('document', fn (Builder $query) => $query->where('currency', $currency)))
            ->with(['document:id,type,number,currency,exchange_rate,source_id', 'contact:id,name', 'account:id,name,currency', 'location:id,name'])
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * Issued invoices dated on or before $asOf, each with what had settled it
     * by then (paid_by, withheld_by, credited_by, refunded_by) - the parts of
     * InvoiceSettlement's balance, as of that day.
     */
    public function invoicesAsOf(Workspace $workspace, string $asOf, array $filters = []): Collection
    {
        $payments = SalesPayment::query()
            ->selectRaw('sales_document_id, SUM(applied_amount) as paid_sum, SUM(withholding_amount) as withheld_sum')
            ->where('workspace_id', $workspace->id)
            ->where('kind', SalesPayment::PAYMENT)
            ->where('status', SalesPayment::COMPLETED)
            ->where('payment_date', '<=', $asOf)
            ->groupBy('sales_document_id');

        $credits = SalesDocument::query()
            ->selectRaw('source_id, SUM(grand_total) as credited_sum')
            ->where('workspace_id', $workspace->id)
            ->where('type', DocumentType::CreditNote->value)
            ->where('status', SalesDocumentStatus::Issued->value)
            ->where('document_date', '<=', $asOf)
            ->groupBy('source_id');

        $refunds = SalesPayment::query()
            ->join('sales_documents as notes', 'notes.id', '=', 'sales_payments.sales_document_id')
            ->selectRaw('notes.source_id, SUM(sales_payments.applied_amount) as refunded_sum')
            ->where('sales_payments.workspace_id', $workspace->id)
            ->where('sales_payments.kind', SalesPayment::REFUND)
            ->where('sales_payments.status', SalesPayment::COMPLETED)
            ->where('sales_payments.payment_date', '<=', $asOf)
            ->groupBy('notes.source_id');

        return $this->documents($workspace, DocumentType::Invoice, $filters, 'sales_documents.')
            ->select(array_map(fn (string $column) => "sales_documents.{$column}", self::FIGURES))
            ->selectRaw('COALESCE(p.paid_sum, 0) as paid_by, COALESCE(p.withheld_sum, 0) as withheld_by, COALESCE(c.credited_sum, 0) as credited_by, COALESCE(r.refunded_sum, 0) as refunded_by')
            ->leftJoinSub($payments, 'p', 'p.sales_document_id', '=', 'sales_documents.id')
            ->leftJoinSub($credits, 'c', 'c.source_id', '=', 'sales_documents.id')
            ->leftJoinSub($refunds, 'r', 'r.source_id', '=', 'sales_documents.id')
            ->where('sales_documents.document_date', '<=', $asOf)
            ->with(['contact:id,name', 'location:id,name'])
            ->orderBy('sales_documents.due_date')
            ->orderBy('sales_documents.id')
            ->get();
    }

    /** The newest documents, any status - the Dashboard's Recent Transactions. */
    public function latestDocuments(Workspace $workspace, int $limit, ?int $locationId): Collection
    {
        return SalesDocument::query()
            ->where('workspace_id', $workspace->id)
            ->when($locationId, fn (Builder $query, int $id) => $query->where('location_id', $id))
            ->with('contact:id,name')
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'type', 'number', 'revision', 'status', 'document_date', 'expiry_date', 'contact_id', 'currency', 'grand_total', 'customer_snapshot', 'created_at']);
    }

    /** The newest payments and refunds, reversed ones too. */
    public function latestMoney(Workspace $workspace, int $limit, ?int $locationId): Collection
    {
        return SalesPayment::query()
            ->where('workspace_id', $workspace->id)
            ->when($locationId, fn (Builder $query, int $id) => $query->where('location_id', $id))
            ->with(['contact:id,name', 'account:id,currency'])
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'kind', 'number', 'status', 'payment_date', 'contact_id', 'payment_account_id', 'amount', 'created_at']);
    }

    /** Issued documents of $type with $filters - columns prefixed with $table where the query joins. */
    private function documents(Workspace $workspace, DocumentType $type, array $filters, string $table = ''): Builder
    {
        return SalesDocument::query()
            ->where("{$table}workspace_id", $workspace->id)
            ->where("{$table}type", $type->value)
            ->where("{$table}status", SalesDocumentStatus::Issued->value)
            ->when($filters['location_id'] ?? null, fn (Builder $query, $id) => $query->where("{$table}location_id", $id))
            ->when($filters['contact_id'] ?? null, fn (Builder $query, $id) => $query->where("{$table}contact_id", $id))
            ->when($filters['currency'] ?? null, fn (Builder $query, string $currency) => $query->where("{$table}currency", $currency));
    }
}
