<?php

namespace App\Repositories\User;

use App\Models\User\PosOrder;
use App\Models\User\PosOrderPayment;
use App\Models\User\PosReturn;
use App\Models\User\PosSession;
use App\Models\User\Workspace;
use App\Support\Decimal;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What POS took between two moments (Doc 18 sections 18.3 and 18.35 - Phase
 * G2), for its Overview and the Dashboard: at $locationIds - any, for null -
 * each amount in the base currency, as its invoice or sale was valued (each
 * sale's rate kept with it).
 */
class PosOverviewRepository
{
    /** @return array{count: int, total: string} the completed sales, and their invoices' total */
    public function sales(Workspace $workspace, CarbonInterface $from, CarbonInterface $to, ?array $locationIds): array
    {
        $row = $this->orders($workspace, $from, $to, $locationIds)
            ->join('sales_documents', 'sales_documents.id', '=', 'pos_orders.sales_document_id')
            ->selectRaw('count(*) as sales, coalesce(sum(sales_documents.base_grand_total), 0) as total')
            ->first();

        return ['count' => (int) ($row->sales ?? 0), 'total' => Decimal::round((string) ($row->total ?? '0'))];
    }

    /** @return Collection<int, array{method: string, amount: string}> what each method took in those sales - completed payments - the most first */
    public function payments(Workspace $workspace, CarbonInterface $from, CarbonInterface $to, ?array $locationIds): Collection
    {
        return $this->orders($workspace, $from, $to, $locationIds)
            ->join('pos_order_payments', 'pos_order_payments.pos_order_id', '=', 'pos_orders.id')
            ->where('pos_order_payments.status', PosOrderPayment::COMPLETED)
            ->groupBy('pos_order_payments.method_name')
            ->selectRaw('pos_order_payments.method_name as method, sum(pos_order_payments.amount * pos_orders.exchange_rate) as amount')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($row) => ['method' => (string) $row->method, 'amount' => Decimal::round((string) $row->amount)]);
    }

    /** @return array{count: int, credited: string, refunded: string} the returns made, what they credit and what was paid out */
    public function returns(Workspace $workspace, CarbonInterface $from, CarbonInterface $to, ?array $locationIds): array
    {
        $row = DB::table('pos_returns')->where('pos_returns.workspace_id', $workspace->id)
            ->whereBetween('pos_returns.created_at', [$from, $to])
            ->when($locationIds !== null, fn (Builder $query) => $query->whereIn('pos_returns.location_id', $locationIds))
            ->join('pos_orders', 'pos_orders.id', '=', 'pos_returns.pos_order_id')
            ->selectRaw('count(*) as returns, coalesce(sum(pos_returns.total * pos_orders.exchange_rate), 0) as credited, coalesce(sum(pos_returns.refunded * pos_orders.exchange_rate), 0) as refunded')
            ->first();

        return ['count' => (int) ($row->returns ?? 0), 'credited' => Decimal::round((string) ($row->credited ?? '0')), 'refunded' => Decimal::round((string) ($row->refunded ?? '0'))];
    }

    /** @return Collection<int, object> the sessions closed then with a variance - each its number, register, currency and variance - the largest first */
    public function variances(Workspace $workspace, CarbonInterface $from, CarbonInterface $to, ?array $locationIds): Collection
    {
        return DB::table('pos_session_closings')
            ->join('pos_sessions', 'pos_sessions.id', '=', 'pos_session_closings.pos_session_id')
            ->join('pos_registers', 'pos_registers.id', '=', 'pos_sessions.pos_register_id')
            ->where('pos_sessions.workspace_id', $workspace->id)
            ->whereBetween('pos_session_closings.closed_at', [$from, $to])
            ->whereNull('pos_session_closings.reopened_at')
            ->where('pos_session_closings.variance', '!=', 0)
            ->when($locationIds !== null, fn (Builder $query) => $query->whereIn('pos_registers.location_id', $locationIds))
            ->orderByRaw('abs(pos_session_closings.variance) desc')
            ->get(['pos_sessions.id', 'pos_sessions.number', 'pos_sessions.currency', 'pos_registers.name as register', 'pos_registers.code', 'pos_session_closings.variance']);
    }

    /** @return Collection<int, object> the items sold most - by what they took - each its name, quantity and amount, at most $limit */
    public function topItems(Workspace $workspace, CarbonInterface $from, CarbonInterface $to, ?array $locationIds, int $limit = 5): Collection
    {
        return $this->orders($workspace, $from, $to, $locationIds)
            ->join('sales_document_lines', 'sales_document_lines.sales_document_id', '=', 'pos_orders.sales_document_id')
            ->whereNotNull('sales_document_lines.item_id')
            ->groupBy('sales_document_lines.item_id', 'sales_document_lines.name')
            ->selectRaw('sales_document_lines.name as name, sum(sales_document_lines.quantity) as quantity, sum(sales_document_lines.amount * pos_orders.exchange_rate) as amount')
            ->orderByDesc('amount')
            ->limit($limit)
            ->get();
    }

    /** The business's open sessions at $locationIds, the earliest opened first, each with its register and cashier. */
    public function openSessions(Workspace $workspace, ?array $locationIds): Collection
    {
        return PosSession::query()->where('workspace_id', $workspace->id)->whereIn('status', PosSession::LIVE)
            ->when($locationIds !== null, fn ($query) => $query->whereHas('register', fn ($query) => $query->whereIn('location_id', $locationIds)))
            ->with(['register:id,name,code', 'cashier:id,first_name,last_name'])
            ->orderBy('opened_at')
            ->get();
    }

    /** The completed sales between $from and $to at $locationIds. */
    private function orders(Workspace $workspace, CarbonInterface $from, CarbonInterface $to, ?array $locationIds): Builder
    {
        return DB::table('pos_orders')->where('pos_orders.workspace_id', $workspace->id)
            ->where('pos_orders.status', PosOrder::COMPLETED)
            ->whereBetween('pos_orders.completed_at', [$from, $to])
            ->when($locationIds !== null, fn (Builder $query) => $query->whereIn('pos_orders.location_id', $locationIds));
    }
}
