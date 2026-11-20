<?php

namespace App\Repositories\User;

use App\Enums\SalesDocumentStatus;
use App\Models\User\PosCashMovement;
use App\Models\User\PosHeldOrder;
use App\Models\User\PosOrder;
use App\Models\User\PosOrderLine;
use App\Models\User\PosOrderPayment;
use App\Models\User\PosReturn;
use App\Models\User\PosSession;
use App\Models\User\SalesDocumentLine;
use App\Models\User\User;
use App\Models\User\Workspace;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What POS's reports read (Doc 18 section 18.36 - Phase H): its completed
 * sales and their lines, its returns and theirs, and its payments - each
 * dated as its invoice or credit note is, between two business days, at the
 * user's locations, narrowed to a location, register or cashier. Amounts
 * come with what turns them into the base currency.
 */
class PosReportRepository
{
    /** The completed sales whose invoice is dated $from to $to: each with its invoice's base totals, register, location, cashier and salesperson. */
    public function sales(Workspace $workspace, string $from, string $to, array $filters): Collection
    {
        return $this->narrowed(PosOrder::query(), 'pos_orders', $workspace, $filters)
            ->join('sales_documents as invoices', 'invoices.id', '=', 'pos_orders.sales_document_id')
            ->leftJoin('pos_registers', 'pos_registers.id', '=', 'pos_orders.pos_register_id')
            ->leftJoin('locations', 'locations.id', '=', 'pos_orders.location_id')
            ->leftJoin('users as cashiers', 'cashiers.id', '=', 'pos_orders.cashier_id')
            ->leftJoin('users as salespeople', 'salespeople.id', '=', 'invoices.salesperson_id')
            ->where('pos_orders.status', PosOrder::COMPLETED)
            ->where('invoices.status', SalesDocumentStatus::Issued->value)
            ->whereBetween('invoices.document_date', [$from, $to])
            ->orderBy('pos_orders.id')
            ->get([
                'pos_orders.id', 'pos_orders.number', 'pos_orders.completed_at', 'pos_orders.exchange_rate', 'pos_orders.discount_reason', 'pos_orders.discount_approved_by',
                'pos_orders.pos_register_id', 'pos_orders.location_id', 'pos_orders.cashier_id', 'pos_orders.cash_rounding', 'pos_orders.exchange_refunded',
                'invoices.document_date', 'invoices.base_net_total', 'invoices.base_tax_total', 'invoices.base_grand_total', 'invoices.discount_total', 'invoices.taxes', 'invoices.salesperson_id',
                'pos_registers.name as register_name', 'pos_registers.code as register_code', 'locations.name as location_name',
                'cashiers.first_name as cashier_first_name', 'cashiers.last_name as cashier_last_name',
                'salespeople.first_name as salesperson_first_name', 'salespeople.last_name as salesperson_last_name',
            ]);
    }

    /** Those sales' item lines - what SalesFigures::lineNet() needs of each - with their item's category, brand and cost, and their sale's order. */
    public function saleLines(Workspace $workspace, string $from, string $to, array $filters): Collection
    {
        return $this->narrowed($this->lines($from, $to)->join('pos_orders', 'pos_orders.sales_document_id', '=', 'sales_documents.id'), 'pos_orders', $workspace, $filters)
            ->where('pos_orders.status', PosOrder::COMPLETED)
            ->when($filters['category_id'] ?? null, fn (Builder $query, $id) => $query->where('items.category_id', $id))
            ->get($this->lineColumns(['pos_orders.id as pos_order_id', 'pos_orders.pos_register_id', 'pos_orders.cashier_id']));
    }

