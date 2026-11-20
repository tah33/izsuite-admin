<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Reports\PosReportChoicesResource;
use App\Services\Api\Reports\PosReportChoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The registers and cashiers POS's reports can be narrowed to (Doc 18 Phase H1). */
class PosReportChoiceController extends Controller
{
    public function __construct(
        protected PosReportChoiceService $choices,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => new PosReportChoicesResource($this->choices->choices($this->currentWorkspace($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
