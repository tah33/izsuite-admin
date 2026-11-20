<?php

namespace App\Repositories\User;

use App\Enums\DocumentType;
use App\Enums\ExpenseStatus;
use App\Enums\PurchaseStatus;
use App\Enums\SalesDocumentStatus;
use App\Models\User\Expense;
use App\Models\Frontend\Application;
use App\Models\User\PosReturn;
use App\Models\User\Purchase;
use App\Models\User\SalesDocument;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * The Documents page's index (Doc 06 section 4): the business's transaction
 * documents across its apps as one list - Sales Lite's quotations, proforma
 * invoices, invoices and credit notes, Purchases Lite's purchases and
 * Expenses Lite's expenses - never an expense's payment voucher, which stays
 * with its expense (Doc 10 section 16). Newest first; searched by number,
 * reference, contact or payee, item or expense category, or amount, and
 * filtered.
 *
 * Each app's documents stay in their own table: the list is the union of one
 * query per app, paged as one, and each page's rows are then read as the
 * records they are - a SalesDocument, a Purchase or an Expense - with what
 * their row needs: a credit note's invoice, for what it may still refund.
 */
class DocumentIndexRepository
{
    /** The app families whose documents the index lists - by its source filter. */
    public const SOURCES = ['sales', 'purchases', 'expenses', 'pos'];

    private const OFFERS = [DocumentType::Quotation->value, DocumentType::Proforma->value];

    private const SALES_WITH = [
        'contact:id,name', 'location:id,name', 'source:id,amount_paid,refunded_amount',
        // Doc 18 Phase G3: a POS sale's or return's - its order, register and session.
        'posOrder:id,sales_document_id,number,invoiced_at,pos_register_id,pos_session_id', 'posOrder.register:id,name,code', 'posOrder.session:id,number',
        'posReturn:id,number,credit_note_id,pos_order_id,pos_register_id,pos_session_id', 'posReturn.order:id,number', 'posReturn.register:id,name,code', 'posReturn.session:id,number',
    ];

    /** Doc 18 Phase G3: a refund receipt's - its sale, register, session, credit note and customer. */
    private const REFUND_RECEIPTS_WITH = ['order:id,number', 'register:id,name,code', 'session:id,number', 'creditNote:id,number,document_date', 'contact:id,name', 'location:id,name'];

    private const PURCHASES_WITH = ['contact:id,name', 'location:id,name'];

    private const EXPENSES_WITH = ['contact:id,name', 'location:id,name'];

    /** @param  list<DocumentType>  $types  the kinds listed - see union() */
    public function paginateFor(Workspace $workspace, array $filters, int $perPage, string $today, array $types): LengthAwarePaginator
    {
        $page = $this->union($workspace, $filters, $today, $types)->paginate($perPage);

        return $page->setCollection($this->records($page->getCollection()));
    }

    /**
     * Everything the filters let through, read a few hundred at a time - for an export.
     *
     * @param  list<DocumentType>  $types
     */
    public function lazyFor(Workspace $workspace, array $filters, string $today, array $types): LazyCollection
    {
        return $this->union($workspace, $filters, $today, $types)->lazy(500)->chunk(500)->flatMap(fn (LazyCollection $rows) => $this->records($rows->collect()));
    }

    /** @param  list<DocumentType>  $types */
    public function countFor(Workspace $workspace, array $filters, string $today, array $types): int
    {
        return $this->union($workspace, $filters, $today, $types)->count();
    }