    /** The returns whose credit note is dated $from to $to: each with its credit note's base totals, register, location, cashier, how it was paid back, and its sale's salesperson. */
    public function returns(Workspace $workspace, string $from, string $to, array $filters): Collection
    {
        return $this->narrowed(PosReturn::query(), 'pos_returns', $workspace, $filters)
            ->join('sales_documents as notes', 'notes.id', '=', 'pos_returns.credit_note_id')
            ->join('pos_orders', 'pos_orders.id', '=', 'pos_returns.pos_order_id')
            ->join('sales_documents as invoices', 'invoices.id', '=', 'pos_orders.sales_document_id')
            ->leftJoin('pos_registers', 'pos_registers.id', '=', 'pos_returns.pos_register_id')
            ->leftJoin('locations', 'locations.id', '=', 'pos_returns.location_id')
            ->leftJoin('users as cashiers', 'cashiers.id', '=', 'pos_returns.cashier_id')
            ->leftJoin('users as salespeople', 'salespeople.id', '=', 'invoices.salesperson_id')
            ->where('notes.status', SalesDocumentStatus::Issued->value)
            ->whereBetween('notes.document_date', [$from, $to])
            ->orderBy('pos_returns.id')
            ->get([
                'pos_returns.id', 'pos_returns.number', 'pos_returns.created_at', 'pos_returns.reason', 'pos_returns.refund_method', 'pos_returns.refund_method_name', 'pos_returns.refund_status',
                'pos_returns.refunded', 'pos_returns.refund_due', 'pos_returns.total', 'pos_returns.currency', 'pos_orders.exchange_rate', 'pos_orders.number as sale_number',
                'pos_returns.pos_register_id', 'pos_returns.location_id', 'pos_returns.cashier_id', 'invoices.salesperson_id',
                'notes.document_date', 'notes.number as credit_note', 'notes.base_net_total', 'notes.base_tax_total', 'notes.base_grand_total', 'notes.taxes',
                'pos_registers.name as register_name', 'pos_registers.code as register_code', 'locations.name as location_name',
                'cashiers.first_name as cashier_first_name', 'cashiers.last_name as cashier_last_name',
                'salespeople.first_name as salesperson_first_name', 'salespeople.last_name as salesperson_last_name',
            ]);
    }

    /** Those returns' credit note lines, as saleLines() gives a sale's. */
    public function returnLines(Workspace $workspace, string $from, string $to, array $filters): Collection
    {
        return $this->narrowed($this->lines($from, $to)->join('pos_returns', 'pos_returns.credit_note_id', '=', 'sales_documents.id'), 'pos_returns', $workspace, $filters)
            ->when($filters['category_id'] ?? null, fn (Builder $query, $id) => $query->where('items.category_id', $id))
            ->get($this->lineColumns(['pos_returns.id as pos_return_id']));
    }

    /** The completed payments of the sales whose invoice is dated $from to $to - each its method and amount, and its sale's rate. */
    public function payments(Workspace $workspace, string $from, string $to, array $filters): Collection
    {
        return $this->narrowed(PosOrder::query(), 'pos_orders', $workspace, $filters)
            ->join('sales_documents as invoices', 'invoices.id', '=', 'pos_orders.sales_document_id')
            ->join('pos_order_payments', 'pos_order_payments.pos_order_id', '=', 'pos_orders.id')
            ->where('pos_orders.status', PosOrder::COMPLETED)
            ->where('pos_order_payments.status', PosOrderPayment::COMPLETED)
            ->where('invoices.status', SalesDocumentStatus::Issued->value)
            ->whereBetween('invoices.document_date', [$from, $to])
            ->orderBy('pos_order_payments.id')
            ->get(['pos_order_payments.method_name', 'pos_order_payments.method_type', 'pos_order_payments.status', 'pos_order_payments.amount', 'pos_orders.exchange_rate', 'pos_orders.id as pos_order_id']);
    }

