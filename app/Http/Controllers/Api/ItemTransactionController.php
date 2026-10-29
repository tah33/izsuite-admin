<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Items\ItemTransactionResource;
use App\Services\Api\Items\ItemTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** An item's related transactions (Doc 04 sections 16 and 17), a page at a time. */
class ItemTransactionController extends Controller
{
    public function __construct(
        protected ItemTransactionService $itemTransactionService,
    ) {}

    public function index(Request $request, int $id): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);
            $lines     = $this->itemTransactionService->list($workspace, $id, requested_per_page(10));
            $today     = $this->itemTransactionService->today($workspace);

            return response()->json([
                'data'       => array_map(fn ($line) => new ItemTransactionResource($line, $today), $lines->items()),
                'pagination' => slim_pagination($lines),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