    /**
     * Each app's documents the filters let through, as one list of what
     * finds them - their family and id - and what orders them: newest first.
     * Only documents of $types - sales kinds, Purchase, Expense - are among
     * them: those the user may view.
     *
     * @param  list<DocumentType>  $types
     */
    private function union(Workspace $workspace, array $filters, string $today, array $types): QueryBuilder
    {
        $sales = array_map(fn (DocumentType $type) => $type->value, array_filter($types, fn (DocumentType $type) => $type->family() === 'sales'));

        $halves = array_values(array_filter([
            $sales && $this->wants($filters, 'sales') ? $this->keys($this->sales($workspace, $filters, $today)->whereIn('type', $sales), 'sales') : null,
            in_array(DocumentType::Purchase, $types, true) && $this->wants($filters, 'purchases') ? $this->keys($this->purchases($workspace, $filters, $today), 'purchases') : null,
            in_array(DocumentType::Expense, $types, true) && $this->wants($filters, 'expenses') ? $this->keys($this->expenses($workspace, $filters, $today), 'expenses') : null,
            // Doc 18 Phase G3: POS's refund receipts.
            ($filters['refund_receipts'] ?? false) && $this->wantsRefundReceipts($filters) ? $this->refundReceipts($workspace, $filters) : null,
        ]));

        // Nothing asked for comes from an app with documents: an empty list, shaped like the rest.
        $union = array_shift($halves) ?? $this->keys(SalesDocument::query()->whereRaw('1 = 0'), 'sales');

        foreach ($halves as $half) {
            $union->unionAll($half);
        }

        return DB::query()->fromSub($union, 'documents')
            ->orderByDesc('document_date')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /** Whether $family's documents can be among what the filters ask for: its source, a type of its own, a status it has. */
    private function wants(array $filters, string $family): bool
    {
        $type   = $filters['type'] ?? null;
        $status = $filters['status'] ?? null;
        $credit = in_array($filters['payment_status'] ?? null, ['refunded', 'available', 'partially_applied', 'fully_applied'], true);

        return match ($family) {
            // Doc 18 Phase G3: POS's sales receipts, invoices and credit notes are sales documents too.
            'sales'     => in_array($filters['source'] ?? 'sales', ['sales', 'pos'], true)
                && ! in_array($type, [DocumentType::Purchase->value, DocumentType::Expense->value], true)
                && $status !== PurchaseStatus::Recorded->value,
            'purchases' => ($filters['source'] ?? 'purchases') === 'purchases'
                && (! $type || $type === DocumentType::Purchase->value)
                && (! $status || in_array($status, [PurchaseStatus::Draft->value, PurchaseStatus::Recorded->value, PurchaseStatus::Void->value], true))
                && ! $credit,
            'expenses'  => ($filters['source'] ?? 'expenses') === 'expenses'
                && (! $type || $type === DocumentType::Expense->value)
                && (! $status || in_array($status, [ExpenseStatus::Draft->value, ExpenseStatus::Recorded->value, ExpenseStatus::Void->value], true))
                && ! $credit,
        };
    }

    /** What the union needs of a document: its family and id, and what orders it. $family is one of this class's own words. */
    private function keys(Builder $query, string $family): QueryBuilder
    {
        return $query->select(['id', 'document_date', 'created_at'])->selectRaw("'{$family}' as family")->toBase();
    }

    /** The rows of a page, read as the records they are, in the page's order. */
    private function records(Collection $rows): Collection
    {
        $loaded = [
            'sales'     => SalesDocument::with(self::SALES_WITH)->findMany($rows->where('family', 'sales')->pluck('id'))->keyBy('id'),
            'purchases' => Purchase::with(self::PURCHASES_WITH)->findMany($rows->where('family', 'purchases')->pluck('id'))->keyBy('id'),
            'expenses'  => Expense::with(self::EXPENSES_WITH)->findMany($rows->where('family', 'expenses')->pluck('id'))->keyBy('id'),
            'pos_returns' => PosReturn::with(self::REFUND_RECEIPTS_WITH)->findMany($rows->where('family', 'pos_returns')->pluck('id'))->keyBy('id'),
        ];

        return $rows->map(fn (object $row) => $loaded[$row->family][$row->id])->values();
    }

    private function sales(Workspace $workspace, array $filters, string $today): Builder
    {
        return SalesDocument::query()
            ->where('workspace_id', $workspace->id)
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $this->search($query, $search, ['customer_reference', 'sales_reference'], amounts: $filters['values']['sales'] ?? false))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when(($filters['source'] ?? null) === 'pos', fn (Builder $query) => $this->fromPos($query))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $this->whereStatus($query, $status, $today))
            ->when($filters['payment_status'] ?? null, fn (Builder $query, string $status) => $query->where('payment_status', $status))
            ->when(filter_var($filters['overdue'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $query) => $query
                ->where('type', DocumentType::Invoice->value)
                ->where('status', SalesDocumentStatus::Issued->value)
                ->where('due_date', '<', $today)
                ->where('balance_due', '>', 0))
            ->tap(fn (Builder $query) => $this->common($query, $filters));
    }

    /** POS's sales documents (Doc 18 Phase G3): the invoices it made - its sales receipts and formal invoices - and its returns' credit notes. */
    private function fromPos(Builder $query): Builder
    {
        return $query->where(fn (Builder $query) => $query
            ->whereIn('source_app_id', Application::query()->where('family_key', 'pos')->select('id'))
            ->orWhereIn('id', PosReturn::query()->select('credit_note_id')));
    }

