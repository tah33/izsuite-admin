<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\SubmitStoreQuoteRequestRequest;
use App\Http\Resources\Ecommerce\StoreQuoteRequestResource;
use App\Services\Api\Ecommerce\StorefrontQuoteRequests;
use Illuminate\Http\JsonResponse;

/** A storefront's Request a Quote (Doc 19 Phase H4a): a request for a quotation sent, by a guest or a signed-in customer. */
class StoreQuoteRequestController extends Controller
{
    public function __construct(
        protected StorefrontQuoteRequests $requests,
    ) {}

    public function store(SubmitStoreQuoteRequestRequest $request, string $code): JsonResponse
    {
        try {
            $quote = $this->requests->submit($code, $request->validated(), $request->attributes->get('storeCustomer'));

            return response()->json(['message' => "Your request is sent: we will reply to {$quote->customer_email}.", 'data' => new StoreQuoteRequestResource($quote)], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
