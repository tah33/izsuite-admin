<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\User\Apps\AppsCenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppsCenterController extends Controller
{
    public function __construct(
        protected AppsCenter $appsCenter,
    ) {}

    /**
     * Every listed app family by category, with each edition's state for the
     * business.
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $workspace = $request->attributes->get('workspace');

            abort_if($workspace === null, 404, 'Create a workspace first.');

            return response()->json(['data' => $this->appsCenter->for($workspace)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