    /** Whether POS's refund receipts can be among what the filters ask for (Doc 18 Phase G3): no type, status or payment of the apps' own. */
    private function wantsRefundReceipts(array $filters): bool
    {
        return in_array($filters['source'] ?? 'pos', ['pos'], true)
            && empty($filters['type']) && empty($filters['status']) && empty($filters['payment_status']) && ! filter_var($filters['overdue'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * POS's returns as their refund receipts (Doc 18 Phase G3), each dated as
     * its credit note is - by its number or its sale's, its customer, where,
     * in which currency and when.
     */
    private function refundReceipts(Workspace $workspace, array $filters): QueryBuilder
    {
        return PosReturn::query()
            ->join('sales_documents', 'sales_documents.id', '=', 'pos_returns.credit_note_id')
            ->where('pos_returns.workspace_id', $workspace->id)
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $query) => $query
                ->where('pos_returns.number', 'like', "%{$search}%")
                ->orWhereHas('order', fn (Builder $query) => $query->where('number', 'like', "%{$search}%"))))
            ->when($filters['contact_id'] ?? null, fn (Builder $query, $id) => $query->where('pos_returns.contact_id', $id))
            ->when($filters['location_id'] ?? null, fn (Builder $query, $id) => $query->where('pos_returns.location_id', $id))
            ->when(($filters['location_ids'] ?? null) !== null, fn (Builder $query) => $query->whereIn('pos_returns.location_id', $filters['location_ids']))
            ->when($filters['currency'] ?? null, fn (Builder $query, string $currency) => $query->where('pos_returns.currency', $currency))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->where('sales_documents.document_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->where('sales_documents.document_date', '<=', $date))
            ->select(['pos_returns.id', 'sales_documents.document_date', 'pos_returns.created_at'])
            ->selectRaw("'pos_returns' as family")
            ->toBase();
    }

    private function purchases(Workspace $workspace, array $filters, string $today): Builder
    {
        return Purchase::query()
            ->where('workspace_id', $workspace->id)
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $this->search($query, $search, ['reference', 'supplier_invoice_number'], amounts: $filters['values']['purchases'] ?? false))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['payment_status'] ?? null, fn (Builder $query, string $status) => $query->where('payment_status', $status))
            ->when(filter_var($filters['overdue'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $query) => $query
                ->where('status', PurchaseStatus::Recorded->value)
                ->where('due_date', '<', $today)
                ->where('balance_due', '>', 0))
            ->tap(fn (Builder $query) => $this->common($query, $filters));
    }

    private function expenses(Workspace $workspace, array $filters, string $today): Builder
    {
        return Expense::query()
            ->where('workspace_id', $workspace->id)
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $this->search($query, $search, ['reference', 'receipt_number', 'payee_name'], ['category_name', 'description'], amounts: $filters['values']['expenses'] ?? false))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['payment_status'] ?? null, fn (Builder $query, string $status) => $query->where('payment_status', $status))
            ->when(filter_var($filters['overdue'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $query) => $query
                ->where('status', ExpenseStatus::Recorded->value)
                ->where('due_date', '<', $today)
                ->where('balance_due', '>', 0))
            ->tap(fn (Builder $query) => $this->common($query, $filters));
    }

    /** The filters every app's documents share. */
    private function common(Builder $query, array $filters): void
    {
        $query
            ->when($filters['contact_id'] ?? null, fn (Builder $query, $id) => $query->where('contact_id', $id))
            ->when($filters['location_id'] ?? null, fn (Builder $query, $id) => $query->where('location_id', $id))
            ->atLocations($filters['location_ids'] ?? null)
            ->when($filters['currency'] ?? null, fn (Builder $query, string $currency) => $query->where('currency', $currency))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->where('document_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->where('document_date', '<=', $date));
    }

    /**
     * By number, the columns in $references - its references, a payee's name
     * typed in - the contact's name, a line's $lineColumns - its item, or an
     * expense line's category and description - or the total, typed as a
     * number, while the user may see the app's values ($amounts).
     */
    private function search(Builder $query, string $search, array $references, array $lineColumns = ['name'], bool $amounts = false): Builder
    {
        $amount = str_replace(',', '', $search);

        return $query->where(function (Builder $query) use ($search, $references, $lineColumns, $amount, $amounts) {
            $query->where('number', 'like', "%{$search}%");

            foreach ($references as $column) {
                $query->orWhere($column, 'like', "%{$search}%");
            }

            $query->orWhereHas('contact', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                ->orWhereHas('lines', fn (Builder $query) => $query->where(function (Builder $query) use ($search, $lineColumns) {
                    foreach ($lineColumns as $column) {
                        $query->orWhere($column, 'like', "%{$search}%");
                    }
                }))
                ->when($amounts && is_numeric($amount), fn (Builder $query) => $query->orWhere('grand_total', $amount));
        });
    }

    /** A draft or sent quotation or proforma past its expiry date reads as expired, and as nothing else. */
    private function whereStatus(Builder $query, string $status, string $today): Builder
    {
        $open = [SalesDocumentStatus::Draft->value, SalesDocumentStatus::Sent->value];

        if ($status === SalesDocumentStatus::EXPIRED) {
            return $query->whereIn('type', self::OFFERS)->whereIn('status', $open)->where('expiry_date', '<', $today);
        }

        if (in_array($status, $open, true)) {
            return $query->where('status', $status)->where(fn (Builder $query) => $query
                ->whereNotIn('type', self::OFFERS)
                ->orWhereNull('expiry_date')
                ->orWhere('expiry_date', '>=', $today));
        }

        return $query->where('status', $status);
    }
}