    /**
     * The completed sales whose invoice is dated $from to $to that gave a
     * discount - on a line or the whole - or had a price overridden (Phase
     * H2): with their invoice's subtotal, its lines' discounts, and who
     * approved a discount beyond the limit.
     */
    public function discounted(Workspace $workspace, string $from, string $to, array $filters): Collection
    {
        $exists = fn (string $table, string $column, string $outer) => fn ($query) => $query->select(DB::raw(1))->from($table)->whereColumn("{$table}.{$column}", $outer);

        return $this->narrowed(PosOrder::query(), 'pos_orders', $workspace, $filters)
            ->join('sales_documents as invoices', 'invoices.id', '=', 'pos_orders.sales_document_id')
            ->leftJoin('pos_registers', 'pos_registers.id', '=', 'pos_orders.pos_register_id')
            ->leftJoin('users as cashiers', 'cashiers.id', '=', 'pos_orders.cashier_id')
            ->leftJoin('users as approvers', 'approvers.id', '=', 'pos_orders.discount_approved_by')
            ->where('pos_orders.status', PosOrder::COMPLETED)
            ->where('invoices.status', SalesDocumentStatus::Issued->value)
            ->whereBetween('invoices.document_date', [$from, $to])
            ->where(fn (Builder $query) => $query
                ->where('invoices.discount_total', '>', 0)
                ->orWhereExists(fn ($lines) => $exists('sales_document_lines', 'sales_document_id', 'invoices.id')($lines)->where('sales_document_lines.discount_amount', '>', 0))
                ->orWhereExists(fn ($lines) => $exists('pos_order_lines', 'pos_order_id', 'pos_orders.id')($lines)->where('pos_order_lines.price_source', PosOrderLine::OVERRIDE)))
            ->select([
                'pos_orders.id', 'pos_orders.number', 'pos_orders.exchange_rate', 'pos_orders.discount_reason', 'pos_orders.discount_approved_by',
                'invoices.document_date', 'invoices.subtotal', 'invoices.discount_total',
                'pos_registers.name as register_name', 'pos_registers.code as register_code',
                'cashiers.first_name as cashier_first_name', 'cashiers.last_name as cashier_last_name',
                'approvers.first_name as approver_first_name', 'approvers.last_name as approver_last_name',
            ])
            ->addSelect(['line_discounts' => SalesDocumentLine::query()->selectRaw('COALESCE(SUM(discount_amount), 0)')->whereColumn('sales_document_lines.sales_document_id', 'invoices.id')])
            ->orderBy('invoices.document_date')
            ->orderBy('pos_orders.id')
            ->get();
    }

    /** The lines of $orderIds whose price was overridden: each its order, quantity and price, the price it replaced and why. */
    public function overrides(array $orderIds): Collection
    {
        return PosOrderLine::query()
            ->join('sales_document_lines', 'sales_document_lines.id', '=', 'pos_order_lines.sales_document_line_id')
            ->whereIn('pos_order_lines.pos_order_id', $orderIds)
            ->where('pos_order_lines.price_source', PosOrderLine::OVERRIDE)
            ->orderBy('pos_order_lines.id')
            ->get(['pos_order_lines.pos_order_id', 'pos_order_lines.standard_price', 'pos_order_lines.override_reason', 'sales_document_lines.unit_price', 'sales_document_lines.quantity']);
    }

    /** The held orders cancelled or expired from $from to $to: each with its register, cashier, and who cancelled it. */
    public function closedHeld(Workspace $workspace, CarbonInterface $from, CarbonInterface $to, array $filters): Collection
    {
        return $this->called($this->narrowed(PosHeldOrder::query(), 'pos_held_orders', $workspace, $filters), 'pos_held_orders', 'pos_held_orders.closed_by')
            ->whereIn('pos_held_orders.status', [PosHeldOrder::CANCELLED, PosHeldOrder::EXPIRED])
            ->whereBetween('pos_held_orders.closed_at', [$from, $to])
            ->addSelect(['pos_held_orders.number', 'pos_held_orders.status', 'pos_held_orders.currency', 'pos_held_orders.grand_total', 'pos_held_orders.cancel_reason', 'pos_held_orders.closed_at'])
            ->get();
    }

