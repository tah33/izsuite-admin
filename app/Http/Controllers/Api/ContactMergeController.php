<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Contacts\ContactMergeRequest;
use App\Http\Resources\Contacts\ContactMergeCandidateResource;
use App\Http\Resources\Contacts\ContactMergePreviewResource;
use App\Http\Resources\Contacts\ContactResource;
use App\Services\Api\Contacts\ContactMergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Merging another contact into the one in the address (Doc 17 Phase C3):
 * the contacts it may be another copy of, what a merge would do, and the
 * merge itself. See ContactMergeService.
 */
class ContactMergeController extends Controller
{
    public function __construct(
        protected ContactMergeService $mergeService,
    ) {}

    public function candidates(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'data' => ContactMergeCandidateResource::collection($this->mergeService->candidates($this->currentWorkspace($request), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function preview(ContactMergeRequest $request, int $id): JsonResponse
    {
        try {
            $preview = $this->mergeService->preview($this->currentWorkspace($request), $id, $request->integer('merged_id'));

            return response()->json([
                'data' => new ContactMergePreviewResource($preview),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(ContactMergeRequest $request, int $id): JsonResponse
    {
        try {
            $contact = $this->mergeService->merge($this->currentWorkspace($request), $request->user(), $id, $request->integer('merged_id'));

            return response()->json([
                'message' => 'Contacts merged.',
                'data'    => new ContactResource($contact),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
