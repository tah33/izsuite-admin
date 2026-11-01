<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Expenses\SaveExpenseSettingsRequest;
use App\Http\Resources\Expenses\ExpenseSettingsResource;
use App\Services\Api\Expenses\ExpenseSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Settings > Expenses (Doc 11 section 17) - there while Expenses Lite or Expenses is on. */
class ExpenseSettingController extends Controller
{
    public function __construct(
        protected ExpenseSettingService $expenseSettingService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => new ExpenseSettingsResource($this->expenseSettingService->settings($this->currentWorkspace($request))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveExpenseSettingsRequest $request): JsonResponse
    {
        try {
            $settings = $this->expenseSettingService->save($this->currentWorkspace($request), $request->validated());

            return response()->json([
                'message' => 'Expense settings saved successfully.',
                'data'    => new ExpenseSettingsResource($settings),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
