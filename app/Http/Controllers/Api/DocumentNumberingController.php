<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Documents\SaveNumberingRequest;
use App\Http\Resources\Documents\DocumentNumberingResource;
use App\Http\Resources\Documents\NextNumberResource;
use App\Services\Api\Documents\NumberingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Settings > Documents > Numbering (Doc 11 section 21): how each kind of
 * document is numbered, addressed by its type - /numbering/invoice.
 */
class DocumentNumberingController extends Controller
{
    public function __construct(
        protected NumberingService $numberingService,
    ) {}

    /** Each kind of document of the apps the business has on, with the next number it would get. */
    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => DocumentNumberingResource::collection($this->numberingService->list($this->currentWorkspace($request))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveNumberingRequest $request, string $type): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $row       = $this->numberingService->save($workspace, $this->numberingService->type($workspace, $type), $request->validated());

            return response()->json([
                'message' => 'Numbering saved successfully.',
                'data'    => new DocumentNumberingResource($row),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** The next number the settings in the query would give, without saving them. */
    public function preview(SaveNumberingRequest $request, string $type): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $next      = $this->numberingService->preview($workspace, $this->numberingService->type($workspace, $type), $request->validated());

            return response()->json([
                'data' => new NextNumberResource($next),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
