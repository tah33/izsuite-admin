<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\SaveStoreAddressRequest;
use App\Http\Resources\Ecommerce\StoreAddressResource;
use App\Services\Api\Ecommerce\StorefrontCustomerAddresses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A signed-in storefront customer's saved addresses (Doc 19 Phase E2): listed, added, changed and deleted. */
class StoreAccountAddressController extends Controller
{
    public function __construct(
        protected StorefrontCustomerAddresses $addresses,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => StoreAddressResource::collection($this->addresses->list($request->attributes->get('storeCustomer')))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveStoreAddressRequest $request): JsonResponse
    {
        try {
            $address = $this->addresses->add($request->attributes->get('storeCustomer'), $request->validated());

            return response()->json(['message' => 'Address saved.', 'data' => new StoreAddressResource($address)], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveStoreAddressRequest $request, string $code, string $address): JsonResponse
    {
        try {
            $saved = $this->addresses->update($request->attributes->get('storeCustomer'), (int) $address, $request->validated());

            return response()->json(['message' => 'Address saved.', 'data' => new StoreAddressResource($saved)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, string $code, string $address): JsonResponse
    {
        try {
            $this->addresses->delete($request->attributes->get('storeCustomer'), (int) $address);

            return response()->json(['message' => 'Address deleted.']);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
