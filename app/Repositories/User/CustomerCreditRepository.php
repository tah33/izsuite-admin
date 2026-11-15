<?php

namespace App\Repositories\User;

use App\Enums\DocumentType;
use App\Enums\SalesDocumentStatus;
use App\Models\User\CustomerAdvance;
use App\Models\User\SalesDocument;
use App\Models\User\Workspace;
use Illuminate\Support\Collection;

/**
 * What a customer's credit exposure is made of, beside the invoices they
 * still owe on (Doc 16 section 16.21 - Phase G1): the sales orders confirmed
 * and not invoiced yet, and the credit they hold but have not applied.
 */
class CustomerCreditRepository
{
    /**
     * A customer's confirmed and held sales orders, each with its base total
     * and what its issued invoices billed of it (`invoiced_base`), both in
     * the base currency.
     */
    public function openOrdersFor(Workspace $workspace, int $contactId): Collection
    {
        // The invoices are aliased, so `sales_documents` in the subquery is the order outside it.
        $invoiced = SalesDocument::query()
            ->from('sales_documents as invoices')
            ->selectRaw('COALESCE(SUM(invoices.base_grand_total), 0)')
            ->whereColumn('invoices.source_id', 'sales_documents.id')
            ->where('invoices.type', DocumentType::Invoice->value)
            ->where('invoices.status', SalesDocumentStatus::Issued->value);

        return SalesDocument::query()
            ->where('workspace_id', $workspace->id)
            ->where('type', DocumentType::SalesOrder->value)
            ->where('contact_id', $contactId)
            ->whereIn('status', [SalesDocumentStatus::Confirmed->value, SalesDocumentStatus::OnHold->value])
            ->select(['id', 'base_grand_total'])
            ->selectSub($invoiced, 'invoiced_base')
            ->get();
    }

    /** A customer's advances with something left to apply: what is left, of how much, worth how much in the base currency. */
    public function openAdvancesFor(Workspace $workspace, int $contactId): Collection
    {
        return CustomerAdvance::query()
            ->where('workspace_id', $workspace->id)
            ->where('contact_id', $contactId)
            ->where('status', CustomerAdvance::COMPLETED)
            ->where('balance', '>', 0)
            ->get(['id', 'amount', 'base_amount', 'balance']);
    }

    /** A customer's issued credit notes with credit left, and each one's rate to the base currency. */
    public function openCreditNotesFor(Workspace $workspace, int $contactId): Collection
    {
        return SalesDocument::query()
            ->where('workspace_id', $workspace->id)
            ->where('type', DocumentType::CreditNote->value)
            ->where('contact_id', $contactId)
            ->where('status', SalesDocumentStatus::Issued->value)
            ->where('balance_due', '>', 0)
            ->get(['id', 'balance_due', 'exchange_rate']);
    }
}
