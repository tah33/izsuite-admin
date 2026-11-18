<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Apps\DeactivateAppRequest;
use App\Http\Resources\Apps\DeactivationImpactResource;
use App\Http\Resources\Navigation\NavigationResource;
use App\Models\User\Workspace;
use App\Services\Api\Apps\AppActivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Turning apps on and off from the Apps Center. Each answer carries the new
 * navigation, so the sidebar updates without a second request. Before an
 * app goes off, its impact says what will (Doc 13 section 8).
 */
class AppActivationController extends Controller
{
    public function __construct(
        protected AppActivationService $appActivationService,
    ) {}

    public function activate(Request $request, string $appKey): JsonResponse
    {
        try {
            $result = $this->appActivationService->activate($this->workspace($request), $appKey, $request->user());

            return response()->json([
                'message' => $result['message'],
                'data'    => new NavigationResource($result['navigation']),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function deactivate(DeactivateAppRequest $request, string $appKey): JsonResponse
    {
        try {
            $result = $this->appActivationService->deactivate($this->workspace($request), $appKey, $request->user(), $request->boolean('with_dependents'));

            return response()->json([
                'message' => $result['message'],
                'data'    => new NavigationResource($result['navigation']),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function impact(Request $request, string $appKey): JsonResponse
    {
        try {
            return response()->json([
                'data' => new DeactivationImpactResource($this->appActivationService->impact($this->workspace($request), $appKey)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    private function workspace(Request $request): Workspace
    {
        $workspace = $request->attributes->get('workspace');

        abort_if($workspace === null, 404, 'Create a workspace first.');

        return $workspace;
    }
}
