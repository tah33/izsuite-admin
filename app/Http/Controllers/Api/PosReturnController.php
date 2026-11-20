<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\ListPosReturnsRequest;
use App\Http\Requests\Api\Pos\LookupPosReturnRequest;
use App\Http\Requests\Api\Pos\QuotePosReturnRequest;
use App\Http\Requests\Api\Pos\RecordPosReturnRequest;
use App\Http\Resources\Pos\PosReturnListResource;
use App\Http\Resources\Pos\PosReturnQuoteResource;
use App\Http\Resources\Pos\PosReturnResource;
use App\Http\Resources\Pos\PosReturnSaleResource;
use App\Services\Api\Pos\PosRecordLists;
use App\Services\Api\Pos\PosReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** POS returns (Doc 18 Phase E1): the sale a return starts from, what it would credit, and the return recorded with its refund. */
class PosReturnController extends Controller
{
    public function __construct(
        protected PosReturnService $returnService,
        protected PosRecordLists $lists,
    ) {}

    /** Phase G1: the page of returns. */
    public function index(ListPosReturnsRequest $request): JsonResponse
    {
        try {
            $page = $this->lists->returns($this->currentWorkspace($request), $request->validated(), requested_per_page());

            return response()->json(['data' => PosReturnListResource::collection($page->items()), 'pagination' => slim_pagination($page)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function lookup(LookupPosReturnRequest $request): JsonResponse
    {
        try {
            return response()->json(['data' => new PosReturnSaleResource($this->returnService->lookup($this->currentWorkspace($request), $request->validated('receipt')))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function quote(QuotePosReturnRequest $request): JsonResponse
    {
        try {
            return response()->json(['data' => new PosReturnQuoteResource($this->returnService->quote($this->currentWorkspace($request), $request->validated()))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Phase E2: its refund receipt - ?download=1 to save it. */
    public function receipt(Request $request, int $id): Response
    {
        try {
            [$filename, $pdf] = $this->returnService->receipt($this->currentWorkspace($request), $id);

            return $this->pdfResponse($filename, $pdf, $request->boolean('download'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(RecordPosReturnRequest $request): JsonResponse
    {
        try {
            $return = $this->returnService->record($this->currentWorkspace($request), $request->validated());

            return response()->json([
                'message' => "Return {$return->number} recorded.",
                'data'    => new PosReturnResource($return),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
