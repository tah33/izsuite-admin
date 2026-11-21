<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ecommerce\CloseStoreAccountRequest;
use App\Http\Requests\Api\Ecommerce\SaveStoreAccountRequest;
use App\Http\Requests\Api\Ecommerce\StoreAccountPasswordRequest;
use App\Http\Resources\Ecommerce\StoreAccountResource;
use App\Services\Api\Ecommerce\StorefrontCustomerAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A signed-in storefront customer's own account (Doc 19 Phase E1): seen, its profile saved - from Phase E3 its language and marketing too - a new password, and closed. */
class StoreAccountController extends Controller
{
    public function __construct(
        protected StorefrontCustomerAccount $account,
    ) {}

    public function show(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => new StoreAccountResource($this->account->view($request->attributes->get('storeCustomer')))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveStoreAccountRequest $request): JsonResponse
    {
        try {
            $account = $this->account->update($request->attributes->get('storeCustomer'), $request->validated());

            return response()->json(['message' => 'Your details are saved.', 'data' => new StoreAccountResource($account)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function password(StoreAccountPasswordRequest $request): JsonResponse
    {
        try {
            $this->account->changePassword($request->attributes->get('storeCustomerToken'), $request->validated('current_password'), $request->validated('password'));

            return response()->json(['message' => 'Your new password is saved. Your other sign-ins are signed out.']);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function close(CloseStoreAccountRequest $request): JsonResponse
    {
        try {
            $this->account->close($request->attributes->get('storeCustomer'), $request->validated('password'));

            return response()->json(['message' => 'Your account is closed.']);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
