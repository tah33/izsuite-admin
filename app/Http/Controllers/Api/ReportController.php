<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Reports\RunReportRequest;
use App\Http\Resources\Reports\ReportCatalogResource;
use App\Http\Resources\Reports\ReportTableResource;
use App\Services\Api\Reports\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Reports page (Doc 07): the reports the business has, one report with
 * its filters - /reports/sales-summary?range=this_year - and the same as CSV
 * or PDF.
 */
class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => ReportCatalogResource::collection($this->reportService->catalog($this->currentWorkspace($request))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function show(RunReportRequest $request, string $report): JsonResponse
    {
        try {
            return response()->json([
                'data' => new ReportTableResource($this->reportService->run($this->currentWorkspace($request), $report, $request->validated())),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function export(RunReportRequest $request, string $report): StreamedResponse
    {
        try {
            return $this->reportService->csv($this->currentWorkspace($request), $report, $request->validated());

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function pdf(RunReportRequest $request, string $report): Response
    {
        try {
            [$filename, $pdf] = $this->reportService->pdf($this->currentWorkspace($request), $report, $request->validated());

            return $this->pdfResponse($filename, $pdf, true);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
