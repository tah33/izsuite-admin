<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sales\SaveCustomerCreditRequest;
use App\Http\Resources\Sales\CustomerCreditResource;
use App\Services\Api\Sales\CustomerCredit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A customer's credit control and exposure (Doc 16 section 16.21 - Phase G1), the full Sales app's: on their page, and in the sales form. */
class CustomerCreditController extends Controller
{
    public function __construct(
        protected CustomerCredit $customerCredit,
    ) {}

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'data' => new CustomerCreditResource($this->customerCredit->summary($this->currentWorkspace($request), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveCustomerCreditRequest $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'message' => 'Credit control saved.',
                'data'    => new CustomerCreditResource($this->customerCredit->save($this->currentWorkspace($request), $id, $request->validated())),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
