<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sales\CalculateSalesRequest;
use App\Http\Requests\Api\Sales\SalesDefaultsRequest;
use App\Http\Resources\Expenses\ExpenseDefaultsResource;
use App\Http\Resources\Sales\SalesTotalsResource;
use App\Services\Api\Expenses\ExpenseFormService;
use Illuminate\Http\JsonResponse;

/**
 * What the expense form asks the server (Doc 10 sections 5 and 6): what a new
 * expense starts with - following the payee, the date or the currency chosen
 * - and its totals as it is filled in, from the same arithmetic as a sale's.
 */
class ExpenseFormController extends Controller
{
    public function __construct(
        protected ExpenseFormService $expenseFormService,
    ) {}

    public function defaults(SalesDefaultsRequest $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => new ExpenseDefaultsResource($this->expenseFormService->defaults($this->currentWorkspace($request), $request->validated())),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function calculate(CalculateSalesRequest $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => new SalesTotalsResource($this->expenseFormService->calculate($this->currentWorkspace($request), $request->validated())),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
