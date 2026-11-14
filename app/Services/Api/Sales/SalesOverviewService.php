<?php

namespace App\Services\Api\Sales;

use App\Enums\DocumentType;
use App\Enums\SalesDocumentStatus;
use App\Models\User\SalesDocument;
use App\Models\User\SalesPayment;
use App\Models\User\Workspace;
use App\Repositories\User\SalesReportRepository;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\Sales\OutstandingInvoicesReport;
use App\Services\Shared\WorkspaceAccess;
use App\Support\Decimal;
use Illuminate\Support\Collection;

/**
 * The Sales Overview (Doc 16 section 16.5), for the full Sales app: what was
 * quoted and won, what was sold, received and credited, and what is still
 * owed - for a period, a location, a customer, a salesperson or team, and a
 * currency. Quotations are labelled apart: "Quotation Value and Confirmed
 * Order Value ... must not be counted as financial Sales". Sales are issued
 * invoices, without tax, as the Dashboard counts them.
 *
 * Amounts are in the base currency, or in the currency chosen when one is.
 * Sales orders (Phase B) are counted apart too: those confirmed in the period,
 * and of those still open - confirmed or on hold - the ones with something
 * left to deliver or to invoice, as of the period's end.
 */
class SalesOverviewService
{
    public function __construct(
        protected SalesReportRepository $sales,
        protected OutstandingInvoicesReport $outstanding,
        protected WorkspaceAccess $access,
        protected ReportPeriod $period,
    ) {}

    /**
     * $input is validated (SalesOverviewRequest): range or date_from and
     * date_to, location_id, contact_id, salesperson_id, sales_team_id and
     * currency. What is still owed is as of the period's end, or today.
     */
    public function figures(Workspace $workspace, array $input): array
    {
        [$from, $to] = $this->period->range($workspace, $input['range'] ?? null, $input['date_from'] ?? null, $input['date_to'] ?? null);
        $asOf        = min($to, $this->period->today($workspace));
        $currency    = $input['currency'] ?? null;
        $filters  = [
            ...array_filter(array_intersect_key($input, array_flip(['location_id', 'contact_id', 'salesperson_id', 'sales_team_id', 'currency']))),
            'location_ids' => $this->access->locations(),
        ];

        if ($filters['location_ids'] === null) {
            unset($filters['location_ids']);
        }

        // The base currency's columns, or the document's own in the currency chosen.
        $total    = fn (Collection $documents, string $column) => Decimal::sum($documents->map(fn (SalesDocument $document) => Decimal::of($currency ? $document->{$column} : $document->{"base_{$column}"})));
        $offers   = $this->sales->offers($workspace, $from, $to, $filters);
        // The status is cast to its enum, so it is compared as one.
        $won      = $offers->filter(fn (SalesDocument $document) => in_array($document->status, [SalesDocumentStatus::Accepted, SalesDocumentStatus::Converted], true));
        $invoices = $this->sales->issued($workspace, DocumentType::Invoice, $from, $to, $filters);
        $notes    = $this->sales->issued($workspace, DocumentType::CreditNote, $from, $to, $filters);
        $money    = $this->sales->moneyMoved($workspace, $from, $to, [...$filters, 'currency' => null, 'document_currency' => $currency]);
        $moved    = fn (string $kind) => Decimal::sum($money->where('kind', $kind)->map(fn (SalesPayment $payment) => Decimal::of($currency ? $payment->applied_amount : $payment->base_amount)));
        $owed     = $this->outstanding->rows($workspace, $asOf, $filters);
        $overdue  = array_filter($owed, fn (array $row) => $row['days_overdue'] > 0);
        $owedSum  = fn (array $rows) => Decimal::sum(array_column($rows, $currency ? 'outstanding' : 'base_outstanding'));
        $orders   = $this->sales->orders($workspace, $to, $filters);
        $open     = $orders->filter(fn (SalesDocument $order) => in_array($order->status, [SalesDocumentStatus::Confirmed, SalesDocumentStatus::OnHold], true));
        $figure   = fn (Collection $documents) => ['count' => $documents->count(), 'value' => $total($documents, 'grand_total')];

        return [
            'from'                => $from,
            'to'                  => $to,
            'currency'            => $currency ?? $workspace->base_currency,
            'quotations'          => ['count' => $offers->count(), 'value' => $total($offers, 'grand_total')],
            'accepted_quotations' => ['count' => $won->count(), 'value' => $total($won, 'grand_total')],
            'confirmed_orders'    => $figure($orders->filter(fn (SalesDocument $order) => $order->document_date >= $from)),
            'orders_to_deliver'   => $figure($open->filter(fn (SalesDocument $order) => in_array($order->delivery_status, ['not_delivered', 'partially_delivered'], true))),
            'orders_to_invoice'   => $figure($open->filter(fn (SalesDocument $order) => $order->invoice_status !== 'invoiced')),
            'sales'               => ['count' => $invoices->count(), 'value' => $total($invoices, 'net_total')],
            'received'            => bcsub($moved(SalesPayment::PAYMENT), $moved(SalesPayment::REFUND), 2),
            'outstanding'         => ['count' => count($owed), 'value' => $owedSum($owed)],
            'overdue'             => ['count' => count($overdue), 'value' => $owedSum($overdue)],
            'credit_notes'        => ['count' => $notes->count(), 'value' => $total($notes, 'net_total')],
        ];
    }
}
