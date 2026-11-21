<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\SaveEcommercePaymentMethodRequest;
use App\Http\Resources\Ecommerce\EcommercePaymentMethodResource;
use App\Http\Resources\Ecommerce\EcommercePaymentMethodsResource;
use App\Services\Api\Ecommerce\EcommercePaymentMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Settings → eCommerce's payment methods without a gateway (Doc 19 Phase D2), under Manage Payment Methods. */
class EcommercePaymentMethodController extends Controller
{
    public function __construct(
        protected EcommercePaymentMethodService $methodService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => new EcommercePaymentMethodsResource($this->methodService->show($this->currentWorkspace($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveEcommercePaymentMethodRequest $request): JsonResponse
    {
        try {
            $method = $this->methodService->add($this->currentWorkspace($request), $request->validated());

            return response()->json(['message' => 'Payment method added.', 'data' => new EcommercePaymentMethodResource($method)], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveEcommercePaymentMethodRequest $request, int $id): JsonResponse
    {
        try {
            $method = $this->methodService->update($this->currentWorkspace($request), $id, $request->validated());

            return response()->json(['message' => 'Payment method saved.', 'data' => new EcommercePaymentMethodResource($method)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->methodService->delete($this->currentWorkspace($request), $id);

            return response()->json(['message' => 'Payment method deleted.']);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
