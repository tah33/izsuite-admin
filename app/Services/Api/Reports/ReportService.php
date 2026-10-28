<?php

namespace App\Services\Api\Reports;

use App\Enums\DocumentType;
use App\Models\User\Workspace;
use App\Repositories\User\BusinessSettingRepository;
use App\Repositories\User\ContactRepository;
use App\Services\Documents\DocumentFormat;
use App\Services\Documents\DocumentTemplates;
use App\Services\Documents\GotenbergClient;
use App\Services\Reports\ReportCatalog;
use App\Services\Reports\ReportPeriod;
use App\Services\Reports\ReportTable;
use App\Services\Shared\ActivityLogService;
use App\Support\Csv;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Reports page (Doc 07): its catalog, a report run with its filters, and
 * the same report as CSV or PDF (section 4) - each built from the source
 * records as it is asked for. Exports are logged.
 */
class ReportService
{
    /** The most periods a grouping by $grain may have. */
    private const MAX_PERIODS = ['day' => 366, 'week' => 400];

    public function __construct(
        protected ReportCatalog $reportCatalog,
        protected ReportPeriod $reportPeriod,
        protected ContactRepository $contactRepository,
        protected BusinessSettingRepository $businessSettingRepository,
        protected DocumentTemplates $documentTemplates,
        protected GotenbergClient $gotenbergClient,
    ) {}

    public function catalog(Workspace $workspace): array
    {
        return $this->reportCatalog->available($workspace);
    }

    /** $input is validated (see RunReportRequest). */
    public function run(Workspace $workspace, string $key, array $input): ReportTable
    {
        $entry = $this->reportCatalog->find($workspace, $key);

        return app($entry['class'])->build($workspace, $this->filters($workspace, $key, $input));
    }

    /** The report as CSV: its columns, its rows, then its totals - plain numbers, for a spreadsheet. */
    public function csv(Workspace $workspace, string $key, array $input): StreamedResponse
    {
        $table = $this->run($workspace, $key, $input);

        $this->logged($workspace, $table, 'CSV');

        $rows = [...$table->rows, ...($table->totals ? [$table->totals] : [])];

        return Csv::download("{$key}-{$this->reportPeriod->today($workspace)}.csv", array_column($table->columns, 'label'), array_map(
            fn (array $row) => array_map(fn (array $column) => (string) ($row[$column['key']] ?? ''), $table->columns),
            $rows,
        ));
    }

    /** @return array{0: string, 1: string} the report as a PDF - landscape when it is wide - and its file name */
    public function pdf(Workspace $workspace, string $key, array $input): array
    {
        $table    = $this->run($workspace, $key, $input);
        $template = $this->documentTemplates->settingsFor($workspace, DocumentType::Invoice, null);

        $this->logged($workspace, $table, 'PDF');

        $html = view('reports.report', [
            'table'    => $table,
            'business' => $this->businessSettingRepository->findFor($workspace)?->business_name ?: $workspace->name,
            'format'   => DocumentFormat::of($template),
            'color'    => $template['color'],
        ])->render();

        $footer = view('documents.footer', ['template' => ['footer_text' => null]])->render();

        return ["{$key}-{$this->reportPeriod->today($workspace)}.pdf", $this->gotenbergClient->pdf($html, count($table->columns) > 8 ? 'a4_landscape' : 'a4', $footer)];
    }

    /** The dates and filters a report is built with: the range, an as-of day, the grouping and the customer. */
    private function filters(Workspace $workspace, string $key, array $input): array
    {
        [$from, $to] = $this->reportPeriod->range($workspace, $input['range'] ?? null, $input['date_from'] ?? null, $input['date_to'] ?? null);

        $filters = [
            'from'        => $from,
            'to'          => $to,
            'as_of'       => $input['as_of'] ?? $this->reportPeriod->today($workspace),
            'group_by'    => $input['group_by'] ?? 'month',
            'location_id' => $input['location_id'] ?? null,
            'contact_id'  => $input['contact_id'] ?? null,
            'currency'    => $input['currency'] ?? null,
        ];

        $max = self::MAX_PERIODS[$filters['group_by']] ?? null;

        if ($key === 'sales-summary' && $max && count($this->reportPeriod->buckets($from, $to, $filters['group_by'])) >= $max) {
            throw ValidationException::withMessages(['group_by' => 'That range is too long to show '.($filters['group_by'] === 'day' ? 'day by day' : 'week by week').'. Group it by month instead.']);
        }

        if ($key === 'customer-statement') {
            $filters['contact'] = $filters['contact_id'] ? $this->contactRepository->findFor($workspace, (int) $filters['contact_id']) : null;

            if (! $filters['contact']) {
                throw ValidationException::withMessages(['contact_id' => 'Choose the customer to make the statement for.']);
            }
        }

        return $filters;
    }

    private function logged(Workspace $workspace, ReportTable $table, string $format): void
    {
        ActivityLogService::record('exported', "Exported the {$table->title} report as {$format}", $workspace, ['source' => 'api', 'period' => $table->period]);
    }
}
