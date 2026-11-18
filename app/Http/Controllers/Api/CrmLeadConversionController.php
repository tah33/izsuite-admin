<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Crm\ConvertCrmLeadRequest;
use App\Http\Resources\Crm\CrmLeadConversionResource;
use App\Http\Resources\Crm\CrmLeadResource;
use App\Services\Api\Crm\CrmLeadConversion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Converting a qualified lead into a contact and an opportunity (Doc 17 Phase C1): what it offers, and doing it. */
class CrmLeadConversionController extends Controller
{
    public function __construct(
        protected CrmLeadConversion $crmLeadConversion,
    ) {}

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'data' => new CrmLeadConversionResource($this->crmLeadConversion->preview($this->currentWorkspace($request), $request->user(), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(ConvertCrmLeadRequest $request, int $id): JsonResponse
    {
        try {
            $lead = $this->crmLeadConversion->convert($this->currentWorkspace($request), $request->user(), $id, $request->validated());

            return response()->json([
                'message' => "The lead is converted: opportunity {$lead->opportunity->name} for {$lead->contact->name}.",
                'data'    => new CrmLeadResource($lead),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