    /** The payments declined from $from to $to: each with its sale, register, cashier, and who declined it. */
    public function declinedPayments(Workspace $workspace, CarbonInterface $from, CarbonInterface $to, array $filters): Collection
    {
        return $this->called($this->narrowed(PosOrder::query(), 'pos_orders', $workspace, $filters)->join('pos_order_payments', 'pos_order_payments.pos_order_id', '=', 'pos_orders.id'), 'pos_orders', 'pos_order_payments.closed_by')
            ->where('pos_order_payments.status', PosOrderPayment::DECLINED)
            ->whereBetween('pos_order_payments.closed_at', [$from, $to])
            ->addSelect(['pos_orders.number', 'pos_orders.currency', 'pos_order_payments.method_name', 'pos_order_payments.amount', 'pos_order_payments.decline_reason', 'pos_order_payments.closed_at'])
            ->get();
    }

    /** The refunds declined from $from to $to: each with its return, register, cashier, and who declined it. */
    public function declinedRefunds(Workspace $workspace, CarbonInterface $from, CarbonInterface $to, array $filters): Collection
    {
        return $this->called($this->narrowed(PosReturn::query(), 'pos_returns', $workspace, $filters), 'pos_returns', 'pos_returns.refund_closed_by')
            ->where('pos_returns.refund_status', PosReturn::DECLINED)
            ->whereBetween('pos_returns.refund_closed_at', [$from, $to])
            ->addSelect(['pos_returns.number', 'pos_returns.currency', 'pos_returns.total', 'pos_returns.refund_method_name', 'pos_returns.refund_decline_reason', 'pos_returns.refund_closed_at as closed_at'])
            ->get();
    }

    /** $query of $table's records with their register's name and code, their cashier's name, and that of the user $closer names. */
    private function called(Builder $query, string $table, string $closer): Builder
    {
        return $query
            ->leftJoin('pos_registers', 'pos_registers.id', '=', "{$table}.pos_register_id")
            ->leftJoin('users as cashiers', 'cashiers.id', '=', "{$table}.cashier_id")
            ->leftJoin('users as closers', 'closers.id', '=', $closer)
            ->select([
                'pos_registers.name as register_name', 'pos_registers.code as register_code', 'cashiers.first_name as cashier_first_name', 'cashiers.last_name as cashier_last_name',
                'closers.first_name as closer_first_name', 'closers.last_name as closer_last_name',
            ]);
    }
    /**
     * The sessions opened from $from to $to (Phase H3), at the registers of
     * the user's locations, narrowed to a location, register or cashier: each
     * with its register, cashier, and its latest closing's count and figures.
     */
    public function sessions(Workspace $workspace, CarbonInterface $from, CarbonInterface $to, array $filters): Collection
    {
        return $this->sessionsAt($workspace, $filters)
            ->leftJoin('pos_session_closings as closings', 'closings.id', '=', DB::raw('(select max(latest.id) from pos_session_closings as latest where latest.pos_session_id = pos_sessions.id)'))
            ->whereBetween('pos_sessions.opened_at', [$from, $to])
            ->orderBy('pos_sessions.opened_at')
            ->orderBy('pos_sessions.id')
            ->get([
                'pos_sessions.*', 'pos_registers.name as register_name', 'pos_registers.code as register_code',
                'cashiers.first_name as cashier_first_name', 'cashiers.last_name as cashier_last_name',
                'closings.id as closing_id', 'closings.expected_cash', 'closings.counted_cash', 'closings.variance', 'closings.totals as closing_totals',
            ]);
    }

