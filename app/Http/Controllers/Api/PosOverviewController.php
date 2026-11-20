<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Pos\PosOverviewResource;
use App\Services\Api\Pos\PosOverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The POS Overview (Doc 18 Phase G2): POS today. */
class PosOverviewController extends Controller
{
    public function __construct(
        protected PosOverviewService $overview,
    ) {}

    public function show(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => new PosOverviewResource($this->overview->today($this->currentWorkspace($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
