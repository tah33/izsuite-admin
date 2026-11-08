<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Apps\AppDetailsResource;
use App\Services\User\Apps\AppsCenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** An app's own page in the Apps Center (Doc 13 section 6), for the business picked. */
class AppDetailsController extends Controller
{
    public function __construct(
        protected AppsCenter $appsCenter,
    ) {}

    public function __invoke(Request $request, string $family): JsonResponse
    {
        try {
            $workspace = $request->attributes->get('workspace');

            abort_if($workspace === null, 404, 'Create a workspace first.');

            return response()->json(['data' => new AppDetailsResource($this->appsCenter->details($workspace, $family))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