    /**
     * The sessions' latest closings from $from to $to that were over or short
     * (Phase H3): each with its session, register, cashier, and who approved
     * a variance beyond the limit.
     */
    public function variances(Workspace $workspace, CarbonInterface $from, CarbonInterface $to, array $filters): Collection
    {
        return $this->sessionsAt($workspace, $filters)
            ->join('pos_session_closings as closings', 'closings.pos_session_id', '=', 'pos_sessions.id')
            ->leftJoin('users as approvers', 'approvers.id', '=', 'closings.approved_by')
            ->whereRaw('closings.id = (select max(latest.id) from pos_session_closings as latest where latest.pos_session_id = pos_sessions.id)')
            ->where('closings.variance', '!=', 0)
            ->whereBetween('closings.closed_at', [$from, $to])
            ->orderBy('closings.closed_at')
            ->orderBy('closings.id')
            ->get([
                'pos_sessions.number', 'pos_sessions.currency', 'closings.approved_by', 'pos_registers.name as register_name', 'pos_registers.code as register_code',
                'cashiers.first_name as cashier_first_name', 'cashiers.last_name as cashier_last_name', 'approvers.first_name as approver_first_name', 'approvers.last_name as approver_last_name',
                'closings.expected_cash', 'closings.counted_cash', 'closings.variance', 'closings.variance_reason', 'closings.needs_approval', 'closings.closed_at',
            ]);
    }

    /**
     * The cash movements recorded from $from to $to (Phase H3), narrowed - its
     * cashier being who recorded it: each with its register, session, the
     * account on the other side, and who recorded and decided it.
     */
    public function cashMovements(Workspace $workspace, CarbonInterface $from, CarbonInterface $to, array $filters): Collection
    {
        return $this->narrowed(PosCashMovement::query(), 'pos_cash_movements', $workspace, $filters, 'created_by')
            ->leftJoin('pos_registers', 'pos_registers.id', '=', 'pos_cash_movements.pos_register_id')
            ->leftJoin('pos_sessions', 'pos_sessions.id', '=', 'pos_cash_movements.pos_session_id')
            ->leftJoin('payment_accounts as accounts', 'accounts.id', '=', 'pos_cash_movements.payment_account_id')
            ->leftJoin('users as creators', 'creators.id', '=', 'pos_cash_movements.created_by')
            ->leftJoin('users as deciders', 'deciders.id', '=', 'pos_cash_movements.decided_by')
            ->whereBetween('pos_cash_movements.created_at', [$from, $to])
            ->orderBy('pos_cash_movements.created_at')
            ->orderBy('pos_cash_movements.id')
            ->get([
                'pos_cash_movements.number', 'pos_cash_movements.type', 'pos_cash_movements.direction', 'pos_cash_movements.amount', 'pos_cash_movements.currency', 'pos_cash_movements.reason',
                'pos_cash_movements.status', 'pos_cash_movements.payee_name', 'pos_cash_movements.decided_by', 'pos_cash_movements.created_at',
                'pos_registers.name as register_name', 'pos_registers.code as register_code', 'pos_sessions.number as session_number', 'accounts.name as account_name',
                'creators.first_name as creator_first_name', 'creators.last_name as creator_last_name', 'deciders.first_name as decider_first_name', 'deciders.last_name as decider_last_name',
            ]);
    }

