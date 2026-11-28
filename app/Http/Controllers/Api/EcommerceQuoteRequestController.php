<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\ListEcommerceQuoteRequestsRequest;
use App\Http\Resources\Ecommerce\EcommerceMessageListResource;
use App\Http\Resources\Ecommerce\EcommerceQuoteRequestListResource;
use App\Http\Resources\Ecommerce\EcommerceQuoteRequestResource;
use App\Models\User\EcommerceQuoteRequest;
use App\Services\Api\Ecommerce\EcommerceQuoteRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The business's requests for a quotation (Doc 19 Phase H4a), under View Orders: the Quote Requests page - and (Phase H4e) the Messages page - and one request or message whole. */
class EcommerceQuoteRequestController extends Controller
{
    public function __construct(
        protected EcommerceQuoteRequestService $requests,
    ) {}

    public function index(ListEcommerceQuoteRequestsRequest $request): JsonResponse
    {
        try {
            $page     = $this->requests->list($this->currentWorkspace($request), $request->user(), $request->validated(), requested_per_page());
            $resource = $request->validated('kind') === EcommerceQuoteRequest::KIND_MESSAGE ? EcommerceMessageListResource::class : EcommerceQuoteRequestListResource::class;

            return response()->json(['data' => $resource::collection($page->items()), 'pagination' => slim_pagination($page)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json(['data' => new EcommerceQuoteRequestResource($this->requests->find($this->currentWorkspace($request), $request->user(), $id))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
