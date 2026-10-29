<?php

namespace App\Repositories\User;

use App\Models\User\Item;
use App\Models\User\SalesDocumentLine;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * An item's related transactions (Doc 04 sections 16 and 17): the document
 * lines that hold it, across the business's apps - so far Sales Lite's
 * quotations, proformas, invoices and credit notes, any status; purchases
 * join with their app. Newest document first, a document's lines in order.
 */
class ItemTransactionRepository
{
    public function paginateFor(Workspace $workspace, Item $item, int $perPage): LengthAwarePaginator
    {
        return SalesDocumentLine::query()
            ->select('sales_document_lines.*')
            ->join('sales_documents', 'sales_documents.id', '=', 'sales_document_lines.sales_document_id')
            ->where('sales_documents.workspace_id', $workspace->id)
            ->where('sales_document_lines.item_id', $item->id)
            ->with('document.contact:id,name')
            ->orderByDesc('sales_documents.document_date')
            ->orderByDesc('sales_documents.id')
            ->orderBy('sales_document_lines.position')
            ->paginate($perPage);
    }
}
