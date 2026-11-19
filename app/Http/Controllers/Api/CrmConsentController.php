<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Crm\RecordMarketingConsentRequest;
use App\Http\Requests\Api\Crm\WithdrawMarketingConsentRequest;
use App\Http\Resources\Crm\MarketingConsentsResource;
use App\Services\Api\Crm\CrmConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A lead's or contact's marketing consent (Doc 17 Phase I3) - the route's
 * kind says which: its answers, one recorded, a consent given withdrawn.
 */
class CrmConsentController extends Controller
{
    public function __construct(
        protected CrmConsentService $consentService,
    ) {}

    public function index(Request $request, int $id): JsonResponse
    {
        try {
            $consent = $this->consentService->index($this->currentWorkspace($request), $request->user(), $request->route('kind'), $id);

            return response()->json(['data' => new MarketingConsentsResource($consent)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(RecordMarketingConsentRequest $request, int $id): JsonResponse
    {
        try {
            $consent = $this->consentService->record($this->currentWorkspace($request), $request->user(), $request->route('kind'), $id, $request->validated());

            return response()->json(['message' => 'Consent recorded.', 'data' => new MarketingConsentsResource($consent)], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function withdraw(WithdrawMarketingConsentRequest $request, int $id, int $consentId): JsonResponse
    {
        try {
            $consent = $this->consentService->withdraw($this->currentWorkspace($request), $request->user(), $request->route('kind'), $id, $consentId, $request->validated());

            return response()->json(['message' => 'Consent withdrawn.', 'data' => new MarketingConsentsResource($consent)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
