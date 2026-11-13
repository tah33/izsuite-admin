<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Support\ListSupportCasesRequest;
use App\Http\Requests\Api\Support\ReplySupportCaseRequest;
use App\Http\Requests\Api\Support\StoreSupportCaseRequest;
use App\Http\Resources\Support\SupportCaseDetailResource;
use App\Http\Resources\Support\SupportCaseResource;
use App\Services\Api\Support\SupportCaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The business's support cases in the app (Doc 15 section 15.22): the list,
 * opening one, one with its messages, and writing on it. Open to everyone in
 * the business, whatever its status, as it is how they reach izSuite.
 */
class SupportCaseController extends Controller
{
    public function __construct(
        protected SupportCaseService $cases,
    ) {}

    public function index(ListSupportCasesRequest $request): JsonResponse
    {
        try {
            $page = $this->cases->page($this->currentWorkspace($request), $request->user(), $request->validated('status'), (int) ($request->validated('per_page') ?? 15));

            return response()->json([
                'data'       => SupportCaseResource::collection($page->getCollection()),
                'pagination' => slim_pagination($page),
            ]);
        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(StoreSupportCaseRequest $request): JsonResponse
    {
        try {
            $case = $this->cases->open($this->currentWorkspace($request), $request->user(), $request->validated('subject'), $request->validated('message'));

            return response()->json([
                'message' => "Case #{$case->id} is open. izSuite support will reply here, and by e-mail.",
                'data'    => new SupportCaseDetailResource($case),
            ], 201);
        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'data' => new SupportCaseDetailResource($this->cases->find($this->currentWorkspace($request), $request->user(), $id)),
            ]);
        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function reply(ReplySupportCaseRequest $request, int $id): JsonResponse
    {
        try {
            $case = $this->cases->reply($this->currentWorkspace($request), $request->user(), $id, $request->validated('message'));

            return response()->json([
                'message' => 'Sent.',
                'data'    => new SupportCaseDetailResource($case),
            ]);
        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
