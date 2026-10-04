<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Staff\StaffResource;
use App\Services\Api\Staff\StaffService;
use Illuminate\Http\JsonResponse;

class StaffController extends Controller
{
    public function __construct(
        protected StaffService $staffService,
    ) {}

    /**
     * The active staff accounts, alphabetical, as id and name - what the
     * Workspace form's Staff dropdown needs. Needs a signed-in account: who
     * works for the platform is not for anonymous visitors.
     */
    public function index(): JsonResponse
    {
        try {
            return response()->json([
                'data' => StaffResource::collection($this->staffService->list()),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
