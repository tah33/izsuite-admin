<?php

namespace App\Repositories\User;

use App\Enums\DocumentType;
use App\Models\User\Item;
use App\Models\User\PurchaseLine;
use App\Models\User\SalesDocumentLine;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * An item's related transactions (Doc 04 sections 16 and 17): the document
 * lines that hold it, across the business's apps - Sales Lite's quotations,
 * proformas, invoices and credit notes, and Purchases Lite's purchases, any
 * status; expenses join with their app. Newest document first, a document's
 * lines in order.
 *
 * Each app's lines stay in their own table: the list is the union of one
 * query per app, paged as one, and each page's rows are then read as the
 * lines they are - a SalesDocumentLine or a PurchaseLine - with their
 * document and its contact.
 */
class ItemTransactionRepository
{
    /**
     * @param  list<DocumentType>  $types  the kinds of document whose lines are listed - sales kinds, Purchase: those the user may view
     * @param  list<int>|null  $locationIds  the locations the user may use - every one for null
     */
    public function paginateFor(Workspace $workspace, Item $item, int $perPage, array $types, ?array $locationIds = null): LengthAwarePaginator
    {
        $sales = array_map(fn (DocumentType $type) => $type->value, array_filter($types, fn (DocumentType $type) => $type->family() === 'sales'));

        $lines = $this->keys(
            SalesDocumentLine::query()
                ->join('sales_documents as documents', 'documents.id', '=', 'sales_document_lines.sales_document_id')
                ->where('documents.workspace_id', $workspace->id)
                ->whereIn('documents.type', $sales)
                ->when($locationIds !== null, fn (Builder $query) => $query->whereIn('documents.location_id', $locationIds))
                ->where('sales_document_lines.item_id', $item->id),
            'sales_document_lines',
            'sales',
        );

        if (in_array(DocumentType::Purchase, $types, true)) {
            $lines->unionAll($this->keys(
                PurchaseLine::query()
                    ->join('purchases as documents', 'documents.id', '=', 'purchase_lines.purchase_id')
                    ->where('documents.workspace_id', $workspace->id)
                    ->where('purchase_lines.item_id', $item->id)
                    ->when($locationIds !== null, fn (Builder $query) => $query->whereIn('documents.location_id', $locationIds)),
                'purchase_lines',
                'purchases',
            ));
        }

        $page = DB::query()->fromSub($lines, 'lines')
            ->orderByDesc('document_date')
            ->orderByDesc('created_at')
            ->orderBy('family')
            ->orderByDesc('document_id')
            ->orderBy('position')
            ->paginate($perPage);

        return $page->setCollection($this->lines($page->getCollection()));
    }

    /** What the union needs of a line: its family and id, and what orders it - its document's date, when it was made, and its place. */
    private function keys(Builder $query, string $table, string $family): QueryBuilder
    {
        return $query->select(["{$table}.id", 'documents.id as document_id', 'documents.document_date', 'documents.created_at', "{$table}.position"])
            ->selectRaw("'{$family}' as family")
            ->toBase();
    }

    /** The rows of a page, read as the lines they are, in the page's order. */
    private function lines(Collection $rows): Collection
    {
        $loaded = [
            'sales'     => SalesDocumentLine::with('document.contact:id,name')->findMany($rows->where('family', 'sales')->pluck('id'))->keyBy('id'),
            'purchases' => PurchaseLine::with('purchase.contact:id,name')->findMany($rows->where('family', 'purchases')->pluck('id'))->keyBy('id'),
        ];

        return $rows->map(fn (object $row) => $loaded[$row->family][$row->id])->values();
    }
}
