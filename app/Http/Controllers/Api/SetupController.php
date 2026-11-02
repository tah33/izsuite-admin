<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setup\ReachSetupStepRequest;
use App\Http\Resources\Setup\SetupResource;
use App\Services\Api\Setup\SetupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The First-Time Business Setup wizard (Doc 11 section 3): where the
 * business's setup stands, moving it on a step, and finishing it.
 */
class SetupController extends Controller
{
    public function __construct(
        protected SetupService $setupService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => new SetupResource($this->setupService->show($this->currentWorkspace($request))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(ReachSetupStepRequest $request): JsonResponse
    {
        try {
            $setup = $this->setupService->reach($this->currentWorkspace($request), $request->validated('step'));

            return response()->json([
                'message' => 'Setup progress saved.',
                'data'    => new SetupResource($setup),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function complete(Request $request): JsonResponse
    {
        try {
            $setup = $this->setupService->complete($this->currentWorkspace($request));

            return response()->json([
                'message' => 'Your business is set up.',
                'data'    => new SetupResource($setup),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
