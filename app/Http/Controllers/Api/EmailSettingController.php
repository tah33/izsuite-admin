<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Documents\SaveEmailSettingRequest;
use App\Http\Resources\Documents\EmailSettingResource;
use App\Services\Api\Documents\EmailSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Settings > Email (Doc 11 sections 15 and 22): how the business's document emails go out, and what they say. */
class EmailSettingController extends Controller
{
    public function __construct(
        protected EmailSettingService $emailSettingService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => new EmailSettingResource($this->emailSettingService->get($this->currentWorkspace($request))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveEmailSettingRequest $request): JsonResponse
    {
        try {
            return response()->json([
                'message' => 'Email settings saved successfully.',
                'data'    => new EmailSettingResource($this->emailSettingService->save($this->currentWorkspace($request), $request->validated())),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
