<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sales\SaveCreditNoteRequest;
use App\Http\Resources\Sales\CreditNoteDefaultsResource;
use App\Http\Resources\Sales\SalesDocumentResource;
use App\Http\Resources\Sales\SalesTotalsResource;
use App\Services\Api\Sales\CreditNoteService;
use App\Services\Api\Sales\SalesDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Making and changing credit notes (Doc 08 section 15). One is made from its
 * issued invoice - /sales/invoices/{id}/credit-notes, with the form's
 * starting point and its totals preview beside it - and a draft changed at
 * /sales/credit-notes/{id}. Listing, showing, deleting a draft, issuing and
 * voiding go through the routes every sales document shares.
 */
class CreditNoteController extends Controller
{
    public function __construct(
        protected CreditNoteService $creditNoteService,
        protected SalesDocumentService $salesDocumentService,
    ) {}

    public function defaults(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'data' => new CreditNoteDefaultsResource($this->creditNoteService->defaults($this->currentWorkspace($request), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function calculate(SaveCreditNoteRequest $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'data' => new SalesTotalsResource($this->creditNoteService->calculate($this->currentWorkspace($request), $id, $request->validated('lines'))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveCreditNoteRequest $request, int $id): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $note      = $this->creditNoteService->create($workspace, $id, $request->user(), $request->validated());

            return response()->json([
                'message' => 'Draft credit note saved.',
                'data'    => new SalesDocumentResource($note, $this->salesDocumentService->today($workspace)),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveCreditNoteRequest $request, int $id): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $note      = $this->creditNoteService->update($workspace, $id, $request->user(), $request->validated());

            return response()->json([
                'message' => 'Draft credit note updated.',
                'data'    => new SalesDocumentResource($note, $this->salesDocumentService->today($workspace)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
