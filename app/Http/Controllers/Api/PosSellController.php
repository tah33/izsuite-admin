<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\QuotePosCartRequest;
use App\Http\Requests\Api\Pos\ShowPosCustomerCreditRequest;
use App\Http\Resources\Pos\PosCustomerCreditResource;
use App\Http\Requests\Api\Pos\SearchPosItemsRequest;
use App\Http\Resources\Pos\PosCartQuoteResource;
use App\Http\Resources\Pos\PosSellItemsResource;
use App\Http\Resources\Pos\PosSellScreenResource;
use App\Services\Api\Pos\PosSellingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The POS selling screen (Doc 18 Phase C1), in the user's own open session:
 * what it starts with, the items found or scanned, and the cart priced and
 * totalled.
 */
class PosSellController extends Controller
{
    public function __construct(
        protected PosSellingService $sellingService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => new PosSellScreenResource($this->sellingService->screen($this->currentWorkspace($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function items(SearchPosItemsRequest $request): JsonResponse
    {
        try {
            return response()->json(['data' => new PosSellItemsResource($this->sellingService->items($this->currentWorkspace($request), $request->validated()))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Phase D3b: what credit the customer holds, to pay a sale with. */
    public function credit(ShowPosCustomerCreditRequest $request): JsonResponse
    {
        try {
            return response()->json(['data' => new PosCustomerCreditResource($this->sellingService->credit($this->currentWorkspace($request), $request->validated()))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function quote(QuotePosCartRequest $request): JsonResponse
    {
        try {
            return response()->json(['data' => new PosCartQuoteResource($this->sellingService->quote($this->currentWorkspace($request), $request->validated()))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
