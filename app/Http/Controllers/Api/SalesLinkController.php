<?php

namespace App\Http\Controllers\Api;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Sales\DocumentLinkResource;
use App\Services\Api\Sales\SalesLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Share Link (Doc 06 section 9): a sales document's secure link -
 * /sales/invoices/12/link - made or given again, and revoked.
 */
class SalesLinkController extends Controller
{
    public function __construct(
        protected SalesLinkService $salesLinkService,
    ) {}

    public function store(Request $request, string $kind, int $id): JsonResponse
    {
        try {
            $link = $this->salesLinkService->share($this->currentWorkspace($request), DocumentType::fromKind($kind), $id, $request->user());

            return response()->json([
                'message' => 'Secure link ready to share.',
                'data'    => new DocumentLinkResource($link),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, string $kind, int $id): JsonResponse
    {
        try {
            $this->salesLinkService->revoke($this->currentWorkspace($request), DocumentType::fromKind($kind), $id);

            return response()->json([
                'message' => 'Link revoked. It no longer opens the document.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
