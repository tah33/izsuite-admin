<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sales\ListPriceListsRequest;
use App\Http\Requests\Api\Sales\QuotePricesRequest;
use App\Http\Requests\Api\Sales\SavePriceListRequest;
use App\Http\Resources\Sales\PriceListListResource;
use App\Http\Resources\Sales\PriceListResource;
use App\Http\Resources\Sales\PriceQuoteResource;
use App\Services\Api\Sales\PriceListPricing;
use App\Services\Api\Sales\PriceListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Price lists (Doc 16 Phase F1): the Price Lists page, one list, a list made,
 * changed or deleted - and what a sales form's lines cost by them
 * (POST /sales/prices).
 */
class PriceListController extends Controller
{
    public function __construct(
        protected PriceListService $priceListService,
        protected PriceListPricing $priceListPricing,
    ) {}

    public function index(ListPriceListsRequest $request): JsonResponse
    {
        try {
            $lists = $this->priceListService->list($this->currentWorkspace($request), $request->validated(), requested_per_page());

            return response()->json([
                'data'       => PriceListListResource::collection($lists->items()),
                'pagination' => slim_pagination($lists),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'data' => new PriceListResource($this->priceListService->find($this->currentWorkspace($request), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SavePriceListRequest $request): JsonResponse
    {
        try {
            $list = $this->priceListService->create($this->currentWorkspace($request), $request->user(), $request->validated());

            return response()->json([
                'message' => "Price list \"{$list->name}\" added.",
                'data'    => new PriceListResource($list),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SavePriceListRequest $request, int $id): JsonResponse
    {
        try {
            $list = $this->priceListService->update($this->currentWorkspace($request), $id, $request->user(), $request->validated());

            return response()->json([
                'message' => 'Price list saved.',
                'data'    => new PriceListResource($list),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->priceListService->delete($this->currentWorkspace($request), $id);

            return response()->json([
                'message' => 'Price list deleted.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** What the form's lines cost by the lists that hold for them: by each line's key, only those a rule prices. */
    public function quote(QuotePricesRequest $request): JsonResponse
    {
        try {
            $data   = $request->validated();
            $prices = $this->priceListPricing->quote($this->currentWorkspace($request), $data, collect($data['lines'])->keyBy('key')->all());

            return response()->json([
                'data' => PriceQuoteResource::collection(collect($prices)->map(fn (array $price, string $key) => ['key' => $key, ...$price])->values()),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}