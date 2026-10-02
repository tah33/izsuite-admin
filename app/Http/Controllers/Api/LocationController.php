<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Locations\SaveLocationRequest;
use App\Http\Resources\Locations\LocationResource;
use App\Services\Api\Locations\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function __construct(
        protected LocationService $locationService,
    ) {}

    /**
     * The signed-in account's locations, alphabetical by name. Not paginated:
     * an account has a handful of branches and warehouses, and the settings
     * page lists all of them.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => LocationResource::collection($this->locationService->list($request->user())),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveLocationRequest $request): JsonResponse
    {
        try {
            $location = $this->locationService->create($request->user(), $request->validated());

            return response()->json([
                'message' => 'Location added successfully.',
                'data'    => new LocationResource($location),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveLocationRequest $request, int $id): JsonResponse
    {
        try {
            $location = $this->locationService->update($request->user(), $id, $request->validated());

            return response()->json([
                'message' => 'Location updated successfully.',
                'data'    => new LocationResource($location),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->locationService->delete($request->user(), $id);

            return response()->json([
                'message' => 'Location deleted successfully.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
