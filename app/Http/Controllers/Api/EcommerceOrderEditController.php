<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\UpdateEcommerceOrderCustomerRequest;
use App\Http\Requests\Api\Ecommerce\UpdateEcommerceOrderDeliveryRequest;
use App\Http\Resources\Ecommerce\EcommerceOrderDeliveryOptionsResource;
use App\Http\Resources\Ecommerce\EcommerceOrderResource;
use App\Services\Api\Ecommerce\EcommerceOrderDeliveryEdit;
use App\Services\Api\Ecommerce\EcommerceOrderEdits;
use Illuminate\Http\JsonResponse;

/**
 * An online order edited before payment (Doc 19 Phase F5a), under Edit
 * Orders: its customer's details, and its addresses and delivery - with
 * what an address would offer, asked as they are typed.
 */
class EcommerceOrderEditController extends Controller
{
    public function __construct(
        protected EcommerceOrderEdits $edits,
        protected EcommerceOrderDeliveryEdit $delivery,
    ) {}

    public function customer(UpdateEcommerceOrderCustomerRequest $request, int $id): JsonResponse
    {
        try {
            $order = $this->edits->customer($this->currentWorkspace($request), $id, $request->validated());

            return response()->json(['message' => "The customer details of order {$order->number} are saved.", 'data' => new EcommerceOrderResource($order)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function quote(UpdateEcommerceOrderDeliveryRequest $request, int $id): JsonResponse
    {
        try {
            $options = $this->delivery->quote($this->currentWorkspace($request), $id, $request->validated());

            return response()->json(['data' => new EcommerceOrderDeliveryOptionsResource($options)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function delivery(UpdateEcommerceOrderDeliveryRequest $request, int $id): JsonResponse
    {
        try {
            $order = $this->delivery->save($this->currentWorkspace($request), $request->user(), $id, $request->validated());

            return response()->json(['message' => "The addresses and delivery of order {$order->number} are saved.", 'data' => new EcommerceOrderResource($order)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
