<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\SavePickupPointRequest;
use App\Http\Requests\Api\Ecommerce\SaveShippingMethodRequest;
use App\Http\Requests\Api\Ecommerce\SaveShippingZoneRequest;
use App\Http\Resources\Ecommerce\EcommerceShippingResource;
use App\Http\Resources\Ecommerce\PickupLocationResource;
use App\Http\Resources\Ecommerce\ShippingMethodResource;
use App\Http\Resources\Ecommerce\ShippingZoneResource;
use App\Services\Api\Ecommerce\ShippingSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Settings → eCommerce's shipping and pickup (Doc 19 Phase D1), under Manage
 * Shipping: the zones and their methods added, changed and deleted, and each
 * location switched on or off for pickup.
 */
class EcommerceShippingController extends Controller
{
    public function __construct(
        protected ShippingSettingService $shippingService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => new EcommerceShippingResource($this->shippingService->show($this->currentWorkspace($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function storeZone(SaveShippingZoneRequest $request): JsonResponse
    {
        try {
            $zone = $this->shippingService->addZone($this->currentWorkspace($request), $request->validated());

            return response()->json(['message' => 'Shipping zone added.', 'data' => new ShippingZoneResource($zone)], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function updateZone(SaveShippingZoneRequest $request, int $id): JsonResponse
    {
        try {
            $zone = $this->shippingService->updateZone($this->currentWorkspace($request), $id, $request->validated());

            return response()->json(['message' => 'Shipping zone saved.', 'data' => new ShippingZoneResource($zone)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroyZone(Request $request, int $id): JsonResponse
    {
        try {
            $this->shippingService->deleteZone($this->currentWorkspace($request), $id);

            return response()->json(['message' => 'Shipping zone deleted.']);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function storeMethod(SaveShippingMethodRequest $request): JsonResponse
    {
        try {
            $method = $this->shippingService->addMethod($this->currentWorkspace($request), $request->validated());

            return response()->json(['message' => 'Shipping method added.', 'data' => new ShippingMethodResource($method)], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function updateMethod(SaveShippingMethodRequest $request, int $id): JsonResponse
    {
        try {
            $method = $this->shippingService->updateMethod($this->currentWorkspace($request), $id, $request->validated());

            return response()->json(['message' => 'Shipping method saved.', 'data' => new ShippingMethodResource($method)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroyMethod(Request $request, int $id): JsonResponse
    {
        try {
            $this->shippingService->deleteMethod($this->currentWorkspace($request), $id);

            return response()->json(['message' => 'Shipping method deleted.']);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function updatePickup(SavePickupPointRequest $request, int $location): JsonResponse
    {
        try {
            $saved = $this->shippingService->savePickup($this->currentWorkspace($request), $location, $request->validated());

            return response()->json(['message' => $saved->pickupPoint->is_active ? 'Pickup saved.' : 'Pickup switched off.', 'data' => new PickupLocationResource($saved)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
