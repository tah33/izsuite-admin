<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Records\SaveCommentRequest;
use App\Http\Resources\Records\CommentResource;
use App\Http\Resources\Records\MentionablePersonResource;
use App\Services\Shared\RecordCommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Comments on a sales document, purchase or expense (Doc 14 section 14.14):
 * the list, who a comment may mention, writing one and deleting one's own.
 * The route gives the app family; a sales route gives the document's {kind}
 * too.
 */
class RecordCommentController extends Controller
{
    public function __construct(
        protected RecordCommentService $recordCommentService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            [$family, $kind, $id] = $this->record($request);

            return response()->json([
                'data' => CommentResource::collection($this->recordCommentService->list($this->currentWorkspace($request), $family, $kind, $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function people(Request $request): JsonResponse
    {
        try {
            [$family, $kind, $id] = $this->record($request);

            return response()->json([
                'data' => MentionablePersonResource::collection($this->recordCommentService->people($this->currentWorkspace($request), $family, $kind, $id, $request->user())),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveCommentRequest $request): JsonResponse
    {
        try {
            [$family, $kind, $id] = $this->record($request);

            $comment = $this->recordCommentService->add($this->currentWorkspace($request), $family, $kind, $id, $request->user(), $request->validated());

            return response()->json([
                'message' => 'Comment added.',
                'data'    => new CommentResource($comment),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request): JsonResponse
    {
        try {
            [$family, $kind, $id] = $this->record($request);

            $this->recordCommentService->remove($this->currentWorkspace($request), $family, $kind, $id, (int) $request->route('commentId'), $request->user());

            return response()->json(['message' => 'Comment deleted.']);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** @return array{0: string, 1: string|null, 2: int} the app family, a sales document's {kind}, the record's id */
    private function record(Request $request): array
    {
        return [$request->route('family'), $request->route('kind'), (int) $request->route('id')];
    }
}
