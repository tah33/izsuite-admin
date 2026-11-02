<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Locations\AssignLocationUsersRequest;
use App\Http\Resources\Locations\LocationResource;
use App\Services\Api\Locations\LocationUserService;
use Illuminate\Http\JsonResponse;

/** A location's Assign Users (Doc 12 section 18). */
class LocationUserController extends Controller
{
    public function __construct(
        protected LocationUserService $locationUserService,
    ) {}

    public function update(AssignLocationUsersRequest $request, int $id): JsonResponse
    {
        try {
            $location = $this->locationUserService->assign($this->currentWorkspace($request), $request->user(), $id, $request->validated('member_ids'));

            return response()->json([
                'message' => 'Users assigned successfully.',
                'data'    => new LocationResource($location),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
