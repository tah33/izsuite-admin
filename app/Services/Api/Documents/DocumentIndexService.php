<?php

namespace App\Services\Api\Documents;

use App\Enums\DocumentType;
use App\Enums\ExpenseStatus;
use App\Enums\PurchaseStatus;
use App\Models\User\Expense;
use App\Models\User\Purchase;
use App\Models\User\SalesDocument;
use App\Models\User\Workspace;
use App\Repositories\User\DocumentIndexRepository;
use App\Services\Purchases\PurchaseRules;
use App\Services\Sales\SalesDocumentRules;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\BusinessCalendar;
use App\Services\Shared\WorkspaceAccess;
use App\Services\User\Apps\WorkspaceApps;
use App\Support\Csv;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Documents page (Doc 06): one index of the transaction documents the
 * business's apps keep - never a copy of them. Each row opens its source
 * record; nothing here changes a document (section 10). An app turned off
 * keeps its documents listed, read-only (section 19). Only the kinds the
 * user's role may view are listed (Doc 12), so each row opens.
 */
class DocumentIndexService
{
    public function __construct(
        protected DocumentIndexRepository $documentIndexRepository,
        protected SalesDocumentRules $salesDocumentRules,
        protected PurchaseRules $purchaseRules,
        protected BusinessCalendar $businessCalendar,
        protected WorkspaceAccess $access,
    ) {}

    /** $filters are validated (see ListDocumentsRequest). */
    public function list(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->documentIndexRepository->paginateFor($workspace, $filters, $perPage, $this->today($workspace), $this->types());
    }

    public function today(Workspace $workspace): string
    {
        return $this->businessCalendar->today($workspace)->toDateString();
    }

    /** The app each family's documents come from, by family: "Sales Lite", or "Sales" once it is off. */
    public function sources(Workspace $workspace): array
    {
        $apps = WorkspaceApps::for($workspace);

        return [
            'sales'     => $apps->row('sales')?->app?->name ?? 'Sales',
            'purchases' => $apps->row('purchases')?->app?->name ?? 'Purchases',
            'expenses'  => $apps->row('expenses')?->app?->name ?? 'Expenses',
        ];
    }

    /** The list as the page shows it, whole, as CSV (Doc 06 section 9: Export List). */
    public function export(Workspace $workspace, array $filters): StreamedResponse
    {
        $today   = $this->today($workspace);
        $sources = $this->sources($workspace);
        $types   = $this->types();
        $count   = $this->documentIndexRepository->countFor($workspace, $filters, $today, $types);

        ActivityLogService::record('exported', "Exported {$count} documents", $workspace, [
            'source'  => 'api',
            'filters' => array_filter($filters, fn ($value) => $value !== null && $value !== ''),
        ]);

        $rows = (function () use ($workspace, $filters, $today, $sources, $types) {
            foreach ($this->documentIndexRepository->lazyFor($workspace, $filters, $today, $types) as $document) {
                yield $this->csvRow($document, $today, $sources);
            }
        })();

        return Csv::download('documents-'.$today.'.csv', ['Date', 'Number', 'Type', 'Contact', 'Source Application', 'Status', 'Payment Status', 'Total', 'Currency', 'Balance Due', 'Location'], $rows);
    }

    /**
     * The kinds of document the user's role may view, of those the index lists.
     *
     * @return list<DocumentType>
     */
    private function types(): array
    {
        return $this->access->viewable([...array_values(DocumentType::KINDS), DocumentType::Purchase, DocumentType::Expense]);
    }

    private function csvRow(SalesDocument|Purchase|Expense $document, string $today, array $sources): array
    {
        if ($document instanceof Expense) {
            return [
                $document->document_date,
                $document->number ?? 'Draft',
                'Expense',
                $document->payeeName() ?? '',
                $sources['expenses'] ?? '',
                $document->status->label(),
                ucwords(str_replace('_', ' ', (string) $document->payment_status)),
                (string) $document->grand_total,
                $document->currency,
                $document->status === ExpenseStatus::Recorded ? (string) $document->balance_due : '',
                $document->location?->name ?? '',
            ];
        }

        if ($document instanceof Purchase) {
            return [
                $document->document_date,
                $document->number ?? 'Draft',
                'Purchase',
                $document->supplier_snapshot['name'] ?? $document->contact?->name ?? '',
                $sources['purchases'] ?? '',
                $document->status->label(),
                ucwords(str_replace('_', ' ', (string) $document->payment_status)),
                (string) $document->grand_total,
                $document->currency,
                $document->status === PurchaseStatus::Recorded ? (string) $document->balance_due : '',
                $document->location?->name ?? '',
            ];
        }

        return [
            $document->document_date,
            $document->number ?? 'Draft',
            $document->type->label(),
            $document->customer_snapshot['name'] ?? $document->contact?->name ?? '',
            $sources[$document->type->family()] ?? '',
            ucfirst($this->salesDocumentRules->status($document, $today)),
            ucwords(str_replace('_', ' ', (string) $document->payment_status)),
            (string) $document->grand_total,
            $document->currency,
            $this->salesDocumentRules->holdsBalance($document) ? (string) $document->balance_due : '',
            $document->location?->name ?? '',
        ];
    }
}
