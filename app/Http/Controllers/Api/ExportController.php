<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Contacts\ListContactRequest;
use App\Http\Requests\Api\Items\ListItemRequest;
use App\Services\Api\Transfer\ExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The Contacts and Items lists as CSV files (Doc 03 section 15, Doc 04
 * section 17): what the list shows with the same search and filters, whole -
 * not a page at a time.
 */
class ExportController extends Controller
{
    public function __construct(
        protected ExportService $exportService,
    ) {}

    public function contacts(ListContactRequest $request): StreamedResponse
    {
        try {
            return $this->exportService->download($this->currentWorkspace($request), 'contacts', $request->only(['search', 'role', 'type', 'status']));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function items(ListItemRequest $request): StreamedResponse
    {
        try {
            return $this->exportService->download($this->currentWorkspace($request), 'items', $request->only(['search', 'type', 'usage', 'category_id', 'brand_id', 'status']));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
