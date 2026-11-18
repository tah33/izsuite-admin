<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Records\ActivityEntryResource;
use App\Services\Shared\RecordActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A sales document's, purchase's or expense's activity timeline (Doc 14
 * section 14.14). The route gives the app family; a sales route gives the
 * document's {kind} too.
 */
class RecordActivityController extends Controller
{
    public function __construct(
        protected RecordActivityService $recordActivityService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $entries = $this->recordActivityService->timeline(
                $this->currentWorkspace($request),
                $request->route('family'),
                $request->route('kind'),
                (int) $request->route('id'),
            );

            return response()->json([
                'data' => ActivityEntryResource::collection($entries),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
