<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Crm\SendCrmEmailRequest;
use App\Http\Resources\Crm\CrmEmailDraftResource;
use App\Http\Resources\Crm\CrmEmailResource;
use App\Services\Api\Crm\CrmEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * E-mail from a lead, an opportunity or a contact (Doc 17 Phase F3a): what
 * its form starts with, and sending it.
 */
class CrmEmailController extends Controller
{
    public function __construct(
        protected CrmEmailService $emailService,
    ) {}

    public function draft(Request $request, string $kind, int $id): JsonResponse
    {
        try {
            return response()->json([
                'data' => new CrmEmailDraftResource($this->emailService->draft($this->currentWorkspace($request), $request->user(), $kind, $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SendCrmEmailRequest $request): JsonResponse
    {
        try {
            $email = $this->emailService->send($this->currentWorkspace($request), $request->user(), $request->validated());

            return response()->json(['message' => "E-mail to {$email->to} is on its way.", 'data' => new CrmEmailResource($email)], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