    /** The business's sessions, with their register and cashier, narrowed as narrowed() narrows - a session's location being its register's. */
    private function sessionsAt(Workspace $workspace, array $filters): Builder
    {
        return PosSession::query()
            ->join('pos_registers', 'pos_registers.id', '=', 'pos_sessions.pos_register_id')
            ->leftJoin('users as cashiers', 'cashiers.id', '=', 'pos_sessions.cashier_id')
            ->where('pos_sessions.workspace_id', $workspace->id)
            ->when(isset($filters['location_ids']), fn (Builder $query) => $query->whereIn('pos_registers.location_id', $filters['location_ids']))
            ->when($filters['location_id'] ?? null, fn (Builder $query, $id) => $query->where('pos_registers.location_id', $id))
            ->when($filters['register_id'] ?? null, fn (Builder $query, $id) => $query->where('pos_sessions.pos_register_id', $id))
            ->when($filters['cashier_id'] ?? null, fn (Builder $query, $id) => $query->where('pos_sessions.cashier_id', $id));
    }
    /**
     * Whom POS's reports can be narrowed to (Phase H1): the cashiers of the
     * registers at $locationIds - every location for null - and whoever made
     * a sale or a return there, cashier since or not. By name.
     */
    public function cashiers(Workspace $workspace, ?array $locationIds): Collection
    {
        $at = fn (Builder $query) => $query->where('workspace_id', $workspace->id)->when($locationIds !== null, fn (Builder $query) => $query->whereIn('location_id', $locationIds))->select('cashier_id');

        return User::query()
            ->where(fn (Builder $query) => $query
                ->whereIn('id', $at(PosOrder::query()))
                ->orWhereIn('id', $at(PosReturn::query()))
                ->orWhereIn('id', DB::table('pos_register_cashiers')->join('pos_registers', 'pos_registers.id', '=', 'pos_register_cashiers.pos_register_id')
                    ->where('pos_registers.workspace_id', $workspace->id)
                    ->when($locationIds !== null, fn ($query) => $query->whereIn('pos_registers.location_id', $locationIds))
                    ->select('pos_register_cashiers.user_id')))
            ->orderBy('first_name')->orderBy('last_name')->orderBy('id')
            ->get(['id', 'first_name', 'last_name']);
    }

    /** $query of POS's $table narrowed to the business, the user's locations, and a location, register or cashier asked for - its $cashier column. */
    private function narrowed(Builder $query, string $table, Workspace $workspace, array $filters, string $cashier = 'cashier_id'): Builder
    {
        return $query
            ->where("{$table}.workspace_id", $workspace->id)
            ->when(isset($filters['location_ids']), fn (Builder $query) => $query->whereIn("{$table}.location_id", $filters['location_ids']))
            ->when($filters['location_id'] ?? null, fn (Builder $query, $id) => $query->where("{$table}.location_id", $id))
            ->when($filters['register_id'] ?? null, fn (Builder $query, $id) => $query->where("{$table}.pos_register_id", $id))
            ->when($filters['cashier_id'] ?? null, fn (Builder $query, $id) => $query->where("{$table}.{$cashier}", $id));
    }

    /** Issued documents' item lines - as SalesDocumentLine, for SalesFigures - dated $from to $to, with their item's category, brand and cost. */
    private function lines(string $from, string $to): Builder
    {
        return SalesDocumentLine::query()
            ->join('sales_documents', 'sales_documents.id', '=', 'sales_document_lines.sales_document_id')
            ->leftJoin('items', 'items.id', '=', 'sales_document_lines.item_id')
            ->leftJoin('item_categories', 'item_categories.id', '=', 'items.category_id')
            ->leftJoin('item_brands', 'item_brands.id', '=', 'items.brand_id')
            ->whereNotNull('sales_document_lines.item_id')
            ->where('sales_documents.status', SalesDocumentStatus::Issued->value)
            ->whereBetween('sales_documents.document_date', [$from, $to]);
    }

    private function lineColumns(array $own): array
    {
        return [
            ...$own,
            'sales_document_lines.id as line_id', 'sales_document_lines.item_id', 'sales_document_lines.name', 'sales_document_lines.quantity', 'sales_document_lines.amount',
            'sales_document_lines.tax_amount', 'sales_document_lines.unit_price', 'sales_document_lines.discount_amount', 'sales_document_lines.sales_document_id',
            'sales_documents.subtotal', 'sales_documents.discount_total', 'sales_documents.price_tax_mode', 'sales_documents.exchange_rate',
            'items.category_id', 'item_categories.name as category_name', 'items.brand_id', 'item_brands.name as brand_name', 'items.purchase_price',
        ];
    }
}
