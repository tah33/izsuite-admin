<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Imports\ListImportRowsRequest;
use App\Http\Requests\Api\Imports\MapImportRequest;
use App\Http\Requests\Api\Imports\RunImportRequest;
use App\Http\Requests\Api\Imports\StartImportRequest;
use App\Http\Resources\Imports\ImportResource;
use App\Http\Resources\Imports\ImportRowResource;
use App\Services\Api\Transfer\ExportService;
use App\Services\Api\Transfer\ImportService;
use App\Services\Shared\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Importing contacts or items from CSV (Doc 03 section 15, Doc 04 section 17):
 * a template to fill in, then upload, match the columns, see the check, and
 * add. Checking and adding run in the background; the app asks for the
 * import again until it moves on.
 */
class ImportController extends Controller
{
    public function __construct(
        protected ImportService $importService,
        protected ExportService $exportService,
    ) {}

    /** The import's columns and an example row, as a CSV to fill in. */
    public function template(Request $request, string $kind): StreamedResponse
    {
        try {
            return $this->exportService->template($this->currentWorkspace($request), $kind);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(StartImportRequest $request): JsonResponse
    {
        try {
            $import = $this->importService->start($this->currentWorkspace($request), $request->user(), $request->validated('kind'), $request->file('file'));

            // Reading a file changes nothing yet: the import is logged once it adds rows.
            ActivityLogService::skipForThisRequest();

            return response()->json([
                'message' => 'File read. Match its columns to fields.',
                'data'    => new ImportResource($import),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'data' => new ImportResource($this->importService->find($this->currentWorkspace($request), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function map(MapImportRequest $request, int $id): JsonResponse
    {
        try {
            $import = $this->importService->map($this->importService->find($this->currentWorkspace($request), $id), $request->validated('mapping'));

            ActivityLogService::skipForThisRequest();

            return response()->json([
                'message' => 'Checking the rows.',
                'data'    => new ImportResource($import),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function rows(ListImportRowsRequest $request, int $id): JsonResponse
    {
        try {
            $rows = $this->importService->rows(
                $this->importService->find($this->currentWorkspace($request), $id),
                $request->validated('show') ?? 'all',
                requested_per_page(),
            );

            return response()->json([
                'data'       => ImportRowResource::collection($rows->items()),
                'pagination' => slim_pagination($rows),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function run(RunImportRequest $request, int $id): JsonResponse
    {
        try {
            $import = $this->importService->run($this->importService->find($this->currentWorkspace($request), $id), $request->validated('include') ?? []);

            // Logged once it is done, with what it added.
            ActivityLogService::skipForThisRequest();

            return response()->json([
                'message' => 'Adding the rows.',
                'data'    => new ImportResource($import),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->importService->discard($this->importService->find($this->currentWorkspace($request), $id));

            ActivityLogService::skipForThisRequest();

            return response()->json([
                'message' => 'Import dropped.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
